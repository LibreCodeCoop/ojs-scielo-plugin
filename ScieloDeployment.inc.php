<?php

import('lib.pkp.classes.plugins.importexport.PKPImportExportDeployment');

class ScieloDeployment extends PKPImportExportDeployment {

	/**
	 * Constructor
	 * @param $context Context
	 * @param $user User
	 */
	function __construct($context, $user) {
		parent::__construct($context, $user);
	}
}
