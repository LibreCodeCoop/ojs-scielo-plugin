<?php

import('lib.pkp.tests.PKPTestHelper');

class BaseTestCase extends PHPUnit_Framework_TestCase
{
    /**
     * Execute the plug-in via its CLI interface.
     * @param $pluginName string
     * @param $args array
     * @return string CLI output
     */
    protected function executeCLI($pluginName, $args) {
        ob_start();
        $plugin = $this->instantiatePlugin($pluginName);
        PKPTestHelper::xdebugScream(false);
        $plugin->executeCLI(get_class($this), $args, true);
        PKPTestHelper::xdebugScream(true);
        return ob_get_clean();
    }

    /**
     * Instantiate an import-export plugin.
     * @param $pluginName string
     * @return ImportExportPlugin
     */
    protected function instantiatePlugin($pluginName) {
        // Load all import-export plug-ins.
        if (!defined('PWD')) define('PWD', getcwd());
        PluginRegistry::loadCategory('importexport');
        $plugin = PluginRegistry::getPlugin('importexport', $pluginName);
        // self::assertType() has been removed from PHPUnit 3.6
        // but self::assertInstanceOf() is not present in PHPUnit 3.4
        // which is our current test server version.
        // FIXME: change this to assertInstanceOf() after upgrading the
        // test server.
        self::assertTrue(is_a($plugin, 'ImportExportPlugin'));
        return $plugin;
    }
}