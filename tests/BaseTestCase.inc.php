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
    public function mockJournal()
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

    public function mockUser()
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
}