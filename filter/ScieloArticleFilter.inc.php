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
        $submission = $submissionDao->getBySetting('DOI', $doi);
        if (!$submission->getCount()) {
            $submission = $submissionDao->newDataObject();

            $sectionTitle = trim($this->xpath->query('//article-categories/subj-group/subject')->item(0)->textContent);
            if (!$sectionTitle) {
                throw new Exception('Section not found in XML');
            }
            $sectionTitle = explode(':', $sectionTitle)[0];
            $sectionId = $this->getSectionIdByTitle($sectionTitle, $context->getId());
            if (!$sectionId) {
                throw new Exception('Section not found in OJS');
            }
            $submission->setSectionId($sectionId);

            $this->countryCode = strtoupper($node->ownerDocument->documentElement->getAttribute('xml:lang'));
            $this->locale = $this->countryToLocale($this->countryCode);
            if (empty($this->locale)) {
                $this->locale = $context->getPrimaryLocale();
            }
            $submission->setLocale($this->locale);
            $submission->setLanguage($this->locale);
            $this->getLanguageTranslations($node);

            $submission->setContextId($context->getId());
            $submission->stampStatusModified();
            $submission->setDateSubmitted($this->getHistoryDate('received'). ' 00:00:00');
            $submission->setAbstract($this->getINnerHTML($node->getElementsByTagName('abstract')->item(0)), $this->locale);
            foreach ($this->translations as $short => $long) {
                $element = $this->xpath->query('//trans-abstract[@xml:lang="'.$short.'"]');
                if ($element->length) {
                    $submission->setAbstract($this->getINnerHTML($element->item(0)), $long);
                }
            }
            $submission->setStatus(STATUS_QUEUED);
            $submission->setStageId(WORKFLOW_STAGE_ID_PRODUCTION);
            $submission->setSubmissionProgress(0);
            $submission->setTitle($this->getINnerHTML($node->getElementsByTagName('article-title')->item(0)), $this->locale);
            foreach ($this->translations as $short => $long) {
                $element = $this->xpath->query('//trans-title-group[@xml:lang="'.$short.'"]/trans-title');
                if ($element->length) {
                    $submission->setTitle($this->getINnerHTML($element->item(0)), $long);
                }
            }

            $submission->setData('DOI', $doi);
            if (!HookRegistry::call('ScieloArticleFilter::saveSubmission', array(&$submission))) {
                $submissionDao->insertObject($submission);
            }
            $deployment->setSubmission($submission);
            $this->saveAuthors($submission, $node);
            $this->saveFiles($submission, $node);
        }
        return $submission;
    }

    private function saveAuthors(\Article $submission, \DOMElement $node)
    {
        $authors = $this->xpath->query('//contrib-group/contrib');
        if(!$authors->length) {
            return;
        }
        $authorDao = DAORegistry::getDAO('AuthorDAO');
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
            $author->setSubmissionId($submission->getId());
            $author->setPrimaryContact($this->isPrimaryContact($authorNode));
            $author->setIncludeInBrowse(1);
            $author->setSubmissionLocale($submission->getLocale());
            $author->setEmail($this->plugin->getSetting($submission->getJournalId(), 'defaultAuthorEmail'));
            $author = $this->setAff($node, $author);
            if (!HookRegistry::call('ScieloArticleFilter::saveAuthors', array(&$author, &$authorDao, &$submission))) {
                $authorDao->insertObject($author);
            }
        }
    }

    private function setAff(\DOMNode $node, Author $author): Author
    {
        $elements = $node->getElementsByTagName('xref');
        if (!$elements->length) {
            return $author;
        }
        foreach($elements as $element) {
            if ($element->getAttribute('ref-type') == 'aff') {
                $rid = $element->getAttribute('rid');
                $aff = $this->xpath->query('//aff[@id="'.$rid.'"]')->item(0);
                $author = $this->setCountry($aff, $author);
                $author = $this->setCity($aff, $author);
                $author = $this->setinstituition($aff, $author);
                return $author;
            }
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
                'instituition-' . $item->getAttribute('content-type'),
                $item->textContent,
                $this->locale
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
        $country = $node->getElementsByTagName('country');
        if (!$country->length) {
            $author->setData('country', $this->countryCode);
            return $author;
        }
        $author->setData('country', $country->item(0)->textContent);
        $author->setData('countryCode', $country->item(0)->getAttribute('country'));
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