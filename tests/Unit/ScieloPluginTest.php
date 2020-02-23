<?php

import('plugins.importexport.scielo.tests.BaseTestCase');

class ScieloPluginTest extends BaseTestCase
{
    public function testWhenWithoutArgsReturnUsage()
    {
        HookRegistry::clear('contextdao::_getbypath');
        HookRegistry::register('contextdao::_getbypath',function($hookName, $args) {
            $args[2] = new ADORecordSet_empty();
            return true;
        });
        $return = $this->executeCLI('scielo', []);
        $this->assertStringStartsWith('Usage: ', $return);
    }

    public function testInvalidJournalPath()
    {
        HookRegistry::clear('contextdao::_getbypath');
        HookRegistry::register('contextdao::_getbypath',function($hookName, $args) {
            $args[2] = new ADORecordSet_empty();
            return true;
        });
        $return = $this->executeCLI('scielo', ['import', 'file.xml', 'invalidPath']);
        $this->assertNotNull(strpos($return, 'The specified journal path, "path", does not exist.'));
    }

    public function testInvalidUser()
    {
        HookRegistry::clear('contextdao::_getbypath');
        HookRegistry::register('contextdao::_getbypath',function($hookName, $args) {
            $args[2] = new ADORecordSet_empty();
            return true;
        });
        $return = $this->executeCLI('scielo', ['import', 'file.xml', 'invalidPath']);
        $this->assertNotFalse(strpos($return, 'The specified journal path, "invalidPath", does not exist.'));
    }

    public function testInvalidFile()
    {
        $this->mockJournal();
        $this->mockUser();

        $return = $this->executeCLI('scielo', ['import', 'file.xml', 'invalidFile']);
        $this->assertNotFalse(strpos($return, 'The input file '));
        $this->assertNotFalse(strpos($return, ' is not readable.'));
    }

    public function testValidFileWithInvalidContent()
    {
        $this->mockJournal();
        $this->mockUser();

        $tempNam = tempnam('/tmp', 'TEST');
        $return = $this->executeCLI('scielo', ['import', $tempNam, 'validPath']);
        $this->assertNotFalse(strpos($return, 'Invalid XML file'));
    }

    public function testValidXml()
    {
        $this->mockJournal();
        $this->mockUser();

        $return = $this->executeCLI('scielo', [
            'import',
            PHPUNIT_ADDITIONAL_INCLUDE_DIRS.'/mock/article.xml',
            'validPath'
        ]);
        $this->assertEmpty($return);
    }
}