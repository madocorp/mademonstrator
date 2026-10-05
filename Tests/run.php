<?php

define('APP_DIR', dirname(__DIR__));
define('APP_NAMESPACE', 'MADEMO');
define('APP_PATH', APP_DIR . '/mademonstrator.php');
require_once APP_DIR . '/SPTK/App.php';
require_once __DIR__ . '/Support.php';
$tests = [];
foreach (['SlideMarkdownTest', 'SlideThemeTest', 'PresentationTest', 'PresentationTimingTest', 'WindowPreferencesTest', 'SlideLayoutTest'] as $file) {
  $tests += require __DIR__ . '/' . $file . '.php';
}
$failed = 0;
foreach ($tests as $name => $test) {
  try {
    $test();
    echo ". {$name}\n";
  } catch (Throwable $error) {
    $failed++;
    echo "F {$name}: {$error->getMessage()}\n";
  }
}
echo count($tests) - $failed . ' passed, ' . $failed . " failed\n";
exit($failed === 0 ? 0 : 1);
