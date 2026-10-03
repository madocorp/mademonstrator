<?php

namespace MADEMO\Tests;

use MADEMO\App\{Presentation, SlideLayout, SlideMarkdown, SlideText, SlideTheme};
use SPTK\Layout\{LayoutLeaf, LayoutNode, PixelBox, PixelSplitter, Tile, WindowGeometry};
use SPTK\Widgets\Empty\Placeholder;
use SPTK\Widgets\TextEditor\TextEditor;

/** Measure the grid boundary and then its exact pixel slide subtree. */
function measureSlide(LayoutNode $layout, int $columns, int $rows): void {
  $grid = new Tile(0, 0, $columns, $rows);
  $layout->measureGrid($grid);
  $layout->measureArea($grid, new WindowGeometry(8, 16, $columns * 8, $rows * 16, 0, 0));
}

function slideTexts(LayoutNode $layout): array {
  return array_values(array_filter($layout->leaves(), fn(LayoutLeaf $leaf): bool => $leaf->instance() instanceof SlideText && $leaf->instance()->sourceRuns() !== []));
}

/** Pixel box edges are exact and children partition the inner rectangle. */
function pixelSlideGeometry(): void {
  $slide = ['title' => [['text' => 'Padding']], 'elements' => []];
  foreach ([
    ['Default', 100, 50, [40, 40, 720, 720]],
    ['BrightMinimal', 100, 50, [80, 32, 672, 704]],
    ['BrightTechnical', 100, 50, [32, 24, 736, 744]],
  ] as [$theme, $columns, $rows, $expected]) {
    $layout = (new SlideLayout($theme))->build($slide);
    measureSlide($layout, $columns, $rows);
    $area = $layout->pixelContent();
    assertSame($expected, [$area->x, $area->y, $area->width, $area->height], 'Theme padding resolves against the exact slide pixels.');
    assertTrue($layout->leaves()[0]->pixelTile() !== null, 'Slides measure descendants in pixels.');
  }
  $box = new PixelBox(margin: ['left' => '5px', 'right' => '5px'], borderWidth: 2, padding: ['left' => '3px', 'right' => '3px']);
  [$border, $background, $inner] = $box->areas(new Tile(10, 20, 100, 40), 200, 100);
  assertSame([15, 20, 90, 40], [$border->x, $border->y, $border->width, $border->height], 'Margin stays inside the allocated tile.');
  assertSame([17, 22, 86, 36], [$background->x, $background->y, $background->width, $background->height], 'Border follows margin.');
  assertSame([20, 22, 80, 36], [$inner->x, $inner->y, $inner->width, $inner->height], 'Padding follows border.');
  $parts = PixelSplitter::split(new Tile(3, 4, 101, 20), 'horizontal', ['1*', '2*', '1*'], 101, 20);
  assertSame(3, $parts[0]->x, 'Pixel children start at their parent edge.');
  assertSame($parts[0]->x + $parts[0]->width, $parts[1]->x, 'Pixel children have no implicit gap.');
  assertSame(104, $parts[2]->x + $parts[2]->width, 'Pixel children use the whole parent width.');
}

/** A presentation and preview use their complete visible areas without cell padding. */
function pixelSlideWindowEdges(): void {
  $slide = ['title' => [['text' => 'Edges']], 'elements' => []];
  $grid = new Tile(0, 0, 100, 50);
  $geometry = new WindowGeometry(8, 16, 824, 824, 12, 12);
  $presentation = new \SPTK\Core\Screen((new SlideLayout('Default'))->build($slide));
  $presentation->measureGrid($grid);
  $presentation->measureArea($grid, $geometry);
  assertSame([0, 0, 824, 824], [$presentation->layout->pixelTile()->x, $presentation->layout->pixelTile()->y, $presentation->layout->pixelTile()->width, $presentation->layout->pixelTile()->height], 'Presentation slide covers the window without cell padding.');

  $preview = (new SlideLayout('Default'))->build($slide, '1*', '1*');
  $previewGrid = new Tile(10, 5, 60, 25);
  $preview->measureGrid($previewGrid);
  $preview->measureArea($grid, $geometry);
  $expected = $geometry->backgroundArea($previewGrid, $grid);
  assertSame([$expected->x, $expected->y, $expected->width, $expected->height], [$preview->pixelTile()->x, $preview->pixelTile()->y, $preview->pixelTile()->width, $preview->pixelTile()->height], 'Nested preview fills the complete cell tile background area.');
}

