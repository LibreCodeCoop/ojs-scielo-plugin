<?php

import('plugins.importexport.scielo.tests.BaseTestCase');

class ScieloPluginTest extends BaseTestCase
{
    public function testComandWithoutArgsReturnUsage()
    {
        HookRegistry::register('contextdao::_getbypath',function($hookName, $args) {
            $args[2] = new ADORecordSet_empty();
            return true;
        });
        $return = $this->executeCLI('ScieloPlugin', []);
        $this->assertStringStartsWith('Usage: ', $return);
    }

    public function testComandWithInvalidJournalPath()
    {
        HookRegistry::register('contextdao::_getbypath',function($hookName, $args) {
            $args[2] = new ADORecordSet_empty();
            return true;
        });
        $return = $this->executeCLI('ScieloPlugin', ['import', 'file.xml', 'invalidPath']);
        $this->assertNotNull(strpos($return, 'The specified journal path, "path", does not exist.'));
    }

    public function testComandWithInvalidUser()
    {
        HookRegistry::register('contextdao::_getbypath',function($hookName, $args) {
            $args[2] = new ADORecordSet_empty();
            return true;
        });
        $return = $this->executeCLI('ScieloPlugin', ['import', 'file.xml', 'invalidPath']);
        $this->assertNotNull(strpos($return, 'The specified journal path, "path", does not exist.'));
    }
}