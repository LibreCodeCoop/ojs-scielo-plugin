<?php

require_once __DIR__ . '/ScieloSubmissionFilter.inc.php';

class ScieloArticleFilter extends ScieloSubmissionFilter
{

    /** 
     * @var NativeImportExportDeployment
     */
    public $_deployment;

    /**
     * Primary locale of Article
     *
     * @var string
     */
    private $locale;

    /**
     * Country code
     *
     * @var string
     */
    private $countryCode;

    /**
     * Languages of Article translations
     * 
     * @var array
     */
    private $translations = [];

    /**
     * ScieloPlugin
     *
     * @var ScieloPlugin
     */
    private $plugin;

    /**
     * DOMXPath
     *
     * @var \DOMXPath
     */
    private $xpath;

    /**
     * Article
     *
     * @var Article
     */
    private $submission;

    /**
     * Constructor
     * @param $filterGroup FilterGroup
     */
    public function __construct($filterGroup) {
        parent::__construct($filterGroup);
    }

    public function setPlugin(ScieloPlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function getClassName()
    {
        return 'plugins.importexport.scielo.filter.ScieloArticleFilter';
    }

    /**
     * @see Filter::process()
     * @param $input string|DOMDocument
     * @return mixed array
     */
    public function &process(&$input)
    {
        $importedObjects =& parent::process($input);
        // Index imported content
        import('classes.search.ArticleSearchIndex');
        foreach ($importedObjects as $submission) {
            assert(is_a($submission, 'Submission'));
            ArticleSearchIndex::articleMetadataChanged($submission);
            ArticleSearchIndex::submissionFilesChanged($submission);
        }
        ArticleSearchIndex::articleChangesFinished();

        return $importedObjects;
    }

    /**
     * Handle an Article import.
     * The Article must have a valid section in order to be imported
     * @param $node DOMElement
     */
    public function handleElement(\DOMElement $node)
    {
// issues
// issue_settings
// submissions
// submission_file_settings
// submission_files
// submission_galleys
// published_submissions

        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        
        $submission = $this->saveSubmission($node);
    }

    private function saveSubmission(\DOMElement $node)
    {
        $this->xpath = new DOMXPath($node->ownerDocument);

        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $submissionDao = Application::getSubmissionDAO();
        $doi = trim($this->xpath->query('//article-id[@pub-id-type="doi"]')->item(0)->textContent);
        if (!$doi) {
            throw new Exception('DOI not found');
        }
        $this->submission = $submissionDao->getBySetting('DOI', $doi);
        if (!$this->submission->getCount()) {
            $this->submission = $submissionDao->newDataObject();

            $sectionTitle = trim($this->xpath->query('//article-categories/subj-group/subject')->item(0)->textContent);
            if (!$sectionTitle) {
                throw new Exception('Section not found in XML');
            }
            $sectionTitle = explode(':', $sectionTitle)[0];
            $sectionId = $this->getSectionIdByTitle($sectionTitle, $context->getId());
            if (!$sectionId) {
                throw new Exception('Section not found in OJS');
            }
            $this->submission->setSectionId($sectionId);

            $this->countryCode = strtoupper($node->ownerDocument->documentElement->getAttribute('xml:lang'));
            $this->locale = $this->countryToLocale($this->countryCode);
            if (empty($this->locale)) {
                $this->locale = $context->getPrimaryLocale();
            }
            $this->submission->setLocale($this->locale);
            $this->submission->setLanguage($this->locale);
            $this->getLanguageTranslations($node);


            $license = $node->getElementsByTagName('license');
            if ($license->length) {
                $this->submission->setLicenseURL($license->item(0)->getAttribute('xlink:href'));
            }
            $this->submission->setContextId($context->getId());
            $this->submission->stampStatusModified();
            $this->submission->setDateSubmitted($this->getHistoryDate('received'). ' 00:00:00');
            $this->submission->setAbstract($this->getINnerHTML($node->getElementsByTagName('abstract')->item(0)), $this->locale);
            foreach ($this->translations as $short => $long) {
                $element = $this->xpath->query('//trans-abstract[@xml:lang="'.$short.'"]');
                if ($element->length) {
                    $this->submission->setAbstract($this->getINnerHTML($element->item(0)), $long);
                }
            }
            $this->submission->setStatus(STATUS_QUEUED);
            $this->submission->setStageId(WORKFLOW_STAGE_ID_PRODUCTION);
            $this->submission->setSubmissionProgress(0);
            $this->submission->setTitle($this->getINnerHTML($node->getElementsByTagName('article-title')->item(0)), $this->locale);
            foreach ($this->translations as $short => $long) {
                $element = $this->xpath->query('//trans-title-group[@xml:lang="'.$short.'"]/trans-title');
                if ($element->length) {
                    $this->submission->setTitle($this->getINnerHTML($element->item(0)), $long);
                }
            }

            $this->submission->setData('DOI', $doi);
            if (!HookRegistry::call('ScieloArticleFilter::saveSubmission', array(&$this->submission))) {
                $submissionDao->insertObject($this->submission);
            }
            $deployment->setSubmission($this->submission);
            $this->saveAuthors($node);
            $this->saveFiles($node);
        }
        return $this->submission;
    }

    private function saveFiles(\DOMElement $node)
    {
        
    }

    private function saveAuthors(\DOMElement $node)
    {
        $authors = $this->xpath->query('//contrib-group/contrib');
        if(!$authors->length) {
            return;
        }
        $authorDao = DAORegistry::getDAO('AuthorDAO');
        /** @var Author */
        $author = $authorDao->newDataObject();
        foreach ($authors as $authorNode) {
            $name = $authorNode->getElementsByTagName('name')->item(0);
            if ($name->getElementsByTagName('given-names')->length) {
                $author->setGivenName(trim($name->getElementsByTagName('given-names')->item(0)->textContent), $this->locale);
            }
            if ($name->getElementsByTagName('surname')->length) {
                $author->setFamilyName(trim($name->getElementsByTagName('surname')->item(0)->textContent), $this->locale);
            }
            if ($name->getElementsByTagName('suffix')->length) {
                $author->setData('suffix', trim($name->getElementsByTagName('suffix')->item(0)->textContent), $this->locale);
            }
            $author->setUserGroupId(14); // Author
            $author->setSubmissionId($this->submission->getId());
            $author->setPrimaryContact($this->isPrimaryContact($authorNode));
            $author->setIncludeInBrowse(1);
            $author->setSubmissionLocale($this->submission->getLocale());
            $author = $this->setXref($node, $author);
            if (!HookRegistry::call('ScieloArticleFilter::saveAuthors', array(&$author, &$authorDao, &$submission))) {
                $authorDao->insertObject($author);
            }
        }
    }

    private function getPrimaryContactEmail()
    {
        $this->xpath->query('//aff[@id="'.$rid.'"]')->item(0);
    }

    private function setXref(\DOMNode $node, Author $author): Author
    {
        $elements = $node->getElementsByTagName('xref');
        if (!$elements->length) {
            return $author;
        }
        foreach($elements as $element) {
            $rid = $element->getAttribute('rid');
            $type = $element->getAttribute('ref-type');
            $aff = $this->xpath->query('//'.$type.'[@id="'.$rid.'"]')->item(0);
            $author = $this->setCountry($aff, $author);
            $author = $this->setCity($aff, $author);
            $author = $this->setinstituition($aff, $author);
            $author = $this->setEmail($aff, $author);
        }
        return $author;
    }

    private function setEmail(\DOMNode $node, Author $author): Author
    {
        $email = $node->getElementsByTagName('email');
        if ($email->length) {
            $author->setEmail($email->item(0)->textContent);
        } else {
            $author->setEmail(
                $this->plugin->getSetting(
                    $this->submission->getJournalId(),
                    'defaultAuthorEmail'
                )
            );
        }
        return $author;
    }

    private function setinstituition(\DOMNode $node, Author $author): Author
    {
        $institution = $node->getElementsByTagName('institution');
        if (!$institution->length) {
            return $author;
        }
        foreach ($institution as $item) {
            $author->setData(
                'institution-' . $item->getAttribute('content-type'),
                trim($item->textContent)
            );
        }
        return $author;
    }

    private function setCity(\DOMNode $node, Author $author): Author
    {
        $city = $node->getElementsByTagName('city');
        if (!$city->length) {
            return $author;
        }
        $author->setData('city', $city->item(0)->textContent, $this->locale);
        return $author;
    }

    private function setCountry(\DOMNode $node, Author $author): Author
    {
        if ($author->getCountry()) {
            return $author;
        }
        $country = $node->getElementsByTagName('country');
        /** @var CountryDAO */
        $countryDao = DAORegistry::getDAO('CountryDAO');
        $defaultLocale = $this->plugin->getSetting(
            $this->submission->getJournalId(),
            'defaultLocale'
        );
        if (!$country->length) {
            $author->setData('country', $countryDao->getCountry($this->countryCode, $defaultLocale));
            return $author;
        }
        $countryCode = $country->item(0)->getAttribute('country');
        $author->setData('country', $countryDao->getCountry($countryCode, $defaultLocale));
        $author->setData('countryCode', $countryCode);
        return $author;
    }

    private function isPrimaryContact(\DOMNode $node): bool
    {
        $elements = $node->getElementsByTagName('xref');
        if (!$elements->length) {
            return false;
        }
        foreach($elements as $element) {
            if ($element->getAttribute('ref-type') == 'corresp') {
                return true;
            }
        }
        return false;
    }

    private function getHistoryDate(string $type): ?string
    {
        $elements = $this->xpath->query('//history/date');
        if ($elements->length)
        foreach($elements as $element) {
            if ($element->getAttribute('date-type') == $type) {
                return $element->getElementsByTagName('year')->item(0)->textContent . '-' .
                       $element->getElementsByTagName('month')->item(0)->textContent . '-' .
                       $element->getElementsByTagName('day')->item(0)->textContent;
            }
        }
    }

    private function getLanguageTranslations(\DOMElement $node)
    {
        $titles = $node->getElementsByTagName('trans-title-group');
        foreach ($titles as $title) {
            $lang = $title->getAttribute('xml:lang');
            $this->translations[$lang] = $this->countryToLocale($lang);
        }
    }

    private function getINnerHTML(\DOMNode $node): string
    {
        $innerHTML = '';
        foreach ($node->childNodes as $child)  { 
            $innerHTML .= $node->ownerDocument->saveHTML($child);
        }
        return trim($this->translateTags($innerHTML));
    }

    private function translateTags(string $text): string
    {
        $text = str_replace(
            ['<italic>', '</italic>'],
            ['<i>', '</i>'],
            $text
        );
        return $text;
    }

    /**
     * Return the section id By Title or null when not found
     *
     * @param string $sectionTitle
     * @param integer $journalId
     * @return integer|null
     */
    private function getSectionIdByTitle(string $sectionTitle, int $journalId): ?int
    {
        $return = null;
        if (HookRegistry::call('ScieloArticleFilter::getSectionCodeByTitle', array(&$sectionTitle, &$journalId, &$return))) {
            return $return;
        }
        $sectionDao = Application::getSectionDAO();
        $section = $sectionDao->getByTitle($sectionTitle, $journalId);
        if ($section) {
            return $section->getId();
        }
        return $return;
    }

    /**
     * Convert small locale to long locale
     *
     * @param string $locale
     * @return string
     */
    private function countryToLocale(string $locale): string
    {
        switch(strtoupper($locale)) {
            case 'EN':
                return 'en_US';
            case 'PT':
                return 'pt_BR';
            case 'ES':
                return 'es_ES';
            default:
                return $locale;
        }
    }
}