/** Main and normal slides keep the same roles and text decoration. */
function mainSlideContentStyles(): void {
  $styles = [];
  foreach (['# Main title', '## Normal title'] as $heading) {
    $parsed = SlideMarkdown::fromMarkdown([$heading, '> A quote', '', 'A paragraph.'], APP_DIR);
    $layout = (new SlideLayout('Default'))->build($parsed['slide']);
    measureSlide($layout, 120, 60);
    $texts = slideTexts($layout);
    assertSame(3, count($texts), 'Title, quote, and paragraph have separate pixel leaves.');
    $styles[$heading] = [];
    foreach (array_slice($texts, 1) as $leaf) {
      $area = $leaf->pixelContent();
      $styles[$heading][] = (new \ReflectionMethod(SlideText::class, 'content'))->invoke($leaf->instance(), $area->width, $area->height)[1];
    }
  }
  assertSame($styles['## Normal title'], $styles['# Main title'], 'Main slide content keeps normal slide typography.');
  assertSame('4px', (new SlideText([], 'quote', 'Default', (new SlideLayout('Default'))->build(['title' => [], 'elements' => []]), '#050505'))->pixelBox()->borderWidth['left'], 'Quote border belongs to the layout leaf.');
}

/** H1 content starts after a generous viewport-scaled title gap. */
function mainTitleContentGap(): void {
  $parsed = SlideMarkdown::fromMarkdown(['# Main title', 'A short paragraph.'], APP_DIR);
  foreach ([[100, 50], [66, 18]] as [$columns, $rows]) {
    $layout = (new SlideLayout('Default'))->build($parsed['slide']);
    measureSlide($layout, $columns, $rows);
    [$title, $body] = slideTexts($layout);
    $gap = $body->pixelTile()->y - $title->pixelTile()->y - $title->pixelTile()->height;
    assertTrue($gap >= (int)round($rows * 16 * 0.07), 'Main title has a scaled gap before its content.');
  }
}

/** StyledText line height does not depend on the glyphs in a slide title. */
function slideTitleLineHeight(): void {
  foreach (['#', '##'] as $level) {
    $heights = [];
    foreach (['aaa', 'aba', 'aga', 'abg'] as $word) {
      $parsed = SlideMarkdown::fromMarkdown([$level . ' ' . $word], APP_DIR);
      $layout = (new SlideLayout('Default'))->build($parsed['slide']);
      measureSlide($layout, 100, 50);
      $title = slideTexts($layout)[0];
      $heights[] = $title->pixelTile()->height;
    }
    assertSame(array_fill(0, 4, $heights[0]), $heights, 'Title line height is stable across ascenders and descenders.');
  }
}

/** Right-aligned script titles paint the same complete glyphs as left-aligned titles. */
function rightAlignedTitleInkFits(): void {
  foreach (['BrightEsoteric', 'DarkEsoteric'] as $theme) {
    foreach ([[100, 50], [66, 18]] as [$columns, $rows]) {
      $layout = (new SlideLayout($theme))->build(['title' => [['text' => 'Arcana']], 'elements' => []]);
      measureSlide($layout, $columns, $rows);
      $title = slideTexts($layout)[0];
      $area = $title->pixelContent();
      [$runs, $style, $referenceWidth, $referenceHeight] = (new \ReflectionMethod(SlideText::class, 'content'))->invoke($title->instance(), $area->width, $area->height);
      $raster = new \SPTK\Widgets\StyledText\Raster();
      $right = $raster->render($runs, $style, $area->width, $area->height, $referenceWidth, $referenceHeight);
      $left = $raster->render($runs, array_replace($style, ['textAlign' => 'left']), $area->width, $area->height, $referenceWidth, $referenceHeight);
      $ink = function(\SPTK\Core\RasterImage $image): int {
        $background = substr($image->pixels, 0, 4);
        $count = 0;
        for ($offset = 0; $offset < strlen($image->pixels); $offset += 4) {
          $count += substr($image->pixels, $offset, 4) !== $background;
        }
        return $count;
      };
      assertTrue($ink($left) > 0, 'Title raster contains painted text.');
      assertSame($ink($left), $ink($right), 'Right alignment preserves every painted title pixel.');
    }
  }
}

