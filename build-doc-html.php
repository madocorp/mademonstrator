#!/usr/bin/env php
<?php

define('APP_PATH', __FILE__);
define('APP_NAMESPACE', 'MADEMO');

require_once __DIR__ . '/SPTK/Autoload.php';

use MADEMO\App\Presentation;
use MADEMO\App\SlideHtml;

$source = $argv[1] ?? (__DIR__ . '/Layout/doc.md');
$target = $argv[2] ?? (__DIR__ . '/Layout/doc.html');
$sourcePath = realpath($source);

if ($sourcePath === false || !is_file($sourcePath)) {
  fwrite(STDERR, "Markdown file not found: {$source}\n");
  exit(1);
}

$presentation = new Presentation($sourcePath);
$slides = [];
for ($index = 0; $index < $presentation->count(); $index++) {
  $slide = SlideHtml::fromMarkdown($presentation->code($index), dirname($sourcePath));
  $slides[] = $slide['html'];
}

$title = htmlspecialchars(basename($sourcePath), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$stylePath = '../Styles/Default.css';
$html = "<!doctype html>\n"
  . "<html lang=\"en\">\n"
  . "<head>\n"
  . "  <meta charset=\"utf-8\">\n"
  . "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
  . "  <title>{$title}</title>\n"
  . "  <link rel=\"stylesheet\" href=\"{$stylePath}\">\n"
  . "</head>\n"
  . "<body>\n"
  . implode("\n\n", $slides)
  . "\n</body>\n"
  . "</html>\n";

if (file_put_contents($target, $html, LOCK_EX) === false) {
  fwrite(STDERR, "Failed to write HTML file: {$target}\n");
  exit(1);
}

fwrite(STDOUT, "Wrote {$target} with " . count($slides) . " slides.\n");
