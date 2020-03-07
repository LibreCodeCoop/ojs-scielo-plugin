<?php

import('lib.pkp.classes.form.Form');

class ScieloSettingsForm extends Form {

	//
	// Private properties
	//
	/** @var integer */
	var $_contextId;

	/**
	 * Get the context ID.
	 * @return integer
	 */
	function _getContextId() {
		return $this->_contextId;
	}

	/** @var ScieloPlugin */
	var $_plugin;

	/**
	 * Get the plugin.
	 * @return ScieloPlugin
	 */
	function _getPlugin() {
		return $this->_plugin;
	}


	//
	// Constructor
	//
	/**
	 * Constructor
	 * @param $plugin ScieloPlugin
	 * @param $contextId integer
	 */
	function __construct($plugin, $contextId) {
		$this->_contextId = $contextId;
		$this->_plugin = $plugin;

		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

		// Add form validation checks.
		$this->addCheck(new FormValidatorEmail($this, 'defaultAuthorEmail', 'required', 'plugins.importexport.crossref.settings.form.defaultAuthorEmailRequired'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}


	//
	// Implement template methods from Form
	//
	/**
	 * @copydoc Form::initData()
	 */
	function initData() {
		$contextId = $this->_getContextId();
		$plugin = $this->_getPlugin();
		foreach($this->getFormFields() as $fieldName => $fieldType) {
			$this->setData($fieldName, $plugin->getSetting($contextId, $fieldName));
		}
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array_keys($this->getFormFields()));
	}

	/**
	 * Execute the form.
	 */
	function execute() {
		$plugin = $this->_getPlugin();
		$contextId = $this->_getContextId();
		foreach($this->getFormFields() as $fieldName => $fieldType) {
			$plugin->updateSetting($contextId, $fieldName, $this->getData($fieldName), $fieldType);
		}
	}


	//
	// Public helper methods
	//
	/**
	 * Get form fields
	 * @return array (field name => field type)
	 */
	function getFormFields() {
		return array(
			'defaultAuthorEmail' => 'string',
		);
	}

	/**
	 * Is the form field optional
	 * @param $settingName string
	 * @return boolean
	 */
	function isOptional($settingName) {
		return in_array($settingName, []);
	}

}