/** Natural text height uses the pixel content width at both presentation and preview sizes. */
function slideTextFits(): void {
  $parsed = SlideMarkdown::fromMarkdown([
    '## Fitting text',
    '### Longer box',
    str_repeat('This sentence wraps across several lines in a half-width box. ', 3),
    '### Short box',
    'Short text.',
  ], APP_DIR);
  foreach (SlideTheme::names() as $theme) {
    foreach ([[110, 40], [66, 18]] as [$columns, $rows]) {
      $layout = (new SlideLayout($theme))->build($parsed['slide']);
      measureSlide($layout, $columns, $rows);
      foreach (slideTexts($layout) as $leaf) {
        $area = $leaf->pixelContent();
        assertTrue($area !== null, 'Text leaf has a measured pixel content area.');
        assertTrue($leaf->instance()->contentHeight(max(1, $area->width)) <= $area->height, 'Text fits its exact pixel height.');
      }
      $leaves = $layout->leaves();
      $texts = slideTexts($layout);
      $longFill = $leaves[array_search($texts[2], $leaves, true) + 1];
      $shortFill = $leaves[array_search($texts[4], $leaves, true) + 1];
      assertTrue($longFill->instance() instanceof Placeholder && $shortFill->instance() instanceof Placeholder, 'Each box has a filler.');
      assertSame(0, $longFill->pixelTile()->height, 'Tallest box has no filler.');
      assertTrue($shortFill->pixelTile()->height > 0, 'Shorter box fills the row.');
      assertSame($longFill->pixelTile()->y + $longFill->pixelTile()->height, $shortFill->pixelTile()->y + $shortFill->pixelTile()->height, 'Fillers align box bottoms.');
    }
  }
}

/** Markers and text are separate rectangles but one selection target. */
function listRowsStayGrouped(): void {
  foreach ([['* First item', '* Second item'], ['1. First item', '2. Second item']] as $items) {
    $parsed = SlideMarkdown::fromMarkdown(['## Lists', '##### Box', ...$items], APP_DIR);
    $layout = (new SlideLayout('Default'))->build($parsed['slide']);
    measureSlide($layout, 100, 40);
    $targets = $layout->movementLeaves();
    assertSame(4, count($targets), 'Title, box heading, and two list rows are navigation targets.');
    foreach (array_slice($targets, 2) as $target) {
      [$marker, $text] = $layout->focusedLeaves($target);
      assertTrue($marker->instance() instanceof SlideText && $text->instance() instanceof SlideText, 'A list target contains marker and text.');
      assertSame($marker->pixelTile()->y, $text->pixelTile()->y, 'Marker and text share a row.');
      assertSame($marker->pixelTile()->x + $marker->pixelTile()->width, $text->pixelTile()->x, 'Marker and text tiles do not overlap.');
      assertSame($marker->instance()->contentHeight(1000), $marker->instance()->contentHeight(max(1, $marker->pixelContent()->width)), 'Marker remains on one line.');
    }
  }
  $parsed = SlideMarkdown::fromMarkdown(['## Image bullets', '![:bullet](green_arrow.png)', '* First', '* Second'], APP_DIR . '/Assets');
  $layout = (new SlideLayout('Default'))->build($parsed['slide']);
  measureSlide($layout, 66, 18);
  foreach (array_slice($layout->movementLeaves(), 1) as $target) {
    $children = $layout->focusedLeaves($target);
    assertSame(2, count($children), 'Image bullet and text highlight together.');
    assertTrue($children[0]->instance() instanceof \MADEMO\App\SlideImage, 'First child is an image bullet.');
    assertTrue($children[0]->pixelTile()->height <= $children[1]->pixelTile()->height, 'Image bullet does not add vertical space to a short list row.');
  }
}

