<?php

import('plugins.importexport.scielo.tests.BaseTestCase');

class ScieloPluginTest extends BaseTestCase
{
    public function testWhenWithoutArgsReturnUsage()
    {
        HookRegistry::clear('contextdao::_getbypath');
        HookRegistry::register('contextdao::_getbypath', function($hookName, $args) {
            $args[2] = new ADORecordSet_empty();
            return true;
        });
        $return = $this->executeCLI('scielo', []);
        $this->assertStringStartsWith('Usage: ', $return);
    }

    public function testInvalidJournalPath()
    {
        HookRegistry::clear('contextdao::_getbypath');
        HookRegistry::register('contextdao::_getbypath', function($hookName, $args) {
            $args[2] = new ADORecordSet_empty();
            return true;
        });
        $return = $this->executeCLI('scielo', ['import', 'file.xml', 'invalidPath']);
        $this->assertNotNull(strpos($return, 'The specified journal path, "path", does not exist.'));
    }

    public function testInvalidUser()
    {
        HookRegistry::clear('contextdao::_getbypath');
        HookRegistry::register('contextdao::_getbypath', function($hookName, $args) {
            $args[2] = new ADORecordSet_empty();
            return true;
        });
        $return = $this->executeCLI('scielo', ['import', 'file.xml', 'invalidPath']);
        $this->assertTrue(is_numeric(strpos($return, 'The specified journal path, "invalidPath", does not exist.')));
    }

    public function testInvalidFile()
    {
        $this->mockJournal();
        $this->mockUser();

        $return = $this->executeCLI('scielo', ['import', 'file.xml', 'invalidFile']);
        $this->assertTrue(is_numeric(strpos($return, 'The input file ')));
        $this->assertTrue(is_numeric(strpos($return, ' is not readable.')));
    }

    public function testValidFileWithInvalidContent()
    {
        $this->mockJournal();
        $this->mockUser();

        $tempNam = tempnam('/tmp', 'TEST');
        $return = $this->executeCLI('scielo', ['import', $tempNam, 'validPath']);
        $this->assertTrue(is_numeric(strpos($return, 'Invalid XML file')));
    }

    public function testValidXml()
    {
        $this->mockJournal();
        $this->mockUser();
        $this->mockFilter();
        $this->mockFilterGroup();
        HookRegistry::clear('articledao::_getbysetting');
        HookRegistry::register('articledao::_getbysetting', function($hookName, $args) {
            return true;
        });
        HookRegistry::clear('ScieloArticleFilter::getSectionCodeByTitle');
        HookRegistry::register('ScieloArticleFilter::getSectionCodeByTitle', function($hookName, $args) {
            $args[2] = 1;
            return true;
        });
        HookRegistry::clear('ScieloArticleFilter::handleFrontElement');
        HookRegistry::register('ScieloArticleFilter::handleFrontElement', function($hookName, $args) {
            $locale = $args[0]->getLocale();
            $this->assertEquals('en_US', $locale);
            $this->assertEquals('English Title italic', $args[0]->getTitle($locale));
            $this->assertEquals(1, $args[0]->getJournalId());
            $this->assertEquals(STATUS_QUEUED, $args[0]->getStatus());
            return true;
        });

        $return = $this->executeCLI('scielo', [
            'import',
            PHPUNIT_ADDITIONAL_INCLUDE_DIRS.'/mock/article.xml',
            'validPath'
        ]);
        $this->assertEmpty($return);
    }
}