<?php

namespace MADEMO\Tests;

use MADEMO\App\{Presentation, SlideLayout, SlideMarkdown, SlideText, SlideTheme};
use SPTK\Layout\{LayoutLeaf, LayoutNode, Tile};
use SPTK\Widgets\TextEditor\TextEditor;
use SPTK\Widgets\Empty\Placeholder;

/** Verify four empty slide edges convert their percentages to cells on each measurement. */
function slidePaddingCells(): void {
  $slide = ['title' => [['text' => 'Padding']], 'elements' => []];
  foreach ([
    ['Default', 100, 50, [5, 3, 3, 5]],
    ['BrightMinimal', 100, 50, [10, 2, 4, 6]],
    ['Default', 80, 40, [4, 2, 2, 4]],
  ] as [$theme, $width, $height, $expected]) {
    $layout = (new SlideLayout($theme))->build($slide);
    $layout->measureGrid(new Tile(0, 0, $width, $height));
    $leaves = $layout->leaves();
    assertTrue($leaves[0]->instance() instanceof Placeholder, 'Left padding uses an Empty widget.');
    assertTrue($leaves[1]->instance() instanceof Placeholder, 'Top padding uses an Empty widget.');
    assertTrue($leaves[count($leaves) - 2]->instance() instanceof Placeholder, 'Bottom padding uses an Empty widget.');
    assertTrue($leaves[count($leaves) - 1]->instance() instanceof Placeholder, 'Right padding uses an Empty widget.');
    assertSame($expected, [
      $leaves[0]->grid()->width,
      $leaves[1]->grid()->height,
      $leaves[count($leaves) - 2]->grid()->height,
      $leaves[count($leaves) - 1]->grid()->width,
    ], 'Slide percentages should resolve against the full slide dimensions.');
  }
}

/** Verify H1 slides measure separate content tiles between flexible spacers. */
function mainSlideTiles(): void {
  $parsed = SlideMarkdown::fromMarkdown(['# Main title', '> A quote', '', 'An attribution.'], APP_DIR);
  $layout = (new SlideLayout('Default'))->build($parsed['slide']);
  $layout->measureGrid(new Tile(0, 0, 120, 60));
  $leaves = $layout->leaves();
  assertSame(9, count($leaves), 'H1 slide should have four edge tiles and five content tiles.');
  assertTrue($leaves[2]->instance() instanceof Placeholder, 'First content tile is empty.');
  assertTrue($leaves[3]->instance() instanceof SlideText && $leaves[4]->instance() instanceof SlideText && $leaves[5]->instance() instanceof SlideText, 'Title, quote, and attribution have separate StyledText tiles.');
  assertTrue($leaves[6]->instance() instanceof Placeholder, 'Last content tile is empty.');
  assertSame('A quote', $leaves[4]->instance()->sourceRuns()[0]['text'], 'Quote has its own tile.');
  assertSame('An attribution.', $leaves[5]->instance()->sourceRuns()[0]['text'], 'Attribution has its own tile.');
  $font = \SPTK\App::fontOrNull();
  $cellWidth = $font?->cellWidth() ?? 8;
  $cellHeight = $font?->cellHeight() ?? 16;
  foreach ([3, 4, 5] as $index) {
    $leaf = $leaves[$index];
    $expected = max(1, (int)ceil($leaf->instance()->contentHeight($leaf->grid()->width * $cellWidth) / $cellHeight));
    assertSame($expected, $leaf->grid()->height, 'H1 text tiles use their natural heights.');
  }
  assertTrue(abs($leaves[2]->grid()->height - $leaves[6]->grid()->height) <= 1, 'Flexible spacers balance the title slide.');
}

/** Keep H1 content roles and their complete styles identical to H2 content. */
function mainSlideContentStyles(): void {
  $elements = ['> A quote', '', '```', 'echo "code";', '```', '', 'A paragraph.'];
  $styles = [];
  foreach (['# Main title', '## Normal title'] as $title) {
    $parsed = SlideMarkdown::fromMarkdown([$title, ...$elements], APP_DIR);
    $layout = (new SlideLayout('Default'))->build($parsed['slide']);
    $layout->measureGrid(new Tile(0, 0, 120, 60));
    $texts = array_values(array_filter($layout->leaves(), __NAMESPACE__ . '\\isSlideText'));
    assertSame(4, count($texts), 'Each content block has its own tile on both slide types.');
    $styles[$title] = [];
    foreach (array_slice($texts, 1) as $leaf) {
      $content = (new \ReflectionMethod(SlideText::class, 'content'))->invoke($leaf->instance(), $leaf->grid()->width * 8, $leaf->grid()->height * 16);
      $styles[$title][] = $content[1];
    }
  }
  assertSame($styles['## Normal title'], $styles['# Main title'], 'H1 quotes, code, and paragraphs use the same complete styles as H2.');
  assertSame('#101010', $styles['# Main title'][0]['background'], 'H1 quote keeps its themed background.');
  assertSame(4, $styles['# Main title'][0]['borderWidth']['left'], 'H1 quote keeps its themed border.');
}