/** Centered themes still keep list markers and wrapped text left aligned. */
function centeredThemeListsStayLeftAligned(): void {
  foreach (['BrightEsoteric', 'DarkEsoteric'] as $theme) {
    $plain = SlideMarkdown::fromMarkdown(['## Centered list', '* Short item'], APP_DIR);
    $plainLayout = (new SlideLayout($theme))->build($plain['slide']);
    measureSlide($plainLayout, 100, 50);
    [$plainMarker, $plainText] = $plainLayout->focusedLeaves($plainLayout->movementLeaves()[1]);
    assertSame($plainLayout->pixelContent()->x, $plainMarker->pixelTile()->x, 'Plain list marker begins at the slide content edge.');
    $plainStyle = (new \ReflectionMethod(SlideText::class, 'content'))->invoke($plainText->instance(), $plainText->pixelContent()->width, $plainText->pixelContent()->height)[1];
    assertSame('left', $plainStyle['textAlign'], 'Plain list text stays left aligned.');
    foreach ([
      ['## Centered list', '### Box', '* Short item'],
      ['## Centered list', '### Box', '![:bullet](green_arrow.png)', '* Short item'],
      ['## Centered list', '### Box', '* A longer item with enough words to wrap across several lines inside its box'],
    ] as $source) {
      $parsed = SlideMarkdown::fromMarkdown($source, APP_DIR . '/Assets');
      foreach ([[100, 50], [66, 18]] as [$columns, $rows]) {
        $layout = (new SlideLayout($theme))->build($parsed['slide']);
        measureSlide($layout, $columns, $rows);
        [$title, $boxTitle, $row] = $layout->movementLeaves();
        [$marker, $text] = $layout->focusedLeaves($row);
        assertSame($boxTitle->pixelTile()->x, $marker->pixelTile()->x, 'Marker begins at the box content edge.');
        assertSame($marker->pixelTile()->x + $marker->pixelTile()->width, $text->pixelTile()->x, 'Text follows the marker tile.');
        $textStyle = (new \ReflectionMethod(SlideText::class, 'content'))->invoke($text->instance(), $text->pixelContent()->width, $text->pixelContent()->height)[1];
        assertSame('left', $textStyle['textAlign'], 'List text remains left aligned in a centered theme.');
        if (!str_contains(end($source), 'Short item')) {
          assertTrue($text->instance()->contentHeight(max(1, $text->pixelContent()->width)) > $text->instance()->contentHeight(2000), 'Long list text wraps beside a fixed marker indent.');
        }
      }
    }
  }
}

/** Render bundled content at both sizes without touching the cell layout path. */
function slideBundledThemes(): void {
  $document = new Presentation(APP_DIR . '/Assets/doc.md');
  foreach (SlideTheme::names() as $theme) {
    for ($index = 0; $index < $document->count(); $index++) {
      foreach ([[110, 40], [66, 18]] as [$columns, $rows]) {
        $layout = (new SlideLayout($theme))->build($document->slide($index)['slide']);
        measureSlide($layout, $columns, $rows);
        foreach (slideTexts($layout) as $leaf) {
          $area = $leaf->pixelContent();
          if ($area->width > 0 && $area->height > 0) {
            $leaf->instance()->raster($area->width, $area->height);
          }
        }
      }
    }
  }
}

/** Replacing a preview subtree retains the active editor and its buffer. */
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
  $replacement = (new SlideLayout('Default'))->build(['title' => [['text' => 'Preview']], 'elements' => []], width: '1*', height: '1*');
  assertTrue($root->replaceChild($preview, $replacement), 'Preview subtree can be replaced.');
  $screen->setLayout($root);
  assertTrue($screen->activeLeaf()->instance() === $editor, 'Editor remains the active leaf.');
  assertSame('Original', $editor->getValue(), 'Editor buffer survives layout changes.');
  $screen->setLayout(new LayoutNode('vertical', '', ''));
  assertSame(false, $editor->editing(), 'Removed active widget is released.');
}

return [
  'Pixel slide geometry and box edges' => __NAMESPACE__ . '\\pixelSlideGeometry',
  'Presentation slide reaches window edges' => __NAMESPACE__ . '\\pixelSlideWindowEdges',
  'H1 content keeps normal slide styles' => __NAMESPACE__ . '\\mainSlideContentStyles',
  'H1 title has space before content' => __NAMESPACE__ . '\\mainTitleContentGap',
  'Styled text title height is stable' => __NAMESPACE__ . '\\slideTitleLineHeight',
  'Right-aligned title ink stays within the raster' => __NAMESPACE__ . '\\rightAlignedTitleInkFits',
  'Pixel slide text and box fillers fit' => __NAMESPACE__ . '\\slideTextFits',
  'List rows keep grouped selection' => __NAMESPACE__ . '\\listRowsStayGrouped',
  'Centered themes keep lists left aligned' => __NAMESPACE__ . '\\centeredThemeListsStayLeftAligned',
  'Bundled slides render across themes and sizes' => __NAMESPACE__ . '\\slideBundledThemes',
  'Preview replacement retains active editor' => __NAMESPACE__ . '\\slideRetainsEditor',
];
