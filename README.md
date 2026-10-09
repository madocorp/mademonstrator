# MaDemonstrator

MaDemonstrator presents Markdown files as slides and includes a slide editor.

## Requirements

- PHP 8.2 or newer on the command line, with FFI, XMLReader, mbstring, and
  PCNTL enabled. GD is needed for graphs and non-PNG images.
- [SPTK](https://github.com/madocorp/SPTK), with SDL3 and SDL3_ttf available
  as described by its installation instructions. SDL3_ttf also needs FreeType
  and HarfBuzz. PNG images require `libpng16.so.16`.
- A graphical desktop session to run the application.

Check PHP with:

```sh
php --version
php -m | grep -Ei '^(FFI|xmlreader|mbstring|gd|pcntl)$'
```

Install missing PHP extensions and SDL libraries with your system's package
manager. Package names vary by system.

## Installation

MaDemonstrator is installed manually; it does not use Composer. The example
below installs both projects for one Linux user:

```sh
mkdir -p "$HOME/.local/share" "$HOME/.local/bin"
git clone https://github.com/madocorp/SPTK.git "$HOME/.local/share/SPTK"
git clone https://github.com/madocorp/mademonstrator.git \
  "$HOME/.local/share/mademonstrator"
ln -sfn "$HOME/.local/share/SPTK" \
  "$HOME/.local/share/mademonstrator/SPTK"
ln -s "$HOME/.local/share/mademonstrator/mademonstrator.php" \
  "$HOME/.local/bin/mademonstrator"
```

The application loads SPTK from a directory or symlink named `SPTK` beside
`mademonstrator.php`. The clone includes a relative link for a sibling SPTK
checkout; the `ln -sfn` command sets an absolute link for the paths above.
If you install either project elsewhere, change those paths accordingly.
Ensure `~/.local/bin` is in your `PATH`, or run the PHP script by its full path.

The main script is executable in the repository. If a copied installation
loses that permission, restore it with `chmod +x mademonstrator.php` inside
the installation directory.

## Running

```sh
mademonstrator
mademonstrator /path/to/presentation.md
```

Without a file argument, MaDemonstrator opens its bundled example
presentation. Use **F1** to present from the first slide, **F2** to present
from the selected slide, and **Esc** to return to the editor. Space and
Backspace move between slides while presenting. The editor's Settings screen
includes the Markdown reference.

Press H on a selected tile to see its help. Save and other routine messages
use the highlighted info color and wait for Return or Esc. Warnings and errors
also wait for acknowledgment; decisions use Y/Return or N/Esc. File browser
guidance stays visible while you choose a path and returns after other messages.
