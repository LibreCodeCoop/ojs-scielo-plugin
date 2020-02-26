<?php

import('lib.pkp.classes.filter.PersistableFilter');

class ArticleScieloFilter extends PersistableFilter {

    /** @var NativeImportExportDeployment */
    var $_deployment;

    /**
     * Constructor
     * @param $filterGroup FilterGroup
     */
    public function __construct($filterGroup) {
        parent::__construct($filterGroup);
    }

    //
    // Deployment management
    //
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

    public function getClassName()
    {
        return 'plugins.importexport.scielo.filter.ArticleScieloFilter';
    }

    /**
     * @see Filter::process()
     * @param $input string
     * @return mixed array
     */
    public function &process(&$input)
    {
        $return = [];
        return $return;
    }
}