/** Verify normal slide text heights respond to width and leave space to flexible gaps. */
function normalSlideIntrinsicHeight(): void {
  $lines = ['## A normal slide', str_repeat('A longer sentence that should wrap at narrower widths. ', 4)];
  $parsed = SlideMarkdown::fromMarkdown($lines, APP_DIR);
  $layout = (new SlideLayout('Default'))->build($parsed['slide']);
  $font = \SPTK\App::fontOrNull();
  $cellWidth = $font?->cellWidth() ?? 8;
  $cellHeight = $font?->cellHeight() ?? 16;
  $layout->measureGrid(new Tile(0, 0, 120, 100));
  $leaves = $layout->leaves();
  assertTrue($leaves[2]->instance() instanceof SlideText && $leaves[4]->instance() instanceof SlideText, 'Title and body use text tiles.');
  foreach ([2, 4] as $index) {
    $leaf = $leaves[$index];
    $expected = max(1, (int)ceil($leaf->instance()->contentHeight($leaf->grid()->width * $cellWidth) / $cellHeight));
    assertSame($expected, $leaf->grid()->height, 'Text tile height should fit its measured content.');
  }
  assertTrue(abs($leaves[5]->grid()->height - 2 * $leaves[3]->grid()->height) <= 1, 'Remaining space uses a 1:2 gap ratio.');
  $wideBodyHeight = $leaves[4]->grid()->height;
  $layout->measureGrid(new Tile(0, 0, 55, 100));
  assertTrue($leaves[4]->grid()->height > $wideBodyHeight, 'Narrower slides wrap text into more cells.');
  $layout->measureGrid(new Tile(0, 0, 55, 5));
  assertSame(0, $leaves[3]->grid()->height, 'First flexible gap collapses when content is tall.');
  assertSame(0, $leaves[5]->grid()->height, 'Second flexible gap collapses when content is tall.');
}

/** Verify an auto-sized content layout sums several independently measured text tiles. */
function normalSlideMultipleParagraphs(): void {
  $parsed = SlideMarkdown::fromMarkdown(['## Separate paragraphs', 'First paragraph.', '', 'Second paragraph.'], APP_DIR);
  $layout = (new SlideLayout('Default'))->build($parsed['slide']);
  $layout->measureGrid(new Tile(0, 0, 120, 80));
  $leaves = $layout->leaves();
  assertTrue($leaves[4]->instance() instanceof SlideText && $leaves[5]->instance() instanceof SlideText, 'Each paragraph keeps its own StyledText tile.');
  $font = \SPTK\App::fontOrNull();
  $cellWidth = $font?->cellWidth() ?? 8;
  $cellHeight = $font?->cellHeight() ?? 16;
  foreach ([4, 5] as $index) {
    $leaf = $leaves[$index];
    assertSame(max(1, (int)ceil($leaf->instance()->contentHeight($leaf->grid()->width * $cellWidth) / $cellHeight)), $leaf->grid()->height, 'Each paragraph uses its measured height.');
  }
  assertSame($leaves[4]->grid()->y + $leaves[4]->grid()->height + 1, $leaves[5]->grid()->y, 'Paragraph tiles follow each other with one layout gap.');
}

/** Keep custom image-bullet text visible at presentation and preview sizes. */
function imageBulletTextFits(): void {
  $parsed = SlideMarkdown::fromMarkdown([
    '## Image bullets', '![:bullet](green_arrow.png)',
    'A paragraph before the list.', '* First bullet', '* Second bullet',
    '```', 'A code block after the list.', '```',
  ], APP_DIR . '/Assets');
  $font = \SPTK\App::fontOrNull();
  $cellWidth = $font?->cellWidth() ?? 8;
  $cellHeight = $font?->cellHeight() ?? 16;
  foreach (['Default', 'BrightAcademic'] as $theme) {
    foreach ([[110, 40], [66, 18]] as [$width, $height]) {
      $layout = (new SlideLayout($theme))->build($parsed['slide']);
      $layout->measureGrid(new Tile(0, 0, $width, $height));
      foreach ($layout->leaves() as $leaf) {
        if ($leaf->instance() instanceof SlideText) {
          assertTrue($leaf->instance()->pixelPadding(), 'Text renders inside the same cells used for measurement.');
          assertTrue($leaf->instance()->contentHeight(max(1, $leaf->grid()->width * $cellWidth)) <= $leaf->grid()->height * $cellHeight, 'Image-bullet slide text fits its tile.');
        }
      }
    }
  }
}

