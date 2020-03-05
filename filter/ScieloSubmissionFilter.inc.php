<?php

import('lib.pkp.classes.filter.PersistableFilter');

class ScieloSubmissionFilter extends PersistableFilter
{
    /**
     * Set the import/export deployment
     * @param $deployment NativeImportExportDeployment
     */
    public function setDeployment($deployment) {
        $this->_deployment = $deployment;
    }

    /**
     * Get the import/export deployment
     * @return NativeImportExportDeployment
     */
    public function getDeployment() {
        return $this->_deployment;
    }

    /**
     * @see Filter::process()
     * @param $document DOMDocument|string
     * @return array Array of imported documents
     */
    public function &process(&$document)
    {
        // If necessary, convert $document to a DOMDocument.
        if (is_string($document)) {
            $xmlString = $document;
            $document = new DOMDocument();
            $document->loadXml($xmlString);
        }
        assert(is_a($document, 'DOMDocument'));

        $deployment = $this->getDeployment();
        $importedObjects = array();
        if ($document->documentElement->tagName == $this->getSubmissionsNodeName()) {
            // Single element (singular) import
            $object = $this->handleElement($document->documentElement);
            if ($object) {
                $importedObjects[] = $object;
            }
        } else {
            throw new Exception('Invalid article node name.');
        }

        return $importedObjects;
    }

    /**
     * Get the submissions node name
     * @return string
     */
    function getSubmissionsNodeName() {
        return 'article';
    }
}