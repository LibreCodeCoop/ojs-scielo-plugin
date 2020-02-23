<?php

require_once './lib/pkp/tests/phpunit-bootstrap.php';

import('lib.pkp.classes.core.PKPRequest');
import('lib.pkp.classes.core.PKPRouter');
import('lib.pkp.classes.core.Registry');
$request = new \PKPRequest();
$router = new \PKPRouter();
$router->setApplication(\Application::getApplication());
$request->setRouter($router);
\Registry::set('request', $request);
\AppLocale::$request = \Registry::get('request', true, null);