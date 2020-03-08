<?php

import('lib.pkp.tests.PKPTestHelper');

class BaseTestCase extends PHPUnit_Framework_TestCase
{
    /**
     * Execute the plug-in via its CLI interface.
     * @param $pluginPath string
     * @param $args array
     * @return string CLI output
     */
    protected function executeCLI($pluginPath, $args) {
        ob_start();
        if (!defined('PWD')) define('PWD', getcwd());
        $plugin = PluginRegistry::loadPlugin('importexport', $pluginPath);
        self::assertTrue(is_a($plugin, 'ImportExportPlugin'));
        PKPTestHelper::xdebugScream(false);
        $plugin->executeCLI(get_class($this), $args, true);
        PKPTestHelper::xdebugScream(true);
        return ob_get_clean();
    }
    protected function mockJournal()
    {
        // Mock Journal
        HookRegistry::clear('contextdao::_getbypath');
        HookRegistry::register('contextdao::_getbypath',function($hookName, $args) {
            $args[2] = new ADORecordSet_array();
            $args[2]->_numOfRows = 1;
            $args[2]->fields = $args[2]->bind = [
                'journal_id' => 1,
                'path' => 'validPath',
                'seq' => 1,
                'primary_locale' => 'en_US',
                'enabled' => 1
            ];
            return true;
        });
        HookRegistry::register('dao::_getdataobjectsettings', function($hookName, $args) {
            $args[2] = new ADORecordSet_empty();
            return true;
        });
    }

    protected function mockUser()
    {
        // Mock User
        HookRegistry::clear('userdao::_getbyusername');
        HookRegistry::register('userdao::_getbyusername',function($hookName, $args) {
            $args[2] = new ADORecordSet_array();
            $args[2]->_numOfRows = 1;
            $user = json_decode(file_get_contents(
                PHPUNIT_ADDITIONAL_INCLUDE_DIRS.'/mock/userData.json'
            ), true);
            $args[2]->fields = $args[2]->bind = $user;
            return true;
        });
        HookRegistry::clear('dao::_getdataobjectsettings');
        HookRegistry::register('dao::_getdataobjectsettings', function($hookName, $args) {
            $args[2] = new ADORecordSet_empty();
            return true;
        });
    }

    protected function mockFilter()
    {
        HookRegistry::clear('filterdao::_getobjectsbygroup');
        HookRegistry::register('filterdao::_getobjectsbygroup', function($hookName, $args) {
            $args[2] = new ADORecordSet_array();
            $args[2]->_numOfRows = $args[2]->_currentRow= 1;
            $args[2]->fields = $args[2]->bind = [
                'filter_id' => 1,
                'class_name' => 'plugins.importexport.scielo.filter.ScieloArticleFilter',
                'filter_group_id' => 36,
                'display_name' => 'ScieloArticleFilter',
                'is_template' => 0,
                'parent_filter_id' => 0,
                'seq' => 0
            ];
            return true;
        });
    }

    protected function mockFilterGroup()
    {
        HookRegistry::clear('filtergroupdao::_getobjectbyid');
        HookRegistry::register('filtergroupdao::_getobjectbyid', function($hookName, $args) {
            $args[2] = new ADORecordSet_array();
            $args[2]->_numOfRows = $args[2]->_currentRow= 1;
            switch ($args[1]) {
                case 1:
                    $args[2]->fields = $args[2]->bind = [
                        'filter_group_id' => 1,
                        'symbolic' => 'article=>dc11',
                        'display_name' => 'plugins.metadata.dc11.articleAdapter.displayName',
                        'description' => 'plugins.metadata.dc11.articleAdapter.description',
                        'input_type' => 'class::classes.article.Article',
                        'output_type' => 'metadata::plugins.metadata.dc11.schema.Dc11Schema(ARTICLE)',
                    ];
                    break;
                case 3:
                    $args[2]->fields = $args[2]->bind = [
                        'filter_group_id' => 3,
                        'symbolic' => 'article=>mods34',
                        'display_name' => 'plugins.metadata.mods34.articleAdapter.displayName',
                        'description' => 'plugins.metadata.mods34.articleAdapter.description',
                        'input_type' => 'class::classes.article.Article',
                        'output_type' => 'metadata::plugins.metadata.mods34.schema.Mods34Schema(ARTICLE)',
                    ];
                    break;
                case 36:
                    $args[2]->fields = $args[2]->bind = [
                        'filter_group_id' => 1,
                        'symbolic' => 'scielo-xml=>article',
                        'display_name' => 'plugins.importexport.scielo.displayName',
                        'description' => 'plugins.importexport.scielo.description',
                        'input_type' => 'xml::dtd',
                        'output_type' => 'class::classes.article.Article[]',
                    ];
            }
            return true;
        });
    }
}