/** Keep each list item in its own row, with text wrapping beside the marker. */
function ordinaryListItemTiles(): void {
  foreach ([['* First item', '* Second **strong** item'], ['1. First item', '2. Second **strong** item']] as $items) {
    $parsed = SlideMarkdown::fromMarkdown(['## List items', ...$items], APP_DIR);
    $layout = (new SlideLayout('Default'))->build($parsed['slide']);
    $layout->measureGrid(new Tile(0, 0, 100, 40));
    $texts = array_values(array_filter($layout->leaves(), __NAMESPACE__ . '\\isSlideText'));
    assertSame(5, count($texts), 'Slide title and both marker/text pairs have separate tiles.');
    assertSame(strtok($items[0], ' '), $texts[1]->instance()->sourceRuns()[0]['text'], 'First item keeps its marker.');
    assertSame(strtok($items[1], ' '), $texts[3]->instance()->sourceRuns()[0]['text'], 'Second item keeps its marker.');
    assertTrue($texts[2]->grid()->x > $texts[1]->grid()->x, 'Item text starts after its marker.');
    assertSame($texts[1]->grid()->y, $texts[2]->grid()->y, 'Marker and text share a row.');
    assertTrue($texts[4]->grid()->y >= $texts[2]->grid()->y + $texts[2]->grid()->height, 'Second item starts below the first row.');
    assertSame('strong', $texts[4]->instance()->sourceRuns()[1]['role'], 'Inline formatting stays on its list item.');
    $targets = $layout->movementLeaves();
    assertSame(3, count($targets), 'Each list row is one navigation target alongside the slide title.');
    assertSame([$texts[1], $texts[2]], $layout->focusedLeaves($targets[1]), 'Selecting the first item highlights its marker and text together.');
    assertSame([$texts[3], $texts[4]], $layout->focusedLeaves($targets[2]), 'Selecting the second item highlights its marker and text together.');
    $cellWidth = \SPTK\App::fontOrNull()?->cellWidth() ?? 8;
    foreach ([$texts[1], $texts[3]] as $marker) {
      assertSame($marker->instance()->contentHeight(1000), $marker->instance()->contentHeight($marker->grid()->width * $cellWidth), 'Bullet or number fits on one line.');
    }
  }
  $parsed = SlideMarkdown::fromMarkdown(['## Wrapped bullet', '* ' . str_repeat('Several words wrap beside the marker. ', 8)], APP_DIR);
  $layout = (new SlideLayout('Default'))->build($parsed['slide']);
  $layout->measureGrid(new Tile(0, 0, 55, 50));
  $texts = array_values(array_filter($layout->leaves(), __NAMESPACE__ . '\\isSlideText'));
  $cellWidth = \SPTK\App::fontOrNull()?->cellWidth() ?? 8;
  assertTrue($texts[2]->instance()->contentHeight($texts[2]->grid()->width * $cellWidth) > $texts[1]->instance()->contentHeight($texts[1]->grid()->width * $cellWidth), 'Item text wraps beyond its marker.');
  assertSame($texts[1]->grid()->height, $texts[2]->grid()->height, 'Marker and text share the same row height.');
  assertTrue($texts[2]->grid()->x > $texts[1]->grid()->x, 'All wrapped lines use the text column.');
  foreach (['* First', '* Second', '1. First', '2. Second'] as $line) {
    $parsed = SlideMarkdown::fromMarkdown(['## Marker width', '##### Box', $line], APP_DIR);
    $layout = (new SlideLayout('Default'))->build($parsed['slide']);
    $layout->measureGrid(new Tile(0, 0, 120, 70));
    $markers = array_values(array_filter($layout->leaves(), __NAMESPACE__ . '\\isSlideText'));
    $marker = $markers[2];
    assertSame($marker->instance()->contentHeight(1000), $marker->instance()->contentHeight($marker->grid()->width * $cellWidth), 'Marker stays on one line inside a box.');
  }
}

