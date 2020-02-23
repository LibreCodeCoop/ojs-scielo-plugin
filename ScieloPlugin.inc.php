<?php

import('lib.pkp.classes.plugins.ImportExportPlugin');
class ScieloPlugin extends ImportExportPlugin {
	/**
	 * @copydoc ImportExportPlugin::register()
	 */
	public function register($category, $path, $mainContextId = NULL) {
		$success = parent::register($category, $path);
		$this->addLocaleData();
		return $success;
	}

	/**
	 * @copydoc ImportExportPlugin::getName()
	 */
	public function getName() {
		return 'ScieloPlugin';
	}

	/**
	 * @copydoc ImportExportPlugin::getDisplayName()
	 */
	public function getDisplayName() {
		return __('plugins.importexport.scielo.name');
	}

	/**
	 * @copydoc ImportExportPlugin::getDescription()
	 */
	public function getDescription() {
		return __('plugins.importexport.scielo.description');
	}

	/**
	 * @copydoc ImportExportPlugin::register()
	 */
	public function display($args, $request) {
		parent::display($args, $request);

		// Get the journal or press id
		$contextId = Application::get()->getRequest()->getContext()->getId();

		// Use the path to determine which action
		// should be taken.
		$path = array_shift($args);
		switch ($path) {

			// Stream a CSV file for download
			case 'exportAll':
				header('content-type: text/comma-separated-values');
				header('content-disposition: attachment; filename=articles-' . date('Ymd') . '.csv');
				$publications = $this->getAll($contextId);
				$this->export($publications, 'php://output');
				break;

			// When no path is requested, display a list of publications
			// to export and a button to run the `exportAll` path.
			default:
				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign('publications', $this->getAll($contextId));
				$templateMgr->display($this->getTemplateResource('export.tpl'));
		}
	}

	/**
	 * @copydoc ImportExportPlugin::executeCLI()
	 */
	public function executeCLI($scriptName, &$args) {
		$command = array_shift($args);
		$xmlFile = array_shift($args);
		$journalPath = array_shift($args);

		AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER, LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_PKP_SUBMISSION);

		$journalDao = DAORegistry::getDAO('JournalDAO');
		$userDao = DAORegistry::getDAO('UserDAO');

		$journal = $journalDao->getByPath($journalPath);

		if (!$journal) {
			if ($journalPath != '') {
				echo __('plugins.importexport.common.cliError') . "\n";
				echo __('plugins.importexport.common.error.unknownJournal', array('journalPath' => $journalPath)) . "\n\n";
			}
			$this->usage($scriptName);
			return;
		}

		if ($xmlFile && $this->isRelativePath($xmlFile)) {
			$xmlFile = PWD . '/' . $xmlFile;
		}

		switch ($command) {
			case 'import':
				$userName = array_shift($args);
				$user = $userDao->getByUsername($userName);

				if (!$user) {
					if ($userName != '') {
						echo __('plugins.importexport.common.cliError') . "\n";
						echo __('plugins.importexport.native.error.unknownUser', array('userName' => $userName)) . "\n\n";
					}
					$this->usage($scriptName);
					return;
				}

				if (!file_exists($xmlFile)) {
					echo __('plugins.importexport.common.cliError') . "\n";
					echo __('plugins.importexport.common.export.error.inputFileNotReadable', array('param' => $xmlFile)) . "\n\n";
					$this->usage($scriptName);
					return;
				}

				$xmlString = file_get_contents($xmlFile);
				$document = new DOMDocument();
				try {
					$document->loadXml($xmlString);
				} catch (\Exception $th) {
					echo __('plugins.importexport.common.cliError') . "\n";
					echo __('plugins.importexport.scielo.error.invalidXmlFile', array('param' => $xmlFile)) . "\n\n";
					$this->usage($scriptName);
					return;
				}
				return;
		}
		$this->usage($scriptName);
	}

	/**
	 * @copydoc ImportExportPlugin::usage()
	 */
	public function usage($scriptName) {
		echo __('plugins.importexport.scielo.cliUsage', array(
			'scriptName' => $scriptName,
			'pluginName' => $this->getName()
		)) . "\n";
	}
}