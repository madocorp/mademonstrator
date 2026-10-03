<?php

namespace MADEMO\App;

use SPTK\Core\Style;
use SPTK\Events\EventContext;
use SPTK\Layout\{LayoutLeaf, LayoutNode};
use SPTK\Widgets\Text\Text;

/** Keeps the editor preview tile stable while switching between slide layouts and speaker notes. */
final class EditorPreview {

  public static bool $notes = false;
  private static LayoutNode|LayoutLeaf $content;

  /** Find the initial preview placeholder inside its persistent navigation group. */
  public static function initialize(): void {
    foreach (Controller::screen('editor')->layout->leaves() as $leaf) {
      if ($leaf->instance()->id() === 'preview') {
        self::$content = $leaf;
      }
    }
  }

  /** Replace preview content while keeping the surrounding editor and its focus tile intact. */
  public static function update(array $parsed): void {
    $screen = Controller::screen('editor');
    if (self::$notes) {
      $text = new Text(Presenter::title($parsed) . "\n\n" . Presenter::notesText($parsed), new Style());
      $text->setId('previewNotes');
      $replacement = new LayoutLeaf('Text', '1*', '1*', $text);
    } else {
      $replacement = (new SlideLayout(Controller::$session->theme))->build($parsed['slide'], width: '1*', height: '1*');
    }
    if (!$screen->layout->replaceChild(self::$content, $replacement)) {
      throw new \RuntimeException('Editor preview subtree was not found.');
    }
    self::$content = $replacement;
    $screen->setLayout($screen->layout);
    Controller::widget('editor', 'previewMode')->setLabel(self::$notes ? 'Show slides' : 'Show notes');
  }

  /** Toggle notes and slides without resetting the Markdown editor buffer. */
  public static function toggle(EventContext $event): bool {
    Controller::commit();
    self::$notes = !self::$notes;
    Controller::sync();
    return true;
  }

}
