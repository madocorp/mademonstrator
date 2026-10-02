<?php

namespace MADEMO\Tests;

use MADEMO\App\SlideMarkdown;

return [
  'time frame comments set slide seconds without appearing in notes' => function(): void {
    $minute = SlideMarkdown::fromMarkdown(['## Timed', '<!-- TimeFrame: 2min -->', '<!-- Remember this -->'], getcwd());
    $second = SlideMarkdown::fromMarkdown(['## Timed', '<!--', 'TimeFrame: 45sec', 'A useful note', '-->'], getcwd());
    $shortSecond = SlideMarkdown::fromMarkdown(['## Timed', '<!-- TimeFrame: 10s -->'], getcwd());
    $shortMinute = SlideMarkdown::fromMarkdown(['## Timed', '<!-- TimeFrame: 2m -->'], getcwd());
    $default = SlideMarkdown::fromMarkdown(['## Untimed', '<!-- Just a note -->'], getcwd());
    assertSame(120, $minute['timeFrame'], 'Minute time frames should become seconds.');
    assertSame('Remember this', $minute['promptText'], 'Time frame metadata should not appear in notes.');
    assertSame(45, $second['timeFrame'], 'Second time frames should become seconds.');
    assertSame(10, $shortSecond['timeFrame'], 'Short second units should become seconds.');
    assertSame(120, $shortMinute['timeFrame'], 'Short minute units should become seconds.');
    assertSame('A useful note', $second['promptText'], 'Notes beside metadata should remain visible.');
    assertSame(null, $default['timeFrame'], 'Slides without a frame should use the presentation default.');
  },
  'slide markdown separates main and normal titles' => function(): void {
    $main = SlideMarkdown::fromMarkdown(['# Main', '', '> Quote'], getcwd())['slide'];
    $normal = SlideMarkdown::fromMarkdown(['## Slide', '', 'Text'], getcwd())['slide'];
    assertSame('main', $main['type'], 'Heading level 1 should create a main slide.');
    assertSame('normal', $normal['type'], 'Heading level 2 should create a normal slide.');
    assertSame('Main', $main['title'][0]['text'], 'Main title should be stored as runs.');
    assertSame('Slide', $normal['title'][0]['text'], 'Slide title should be stored as runs.');
  },

  'slide markdown maps h3 h4 h5 to box widths' => function(): void {
    $slide = SlideMarkdown::fromMarkdown([
      '## Boxes',
      '### Half',
      'A',
      '#### Third',
      'B',
      '##### Quarter',
      'C',
    ], getcwd())['slide'];
    assertSame('half', $slide['elements'][0]['size'], 'h3 should create a half-width box.');
    assertSame('third', $slide['elements'][1]['size'], 'h4 should create a third-width box.');
    assertSame('quarter', $slide['elements'][2]['size'], 'h5 should create a quarter-width box.');
  },

  'slide markdown keeps h6 inside current box' => function(): void {
    $slide = SlideMarkdown::fromMarkdown([
      '## Boxes',
      '### Box',
      'Before',
      '###### Subtitle',
      'After',
    ], getcwd())['slide'];
    $box = $slide['elements'][0];
    assertSame('box', $box['type'], 'h3 should start a box.');
    assertSame('body', $box['items'][0]['role'], 'Text before h6 should stay in the box.');
    assertSame('subtitle', $box['items'][1]['role'], 'h6 should become a subtitle inside the box.');
    assertSame('body', $box['items'][2]['role'], 'Text after h6 should continue in the same box.');
  },

  'slide markdown keeps links and code as inline runs' => function(): void {
    $result = SlideMarkdown::fromMarkdown(['## Links', 'Use **bold**, `code`, and [PHP](https://php.net).'], getcwd());
    $runs = $result['slide']['elements'][0]['runs'];
    assertSame('strong', $runs[1]['role'], 'Bold markdown should become a strong run.');
    assertSame('code', $runs[3]['role'], 'Inline code should become a code run.');
    assertSame('link', $runs[5]['role'], 'Markdown links should become link runs.');
    assertSame('https://php.net', $result['links'][0], 'Link target should be indexed for keyboard navigation.');
  },

  'slide markdown applies bullet image definitions to later unordered lists' => function(): void {
    $result = SlideMarkdown::fromMarkdown([
      '## Bullets',
      '![:bullet](Assets/green_arrow.png)',
      '* First',
      '* Second',
    ], dirname(__DIR__));
    $elements = $result['slide']['elements'];
    assertSame(1, count($elements), 'Bullet image definition should not render as a standalone image.');
    assertSame('list', $elements[0]['type'], 'Unordered list after bullet definition should become an image-bullet list.');
    assertSame(false, $elements[0]['ordered'], 'Bullet image list should stay unordered.');
    assertSame('First', $elements[0]['items'][0]['runs'][0]['text'], 'List item text should be kept as runs.');
    assertTrue(str_ends_with($elements[0]['items'][0]['bullet'], 'Assets/green_arrow.png'), 'List item should carry the resolved bullet image.');
  },

  'slide markdown keeps ordinary lists as structural list elements' => function(): void {
    $unordered = SlideMarkdown::fromMarkdown(['## List', '* One', '* Two'], getcwd())['slide']['elements'][0];
    $ordered = SlideMarkdown::fromMarkdown(['## List', '1. One', '2. Two'], getcwd())['slide']['elements'][0];
    assertSame('list', $unordered['type'], 'Unordered lists should become structural list elements.');
    assertSame(false, $unordered['ordered'], 'Unordered lists should keep ordered=false.');
    assertSame('*', $unordered['items'][0]['marker'], 'Unordered lists should keep a text marker when no image bullet is defined.');
    assertSame('list', $ordered['type'], 'Ordered lists should become structural list elements.');
    assertSame(true, $ordered['ordered'], 'Ordered lists should keep ordered=true.');
    assertSame('1.', $ordered['items'][0]['marker'], 'Ordered lists should keep numeric markers.');
  },

  'slide markdown keeps images inside heading boxes' => function(): void {
    $result = SlideMarkdown::fromMarkdown([
      '## Image box',
      '### Box',
      'Before',
      '![Logo](Assets/mademo.png)',
      'After',
    ], dirname(__DIR__));
    $box = $result['slide']['elements'][0];
    assertSame('box', $box['type'], 'h3 should create a box.');
    assertSame('body', $box['items'][0]['role'], 'Text before image should stay in the box.');
    assertSame('image', $box['items'][1]['type'], 'Image after h3 should stay in the box.');
    assertSame('body', $box['items'][2]['role'], 'Text after image should stay in the box.');
  },

  'slide markdown parses absolute images as slide level elements' => function(): void {
    $result = SlideMarkdown::fromMarkdown([
      '## Absolute',
      '### Box',
      '![:absolute:20%x30%-80%-0](Assets/mademo.png)',
      'Inside box',
    ], dirname(__DIR__));
    $elements = $result['slide']['elements'];
    assertSame('image', $elements[0]['type'], 'Absolute image should be stored as an image element.');
    assertSame('absolute', $elements[0]['position'], 'Absolute image should carry its positioning mode.');
    assertSame(['width' => '20%', 'height' => '30%', 'x' => '80%', 'y' => '0'], $elements[0]['rect'], 'Absolute image should keep its rectangle specification.');
    assertSame('box', $elements[1]['type'], 'Absolute image should not become part of the open heading box.');
  },
];
