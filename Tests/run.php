<?php

define('APP_PATH', dirname(__DIR__) . '/mademonstrator.php');
define('APP_NAMESPACE', 'MADEMO');

require_once dirname(__DIR__) . '/SPTK/Autoload.php';
require_once __DIR__ . '/Support.php';

$files = [
  __DIR__ . '/SlideMarkdownTest.php',
  __DIR__ . '/SlideThemeTest.php',
];

$tests = [];
foreach ($files as $file) {
  $tests += require $file;
}

$passed = 0;
$failed = 0;
foreach ($tests as $name => $test) {
  try {
    $test();
    $passed++;
    echo ". {$name}\n";
  } catch (\Throwable $e) {
    $failed++;
    echo "F {$name}\n";
    echo "  " . str_replace("\n", "\n  ", $e->getMessage()) . "\n";
  }
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
