<?php

require_once __DIR__ . '/ScieloSubmissionFilter.inc.php';

class ScieloArticleFilter extends ScieloSubmissionFilter
{

    /** @var NativeImportExportDeployment */
    var $_deployment;

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

            $submissionLocale = $this->translateLocale(
                $node->ownerDocument->documentElement->getAttribute('xml:lang')
            );
            if (empty($submissionLocale)) {
                $submissionLocale = $context->getPrimaryLocale();
            }
            $submission->setLocale($submissionLocale);

            $submission->setContextId($context->getId());
            $submission->stampStatusModified();
            $submission->setStatus(STATUS_QUEUED);
            $submission->setSubmissionProgress(0);
            $submission->setTitle($node->getElementsByTagName('article-title')->item(0)->textContent, $submissionLocale);

            $submission->setData('DOI', $doi);
            if (!HookRegistry::call('ScieloArticleFilter::handleFrontElement', array(&$submission))) {
                $submissionDao->insertObject($submission);
            }
            $deployment->setSubmission($submission);
        }
        return $submission;
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
     * @return string|null
     */
    private function translateLocale(string $locale): ?string
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