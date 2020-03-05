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
     * Languages of Article translations
     * 
     * @var array
     */
    private $translations = [];

    /**
     * Constructor
     * @param $filterGroup FilterGroup
     */
    public function __construct($filterGroup) {
        parent::__construct($filterGroup);
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
        $xpath = new DOMXPath($node->ownerDocument);

        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $submissionDao = Application::getSubmissionDAO();
        $doi = trim($xpath->query('//article-id[@pub-id-type="doi"]')->item(0)->textContent);
        if (!$doi) {
            throw new Exception('DOI not found');
        }
        $submission = $submissionDao->getBySetting('DOI', $doi);
        if (!$submission->getCount()) {
            $submission = $submissionDao->newDataObject();

            $sectionTitle = trim($xpath->query('//article-categories/subj-group/subject')->item(0)->textContent);
            if (!$sectionTitle) {
                throw new Exception('Section not found in XML');
            }
            $sectionTitle = explode(':', $sectionTitle)[0];
            $sectionId = $this->getSectionIdByTitle($sectionTitle, $context->getId());
            if (!$sectionId) {
                throw new Exception('Section not found in OJS');
            }
            $submission->setSectionId($sectionId);

            $this->locale = $this->translateLocale(
                $node->ownerDocument->documentElement->getAttribute('xml:lang')
            );
            if (empty($this->locale)) {
                $this->locale = $context->getPrimaryLocale();
            }
            $submission->setLocale($this->locale);
            $submission->setLanguage($this->locale);
            $this->getLanguageTranslations($node);

            $submission->setContextId($context->getId());
            $submission->stampStatusModified();
            $submission->setDateSubmitted($this->getHistoryDate($xpath, 'received'). ' 00:00:00');
            $submission->setAbstract($this->getINnerHTML($node->getElementsByTagName('abstract')->item(0)), $this->locale);
            foreach ($this->translations as $short => $long) {
                $element = $xpath->query('//trans-abstract[@xml:lang="'.$short.'"]');
                if ($element->length) {
                    $submission->setAbstract($this->getINnerHTML($element->item(0)), $long);
                }
            }
            $submission->setStatus(STATUS_QUEUED);
            $submission->setStageId(WORKFLOW_STAGE_ID_PRODUCTION);
            $submission->setSubmissionProgress(0);
            $submission->setTitle($this->getINnerHTML($node->getElementsByTagName('article-title')->item(0)), $this->locale);
            foreach ($this->translations as $short => $long) {
                $element = $xpath->query('//trans-title-group[@xml:lang="'.$short.'"]/trans-title');
                if ($element->length) {
                    $submission->setTitle($this->getINnerHTML($element->item(0)), $long);
                }
            }

            $submission->setData('DOI', $doi);
            if (!HookRegistry::call('ScieloArticleFilter::handleFrontElement', array(&$submission))) {
                $submissionDao->insertObject($submission);
            }
            $deployment->setSubmission($submission);
        }
        return $submission;
    }

    private function getHistoryDate(\DOMXPath $xpath, string $type): ?string
    {
        $elements = $xpath->query('//history/date');
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
            $this->translations[$lang] = $this->translateLocale($lang);
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
    private function translateLocale(string $locale): string
    {
        switch($locale) {
            case 'en':
                return 'en_US';
            case 'pt':
                return 'pt_BR';
            case 'es':
                return 'es_ES';
            default:
                return $locale;
        }
    }
}