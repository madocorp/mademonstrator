# MaDemonstrator

MaDemonstrator is a presentation program written in PHP.
It can display a Markdown file as slides.

## Requirements

### PHP (CLI)

PHP 8 must be installed and available in your PATH.

Check with:

```
php --version
```

### SPTK

SPTK (SDL-based PHP Toolkit) is required.

Project page:
https://github.com/madocorp/sptk

Follow the installation instructions in that project.

You will need the **SPTK directory path** during installation.

## Installation

This is a **manual installation** (no packages).

### 1. Download the Source Code

Clone the repository from GitHub:

```
git clone https://github.com/madocorp/mademonstrator.git
cd mademonstrator
```

### 2. Choose Installation Location

You can put it anywhere, but here are some common locations:

```
~/.local/share/mademonstrator (linux, user-level)
/opt/mademonstrator (linux, system-wide)
~/Applications/mademonstrator (macOS, user-level)
/usr/local/opt/mademonstrator (macOS, system-wide)
```

Use sudo for system-wide installations.
The chosen one will be referred to as INSTALL_DIR below.

### 3. Move Files to the Installation Directory

Make sure you are still in the repository folder.

```
mkdir -p INSTALL_DIR
mv * INSTALL_DIR
```

### 4. Configure SPTK Symlink

The application expects SPTK to be available via a symlink from its directory.

```
cd INSTALL_DIR
ln -s /path/to/sptk SPTK
```

Replace /path/to/sptk with the actual SPTK installation directory.

### 5. Make the Main Script Executable

```
cd INSTALL_DIR
chmod +x mademonstrator.php
```

### 6. Create a Symlink in bin

This allows running the program like a normal command.

BIN_DIR is usually one of these:

```
~/.local/bin
~/bin
/usr/local/bin
```

```
ln -s INSTALL_DIR/mademonstrator.php BIN_DIR/mademonstrator
```

Ensure that BIN_DIR is in your PATH.

### 7. Running the program

Run from anywhere by typing its name.

```
mademonstrator
```

By default, the program displays a presentation about itself.
Use the **space** and **backspace** keys to navigate the slides.

