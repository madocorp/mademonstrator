# MaDemonstrator

Markdown presentations and an integrated slide editor for the current SPTK version.
Requires PHP 8.2+, FFI, XMLReader, mbstring, GD with FreeType, and SPTK's SDL3/SDL3_ttf libraries.
The application expects `SPTK` to be linked to the toolkit directory; this workspace
already includes `SPTK -> ../SPTK`.
`Layout` holds screen XML files; `Assets` holds the bundled presentation, reference text, and images.

Run from any working directory:

```sh
php /home/mado/Web/mad/mademonstrator/mademonstrator.php
php /home/mado/Web/mad/mademonstrator/mademonstrator.php /path/to/presentation.md
```

The app opens in editor mode. Press **F1** to start presenting or **F2** to resume
from the current slide; **F2** or **Esc** returns to the editor. The editor header
shows the first H1 title (or the file name when no H1 exists), the path, and an asterisk for unsaved edits.
The slide list is on the left, preview in the center, Markdown editor below it,
styles beside the preview, and actions beside the editor. Preview updates while typing. F8 switches this area between slides and speaker notes.
The bottom status bar follows the selected widget and changes its tip while that widget is active.
Notices, warnings, and errors temporarily replace the tip. Confirmation prompts use warning color.

Use arrow keys to select a tile, Return to activate it, and Esc to finish editing.
Button hotkeys and Ctrl+S/Ctrl+O work when no widget is active; typing inside an active
widget stays with that widget.
The Markdown editor also accepts Ctrl+Return to finish; Return inserts a newline.
The slide list selects a slide while active. Shift+Up/Down reorders the selected slide and retains its editor buffer.
Typing searches slide titles, including spaces; the displayed row numbers are excluded from search.
The preview and Markdown editor wait briefly when list movement starts, then load the latest slide when the arrow key is released or movement settles. Leaving the list also loads the pending slide.
Add, clone, delete, and restore work on the current slide. The last slide is retained.

| Key | Action |
| --- | --- |
| Space / Backspace | Next / previous slide in the editor when no widget is active, the presentation, or the helper window. |
| F1 | Start presenting from slide one, in the editor. |
| F2 | Resume presenting in the editor, or return to the editor from the presentation or helper. |
| F3 | Toggle the editor preview between slides and notes. |
| F4 | Settings, Markdown reference, and license, in the editor. |
| Ctrl+S / Ctrl+O | Save / open a presentation in the editor. |
| F8 | Reopen the speaker-notes window during presentation. |
| F10 | Quit during presentation. |

In presentation mode, Space advances a slide and Backspace returns to the previous one.
Other keys do not change slides. Return enters the slide so arrows can highlight its elements; Escape
returns to slide navigation. Outside the slide, Escape returns to the editor. Number keys
0–9 open web or mail links using `xdg-open` on Linux.
Speaker notes come from HTML comments. Starting or resuming a presentation opens a separate
helper window; put it on your screen and the slide window on the projector. Notes and slide
numbers update together. Space/Backspace in the helper control the audience slide.
Add `<!-- TimeFrame: 2min -->` or `<!-- TimeFrame: 10s -->` to a slide to set its planned time.
`m`/`min` and `s`/`sec` are accepted.
Slides without one use two minutes. The helper shows total time left in hours, minutes, and seconds;
the clock starts on slide two and freezes on arrival at the final slide. The opening and final
slides have no time budget. Green means the cumulative
schedule is comfortable, yellow means the current slide has at most 30 seconds or 20% of its
budget left, and red means the current slide's cumulative deadline has passed. Advancing to
the next slide adds its planned time and immediately recalculates the color.
Overtime appears with a minus sign. The final slide keeps the time and color recorded on arrival.
Return activates the notes tile for scrolling. Returning to the editor closes the helper;
closing the helper alone leaves the presentation running, and F8 reopens it.

Open and Save As replace the Markdown editor with a file selector in the same tile.
Return accepts the selected file; F5 activates the path field; F6 opens or saves the
entered path; Esc or F2 cancels. The editor and its previous focus return afterward.
Unsaved edits and overwrites ask in the status bar: Y confirms, N declines, and Esc
cancels. Settings persist
in `~/.mademonstrator/config.json`, including the old `presentationWindow` and `promptBox` values.
Settings accepts `full`, `max`, `normal`, or `WIDTHxHEIGHT` for either window; `none` disables
its helper window. Dimensions above 160 columns or 80 rows are pixels (for example `1280x720`);
smaller dimensions retain the old grid-cell interpretation (for example `78x36`).
Presentation geometry applies when starting/resuming, and the editor's previous size and mode
return when leaving presentation mode. Existing nested legacy configuration is also read.

The existing Markdown element conversion and all eleven theme files are retained.
`#` and `##` begin slides; `###`, `####`, and `#####` form half-, third-, and
quarter-width heading boxes; `######` is a subtitle. Paragraphs, strong emphasis,
inline/fenced code, quotations, lists, local images, custom image bullets, and
links are supported.

SlideLayout builds nested native SPTK layouts with StyledText and fitted image tiles.
Presentation and preview share this builder. The surrounding app uses SPTK's cell grid;
the slide subtree measures and renders its tiles in pixels. The preview reflows at its own
size. Dense content can still clip when the available area is too small.
Set `padding` in a style file's `Slide` rule to reserve empty space at the slide edges.
Use percentages in CSS order: top, right, bottom, left. For example,
`Slide { padding: 4% 6% 8% 10%; }` reserves 4% of slide height at the top,
6% of slide width at the right, 8% of slide height at the bottom, and 10% of
slide width at the left. One or two values also follow CSS shorthand rules.
Each percentage resolves to pixels when the slide is measured.
H1 title slides use the same separate content tiles and styles as normal slides,
with balanced flexible empty spacers above and below the title and content.
Text tiles use their measured pixel heights. Flexible spacers share any remaining
height; list rows and separate content elements have small explicit gaps.
`Block` margin, border, and padding surround each heading box once. Margins use
the parent background, borders use `borderColor`, and padding uses the box
background. Other text rules, such as `MainTitle` and `Quote`, put their margin,
border, and padding on their layout leaves. Sizes accept pixels, `vh`, or `vw`
in top/right/bottom/left order. SPTK's reusable pixel layout box model is
documented in `SPTK/Docs/layout.md`.

Validation:

```sh
php mademonstrator/Tests/run.php
php mademonstrator/Tests/Integration.php
php mademonstrator/Tests/Settings.php
php SPTK/Tests/StyledText.php
php SPTK/Tests/PixelLayout.php
```

The integration test uses SDL's dummy video driver. Set `MADEMO_SNAPSHOT_DIR` to an
existing directory to export presentation, editor, and speaker-notes framebuffer snapshots.