/** Treat an image bullet and its text as one highlighted list item. */
function imageBulletRowSelection(): void {
  $parsed = SlideMarkdown::fromMarkdown([
    '## Image bullet selection', '![:bullet](green_arrow.png)', '* First item', '* Second item',
  ], APP_DIR . '/Assets');
  $layout = (new SlideLayout('Default'))->build($parsed['slide']);
  $layout->measureGrid(new Tile(0, 0, 100, 40));
  $targets = $layout->movementLeaves();
  assertSame(3, count($targets), 'Each image-bullet row is one navigation target alongside the slide title.');
  foreach (array_slice($targets, 1) as $target) {
    $children = $layout->focusedLeaves($target);
    assertSame(2, count($children), 'The image bullet and text highlight together.');
    assertTrue($children[0]->instance() instanceof \MADEMO\App\SlideImage, 'The first child is the bullet image.');
    assertTrue($children[1]->instance() instanceof SlideText, 'The second child is the item text.');
  }
}

/** Verify native box fractions, skipped absolute images, and themed rich text rendering. */
function slideNativeLayout(): void {
  $parsed = SlideMarkdown::fromMarkdown([
    '## Native slide', '#### First', 'Text **bold**.', '#### Second', '* One', '* Two',
    '#### Third', '> Quote', '![:absolute:10%x10%-0-0](missing.png)',
  ], APP_DIR);
  $layout = (new SlideLayout('Default'))->build($parsed['slide']);
  $layout->measureGrid(new Tile(0, 0, 120, 45));
  foreach ($layout->leaves() as $leaf) {
    assertTrue(!$leaf->instance() instanceof \SPTK\Widgets\Canvas\Canvas, 'Slide must not contain Canvas.');
    assertTrue(!$leaf->instance() instanceof \SPTK\Widgets\Image\Image, 'Absolute image must be omitted.');
    assertTrue(!$leaf->instance() instanceof \MADEMO\App\SlideImage, 'Absolute image must not produce a slide image tile.');
  }
  $texts = array_values(array_filter($layout->leaves(), __NAMESPACE__ . '\\isSlideText'));
  assertSame(10, count($texts), 'Title, three box headings, two marker/text pairs, and two other bodies use separate native text tiles.');
  assertTrue(abs($texts[1]->grid()->width - $texts[3]->grid()->width) <= 1, 'Third-width boxes differ by at most one grid cell.');
  assertTrue($texts[1]->grid()->x < $texts[3]->grid()->x && $texts[3]->grid()->x < $texts[8]->grid()->x, 'Boxes occupy a horizontal row.');
  assertTrue($texts[3]->grid()->x - ($texts[1]->grid()->x + $texts[1]->grid()->width) >= 3, 'Adjacent boxes have a visible horizontal margin.');
  $image = $texts[0]->instance()->raster(800, 80);
  assertSame([800, 80], [$image->width, $image->height], 'Slide text renders to its own tile.');
  assertTrue($texts[0]->instance()->raster(800, 80) === $image, 'Unchanged text retains its raster.');
  $layout->measureGrid(new Tile(0, 0, 90, 30));
  assertTrue($texts[0]->instance()->raster(800, 80) !== $image, 'Slide reference resize invalidates typography.');
}

/** Keep wrapped box text within its measured row after reserving outer margins. */
function slideBoxContentFits(): void {
  $parsed = SlideMarkdown::fromMarkdown([
    '## Box sizing',
    '### Longer box',
    str_repeat('This sentence wraps across several lines in a half-width box. ', 3),
    '### Short box',
    'Short text.',
  ], APP_DIR);
  $font = \SPTK\App::fontOrNull();
  $cellWidth = $font?->cellWidth() ?? 8;
  $cellHeight = $font?->cellHeight() ?? 16;
  foreach ([[110, 40], [80, 30]] as [$width, $height]) {
    $layout = (new SlideLayout('Default'))->build($parsed['slide']);
    $layout->measureGrid(new Tile(0, 0, $width, $height));
    $leaves = $layout->leaves();
    $texts = array_values(array_filter($leaves, __NAMESPACE__ . '\\isSlideText'));
    foreach (array_slice($texts, 1) as $leaf) {
      assertTrue($leaf->instance()->contentHeight(max(1, $leaf->grid()->width * $cellWidth)) <= $leaf->grid()->height * $cellHeight, 'Box heading and body text fit their tiles.');
    }
    $longFill = $leaves[array_search($texts[2], $leaves, true) + 1];
    $shortFill = $leaves[array_search($texts[4], $leaves, true) + 1];
    assertTrue($longFill->instance() instanceof Placeholder && $shortFill->instance() instanceof Placeholder, 'Each box has an empty filler below its content.');
    assertSame(0, $longFill->grid()->height, 'The tallest box has no filler above its bottom margin.');
    assertTrue($shortFill->grid()->height > 0, 'The shorter box fills the extra row height.');
    assertSame($longFill->grid()->y + $longFill->grid()->height, $shortFill->grid()->y + $shortFill->grid()->height, 'Both box fillers end at the same height.');
  }
}

