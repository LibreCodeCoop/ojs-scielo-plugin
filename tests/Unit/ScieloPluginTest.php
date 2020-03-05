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
            $locale = $args[0]->getLanguage();
            $this->assertEquals('en_US', $locale);
            $this->assertEquals('en_US', $args[0]->getLocale());
            $this->assertEquals('English Title <i>italic</i>', $args[0]->getTitle($locale));
            $this->assertEquals('<i>pt_BR Title</i>', $args[0]->getTitle('pt_BR'));
            $this->assertEquals('<i>es Title</i>', $args[0]->getTitle('es_ES'));
            $this->assertEquals('<p>Abstract en.</p>', $args[0]->getAbstract($locale));
            $this->assertEquals('<p>Trans Abstract pt.</p>', $args[0]->getAbstract('pt_BR'));
            $this->assertEquals('<p>Trans Abstract es.</p>', $args[0]->getAbstract('es_ES'));
            $this->assertEquals('2016-08-31 00:00:00', $args[0]->getDateSubmitted());
            $this->assertEquals(1, $args[0]->getJournalId());
            $this->assertEquals(STATUS_QUEUED, $args[0]->getStatus());
            $this->assertEquals(WORKFLOW_STAGE_ID_PRODUCTION, $args[0]->getStageId());
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