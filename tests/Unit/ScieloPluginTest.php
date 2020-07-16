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
        $this->assertTrue(is_numeric(strpos($return, '"invalidPath"')));
        $this->assertTrue(is_numeric(strpos($return, 'ERRO')));
    }

    public function testInvalidFile()
    {
        $this->mockJournal();
        $this->mockUser();

        $return = $this->executeCLI('scielo', ['import', 'file.xml', 'invalidFile']);
        $this->assertTrue(is_numeric(strpos($return, 'file.xml')));
        $this->assertTrue(is_numeric(strpos($return, 'ERRO')));
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
        HookRegistry::clear('ScieloArticleFilter::saveSubmission');
        HookRegistry::register('ScieloArticleFilter::saveSubmission', function($hookName, $args) {
            $locale = $args[0]->getData('language');
            $this->assertEquals('en_US', $locale);
            $this->assertEquals('en_US', $args[0]->getData('locale'));
            $this->assertEquals('English Title <i>italic</i>', $args[0]->getData('title', $locale));
            $this->assertEquals('<i>pt_BR Title</i>', $args[0]->getData('title', 'pt_BR'));
            $this->assertEquals('<i>es Title</i>', $args[0]->getData('title', 'es_ES'));
            $this->assertEquals('<p>Abstract en.</p>', $args[0]->getData('abstract', $locale));
            $this->assertEquals('<p>Trans Abstract pt.</p>', $args[0]->getData('abstract', 'pt_BR'));
            $this->assertEquals('<p>Trans Abstract es.</p>', $args[0]->getData('abstract', 'es_ES'));
            $this->assertEquals('2016-08-31 00:00:00', $args[0]->getData('date_submitted'));
            $this->assertEquals(1, $args[0]->getData('context_id'));
            $this->assertEquals('10.2495/0102-311X00785447', $args[0]->getData('DOI'));
            $this->assertEquals('http://creativecommons.org/licenses/by/4.0/', $args[0]->getData('license_url'));
            $this->assertEquals(STATUS_QUEUED, $args[0]->getData('status'));
            $this->assertEquals(WORKFLOW_STAGE_ID_PRODUCTION, $args[0]->getData('stage_id'));
            $args[0]->setId(1);
            return true;
        });
        HookRegistry::clear('ScieloArticleFilter::saveAuthors');
        HookRegistry::register('ScieloArticleFilter::saveAuthors', function($hookName, $args) {
            $locale = $args[0]->getSubmissionLocale();
            $this->assertEquals('en_US', $locale);
            $this->assertEquals('Brazil', $args[0]->getCountry());
            $this->assertEquals('BR', $args[0]->getData('countryCode'));
            $this->assertEquals('Jhon', $args[0]->getGivenName($locale));
            $this->assertEquals('Doe', $args[0]->getFamilyName($locale));
            $this->assertEquals(14, $args[0]->getUserGroupId());
            $this->assertContains($args[0]->getUserGroupId(), [14]);
            $this->assertEquals(1, $args[0]->getIncludeInBrowse());
            $this->assertEquals(1, $args[0]->getSubmissionId());
            $this->assertEquals('jhondoe@email.com', $args[0]->getEmail());
            $this->assertEquals('Sector, University Name, City, Country.', $args[0]->getData('institution-original'));
            $this->assertEquals('University Name', $args[0]->getData('institution-normalized'));
            $this->assertEquals('Sector 2', $args[0]->getData('institution-orgdiv1'));
            $this->assertEquals('University Name', $args[0]->getData('institution-orgname'));
            return true;
        });
        HookRegistry::clear('authordao::getAdditionalFieldNames');
        HookRegistry::register('authordao::getAdditionalFieldNames', function($hookName, $args) {
            $args[1][] = 'suffix';
        });
        HookRegistry::clear('pluginsettingsdao::_getpluginsettings');
        HookRegistry::register('pluginsettingsdao::_getpluginsettings', function($hookName, $args) {
            $args[2] = new ADORecordSet_array();
            $args[2]->_numOfRows = 2;
            $args[2]->_currentRow= 0;
            $args[2]->fields = $args[2]->bind = [
                'setting_name' => 'defaultAuthorEmail',
                'setting_value' => 'jhondoe@localhost.fake',
                'setting_type' => 'string'
            ];
            $args[2]->_array = [
                $args[2]->fields,
                [
                    'setting_name' => 'defaultLocale',
                    'setting_value' => 'pt_BR',
                    'setting_type' => 'string'
                ]
            ];
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