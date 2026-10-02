<?php

namespace MADEMO\Tests;

use MADEMO\App\{Presentation, Session};

/** Verify source preservation, fenced headings, and failed save behavior. */
function presentationRoundTrip(): void {
  $path = tempnam(sys_get_temp_dir(), 'mademo-test-');
  $source = "# Opening\n\n```\n## This is code\n  indented\n\n\nlast\n```\n\n---\n\n## Second\n\nText\n";
  try {
    file_put_contents($path, $source);
    $document = new Presentation($path);
    assertSame(2, $document->count(), 'Fenced headings must not split slides.');
    assertTrue(in_array('  indented', $document->code(0), true), 'Indentation is preserved.');
    $document->save($path);
    $loaded = new Presentation($path);
    assertSame($document->code(0), $loaded->code(0), 'Code blocks survive save and reload.');
    assertSame($document->slideTitles(), $loaded->slideTitles(), 'Titles survive save and reload.');
    try {
      $document->save($path . '/missing.md');
      throw new TestFailure('Failed save should throw.');
    } catch (\RuntimeException $error) {
      assertSame($path, $document->file(), 'Failed save retains the previous target.');
    }
  } finally {
    unlink($path);
  }
}

/** Verify insertion, movement, deletion, restoration, and dirty buffer commits. */
function presentationEditing(): void {
  $session = new Session();
  assertSame(false, $session->commit('# New presentation'), 'Unchanged editor text is not dirty.');
  assertSame(true, $session->commit('# Edited'), 'Changed source is committed.');
  assertSame(true, $session->dirty, 'Changed source is unsaved.');
  $document = $session->document;
  $index = $document->insert(0, ['## Added']);
  assertSame(1, $index, 'Insert follows current slide.');
  $document->reorder(['1', '0']);
  try {
    $document->reorder(['0', '0']);
    throw new TestFailure('Duplicate slide indices should be rejected.');
  } catch (\InvalidArgumentException $error) {
    assertSame(['Added', 'Edited'], $document->slideTitles(), 'Invalid order leaves slides intact.');
  }
  assertSame(['Added', 'Edited'], $document->slideTitles(), 'Move preserves slide order.');
  assertTrue($document->delete(0), 'Delete accepts a multi-slide document.');
  assertSame(0, $document->restore(), 'Restore returns the original slot.');
  assertSame(['Added', 'Edited'], $document->slideTitles(), 'Restore preserves original source.');
  $document->delete(1);
  assertSame(false, $document->delete(0), 'Last slide cannot be removed.');
}

/** Verify the first real H1, file basename fallback, and live source changes. */
function presentationDisplayTitle(): void {
  $path = tempnam(sys_get_temp_dir(), 'mademo-title-');
  try {
    file_put_contents($path, "## First slide\n\n```md\n# Fenced text\n```\n\nNotes start here <!--\n# Commented text\n-->\n\n---\n\n## Second slide\n");
    $document = new Presentation($path);
    assertSame(2, $document->count(), 'H1 text inside comments does not create a slide.');
    assertSame(basename($path), $document->displayTitle(), 'H2 headings and hidden H1 text do not replace the file name.');
    $document->insert(1, ['# Actual title']);
    assertSame('Actual title', $document->displayTitle(), 'An H1 on a later slide becomes the presentation title.');
    $document->insert(0, ['# First title']);
    assertSame('First title', $document->displayTitle(), 'The first H1 in presentation order wins.');
    $document->reorder(['3', '0', '1', '2']);
    assertSame('Actual title', $document->displayTitle(), 'Reordering changes which H1 comes first.');
    $document->changeSlide(0, ['## No main title']);
    assertSame('First title', $document->displayTitle(), 'Editing away an H1 refreshes the title.');
    $document->changeSlide(2, ['## No main title']);
    assertSame(basename($path), $document->displayTitle(), 'Removing all H1 headings restores the file name.');
    $document->save($path);
    assertSame(basename($path), (new Presentation($path))->displayTitle(), 'File-name fallback survives reload.');
    file_put_contents($path, '');
    assertSame(basename($path), (new Presentation($path))->displayTitle(), 'An empty file uses its name rather than a generated slide heading.');
  } finally {
    unlink($path);
  }
  $unsaved = new Presentation();
  $unsaved->changeSlide(0, ['## No main title']);
  assertSame('Untitled presentation', $unsaved->displayTitle(), 'Unsaved H2-only presentation has a neutral fallback.');
}

return ['Markdown file round trip' => __NAMESPACE__ . '\\presentationRoundTrip', 'Slide editing and session commits' => __NAMESPACE__ . '\\presentationEditing', 'Presentation display title' => __NAMESPACE__ . '\\presentationDisplayTitle'];
