#!/usr/bin/env php
<?php

define('APP_PATH', __FILE__);

$appDir = __DIR__;
$sptkDir = $appDir . '/SPTK';
if (!is_file($sptkDir . '/Autoload.php')) {
  fwrite(STDERR, "SPTK symlink not found next to mademonstrator.\n");
  exit(1);
}

require_once $sptkDir . '/Autoload.php';

spl_autoload_register(function(string $class) use ($appDir): void {
  if (!str_starts_with($class, 'MADEMO\\App\\')) {
    return;
  }
  $relative = substr($class, strlen('MADEMO\\App\\'));
  $path = $appDir . '/App/' . str_replace('\\', '/', $relative) . '.php';
  if (is_file($path)) {
    require_once $path;
  }
});

use MADEMO\App\Controller;
use SPTK2\Runtime\SdlApp;
use SPTK2\Runtime\SdlWindowOptions;

cli_set_process_title('MADEMO');

$app = new SdlApp();
$controller = new Controller($appDir, $app);
$window = $app->addWindow($controller->build(), new SdlWindowOptions(
  title: 'MaDemonstrator',
  columns: 120,
  rows: 38
));
$controller->setWindow($window);
$app->run();
