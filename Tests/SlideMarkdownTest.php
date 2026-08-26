<?php

namespace MADEMO\Tests;

use MADEMO\App\SlideMarkdown;

return [
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
];
