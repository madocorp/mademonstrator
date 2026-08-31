#!/usr/bin/env php
<?php

define('APP_PATH', __FILE__);
define('APP_NAMESPACE', 'MADEMO');

require_once __DIR__ . '/SPTK/Autoload.php';

use MADEMO\App\Controller;
use SPTK\Runtime\SdlApp;
use SPTK\Runtime\SdlWindowOptions;

$app = new SdlApp('MaDemonstrator');
$controller = new Controller(__DIR__, $app);

$windowOptions = new SdlWindowOptions;
$windowOptions->title = 'MaDemontstrator';
$windowOptions->maximized = true;
$window = $app->addWindow($controller->build(), $windowOptions);
$controller->setWindow($window);
$app->run();
