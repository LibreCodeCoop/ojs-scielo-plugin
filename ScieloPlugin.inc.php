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

		switch ($command) {
			case 'import':
				$xmlFile = array_shift($args);
				$journalPath = array_shift($args);
		
				AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER, LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_PKP_SUBMISSION);
		
				$journalDao = DAORegistry::getDAO('JournalDAO');
				$userDao = DAORegistry::getDAO('UserDAO');
		
				$journal = $journalDao->getByPath($journalPath);
		
				if (!$journal) {
					if ($journalPath != '') {
						echo __('plugins.importexport.common.cliError') . "\n";
						echo __('plugins.importexport.common.error.unknownJournal', ['journalPath' => $journalPath]) . "\n\n";
					}
					$this->usage($scriptName);
					return;
				}
		
				if ($xmlFile && $this->isRelativePath($xmlFile)) {
					$xmlFile = PWD . '/' . $xmlFile;
				}

				$userName = array_shift($args);
				$user = $userDao->getByUsername($userName);

				if (!$user) {
					if ($userName != '') {
						echo __('plugins.importexport.common.cliError') . "\n";
						echo __('plugins.importexport.scielo.error.unknownUser', ['userName' => $userName]) . "\n\n";
					}
					$this->usage($scriptName);
					return;
				}

				if (!file_exists($xmlFile)) {
					echo __('plugins.importexport.common.cliError') . "\n";
					echo __('plugins.importexport.common.export.error.inputFileNotReadable', ['param' => $xmlFile]) . "\n\n";
					$this->usage($scriptName);
					return;
				}

				$xmlString = file_get_contents($xmlFile);
				$document = new DOMDocument();
				try {
					$document->loadXml($xmlString);
				} catch (\Exception $e) {
					echo __('plugins.importexport.common.cliError') . "\n";
					echo __('plugins.importexport.scielo.error.invalidXmlFile', [
						'xmlFile' => $xmlFile,
						'message' => $e->getMessage()
					]) . "\n\n";
					$this->usage($scriptName);
					return;
				}
				$this->import('ScieloDeployment');
				$deployment = new ScieloDeployment($journal, $user);
				$deployment->setImportPath(dirname($xmlFile));
				$filter = 'scielo-xml=>article';
				$content = $this->importSubmissions($xmlString, $filter, $deployment);
				return;
		}
		$this->usage($scriptName);
	}

	/**
	 * Get the XML for a set of submissions wrapped in a(n) issue(s).
	 * @param $importXml string XML contents to import
	 * @param $filter string Filter to be used
	 * @param $deployment PKPImportExportDeployment
	 * @return array Set of imported submissions
	 */
	function importSubmissions($importXml, $filter, $deployment) {
		$filterDao = DAORegistry::getDAO('FilterDAO');
		$scieloImportFilters = $filterDao->getObjectsByGroup($filter);
		assert(count($scieloImportFilters) == 1); // Assert only a single unserialization filter
		/** @var ScieloArticleFilter */
		$importFilter = array_shift($scieloImportFilters);
		$importFilter->setDeployment($deployment);

		$importXml = $this->replaceByLocalPublicId($importXml);
		return $importFilter->execute($importXml);
	}

	private function replaceByLocalPublicId(string $xml): string
	{
		$original = new DOMDocument();
		$original->loadXML($xml);
		$doctype = $original->doctype;
		$document = new DOMDocument();
		$document->loadXML(file_get_contents(__DIR__.'/jats-dtds/schema/catalog.xml'));
		$xpath = new DOMXPath($document);
		$xpath->registerNameSpace('catalog', 'urn:oasis:names:tc:entity:xmlns:xml:catalog');
		$item = $xpath->query('//catalog:public[@publicId=\''.$doctype->publicId.'\']');
		if (!$item->length) {
			throw new Exception(__('plugins.importexport.scielo.error.unsuportedPublicId',
				['publicId' => $doctype->publicId]
			));
		}
		$uri = __DIR__.'/jats-dtds/schema/' . $item->item(0)->getAttribute('uri');
		$xml = preg_replace_callback('/DOCTYPE.*" "(http.*dtd)">/s', function($n) use($uri) {
			return str_replace($n[1], $uri, $n[0]);
		}, $xml);
		return $xml;
	}

	/**
	 * @copydoc ImportExportPlugin::usage()
	 */
	public function usage($scriptName) {
		echo __('plugins.importexport.scielo.cliUsage', [
			'scriptName' => $scriptName,
			'pluginName' => $this->getName()
		]) . "\n";
	}
}