/** Identify slide text leaves without an unnamed callback. */
function isSlideText(LayoutLeaf $leaf): bool {
  return $leaf->instance() instanceof SlideText && $leaf->instance()->sourceRuns() !== [];
}

/** Render all bundled Markdown elements in all themes at presentation and preview grid sizes. */
function slideBundledThemes(): void {
  $document = new Presentation(APP_DIR . '/Assets/doc.md');
  foreach (SlideTheme::names() as $theme) {
    for ($index = 0; $index < $document->count(); $index++) {
      foreach ([[110, 40], [66, 18], [1, 1]] as [$columns, $rows]) {
        $layout = (new SlideLayout($theme))->build($document->slide($index)['slide']);
        $layout->measureGrid(new Tile(0, 0, $columns, $rows));
        foreach ($layout->leaves() as $leaf) {
          if ($leaf->instance() instanceof SlideText && $leaf->grid()->width > 0 && $leaf->grid()->height > 0) {
            $leaf->instance()->raster($leaf->grid()->width * 8, $leaf->grid()->height * 16);
          }
        }
      }
    }
  }
}

/** Verify replacing a preview subtree keeps the same active editor and its text buffer. */
function slideRetainsEditor(): void {
  $editor = new TextEditor('Original');
  $editor->setId('editor');
  $leaf = new LayoutLeaf('TextEditor', '1*', '1*', $editor);
  $preview = new LayoutNode('vertical', '1*', '1*');
  $root = new LayoutNode('vertical', '', '');
  $root->addLeaf($leaf);
  $root->addNode($preview);
  $screen = new \SPTK\Core\Screen($root);
  $input = new \stdClass();
  $input->type = \SPTK\SDLWrapper\SDL::SDL_EVENT_KEY_DOWN;
  $input->key = (object)['key' => \SPTK\SDLWrapper\SDL::KEY_RETURN, 'mod' => 0];
  $screen->handleEvent($input);
  assertTrue($editor->editing(), 'Editor is activated.');
  $replacement = new LayoutNode('vertical', '1*', '1*');
  assertTrue($root->replaceChild($preview, $replacement), 'Preview subtree can be replaced.');
  $screen->setLayout($root);
  assertTrue($screen->activeLeaf()->instance() === $editor, 'Editor remains the active leaf.');
  assertSame('Original', $editor->getValue(), 'Editor buffer survives layout changes.');
  $removed = new LayoutNode('vertical', '', '');
  $screen->setLayout($removed);
  assertSame(false, $editor->editing(), 'Removed active widget is released.');
}

return [
  'Slide padding resolves to cells' => __NAMESPACE__ . '\\slidePaddingCells',
  'H1 slide text uses intrinsic heights' => __NAMESPACE__ . '\\mainSlideTiles',
  'H1 content keeps normal slide styles' => __NAMESPACE__ . '\\mainSlideContentStyles',
  'Normal slide text uses intrinsic heights' => __NAMESPACE__ . '\\normalSlideIntrinsicHeight',
  'Normal slide paragraphs use intrinsic heights' => __NAMESPACE__ . '\\normalSlideMultipleParagraphs',
  'Image bullet text fits its tiles' => __NAMESPACE__ . '\\imageBulletTextFits',
  'Ordinary list items use separate tiles' => __NAMESPACE__ . '\\ordinaryListItemTiles',
  'Image bullet rows select together' => __NAMESPACE__ . '\\imageBulletRowSelection',
  'Native slide layouts' => __NAMESPACE__ . '\\slideNativeLayout',
  'Box text fits inside its row' => __NAMESPACE__ . '\\slideBoxContentFits',
  'Bundled slides across all themes and sizes' => __NAMESPACE__ . '\\slideBundledThemes',
  'Preview replacement retains active editor' => __NAMESPACE__ . '\\slideRetainsEditor',
];
