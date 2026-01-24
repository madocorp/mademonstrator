<?php

namespace MADEMO;

class Controller {

  public static $presentation = false;
  public static $currentSlide = 0;
  public static $configDir;
  public static $styleDir;
  private static $config;
  private static $newSlide = false;

  public static function init() {
    self::$config = \SPTK\Config::load(\SPTK\Config::getFilePath('config.xml'));
    if (!isset(self::$config['config'])) {
      self::$config['config'] = [
        'defaultStyle' => 'DarkTechnical',
        'defaultDir' => \SPTK\Config::getHome(),
        'presentationWindow' => 'full',
        'promptBox' => 'none',
        'browserCmd' => 'firefox --new-tab %url%'
      ];
    }
    self::$configDir = \SPTK\Config::getPath();
    self::$styleDir = \SPTK\Config::getFilePath('Styles');
    if (!is_dir(self::$styleDir)) {
      mkdir(self::$styleDir);
      $appDir = dirname(APP_PATH);
      foreach (glob($appDir . '/Styles/*.xss') as $styleFile) {
        copy($styleFile, self::$styleDir . '/' . basename($styleFile));
      }
    }
    self::open('Layout/doc.md');
    self::loadStyles();
  }

  public static function loadStyles() {
    $menuBox = \SPTK\Element::byName('style-list');
    $menuBox->clear();
    $menuBox->setOnSelect('\MADEMO\Controller::changeStyle');
    foreach (glob(self::$styleDir . '/*.xss') as $i => $styleFile) {
      $name = basename($styleFile, '.xss');
      $menuItem = new \SPTK\MenuBoxItem($menuBox);
      $menuItem->setSelectable('styles');
      $menuItem->setFilterable(true);
      $menuItem->setValue($name);
      $menuItem->setText($name);
      if ($name === self::$config['config']['defaultStyle']) {
        $menuItem->setSelected('true');
        self::changeStyle($name);
      }
    }
  }

  public static function changeStyle($name) {
    if (!is_string($name)) {
      $name = $name->getValue();
    }
    $path = self::$configDir . "/Styles/{$name}.xss";
    \SPTK\StyleSheet::clearCache();
    \SPTK\StyleSheet::load($path, true);
    $slide = \SPTK\Element::firstByType('Slide');
    $slide->recalculateStyle();
    self::$currentSlide = self::$presentation->showSlide(self::$currentSlide);
    \SPTK\Element::refresh();
  }

  public static function keyPressHandler($element, $event) {
    switch (\SPTK\KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case \SPTK\Action::CLOSE:
        self::leavePresentationMode();
        return true;
      case \SPTK\Action::SELECT_ITEM:
        self::setCurrentSlide(self::$currentSlide + 1);
        \SPTK\Element::refresh();
        return true;
      case \SPTK\Action::DELETE_BACK:
        self::setCurrentSlide(self::$currentSlide - 1);
        \SPTK\Element::refresh();
        return true;
      case \SPTK\KeyCode::NUM_0:
        self::gotoLink(0);
        return true;
      case \SPTK\KeyCode::NUM_1:
        self::gotoLink(1);
        return true;
      case \SPTK\KeyCode::NUM_2:
        self::gotoLink(2);
        return true;
      case \SPTK\KeyCode::NUM_3:
        self::gotoLink(3);
        return true;
      case \SPTK\KeyCode::NUM_4:
        self::gotoLink(4);
        return true;
      case \SPTK\KeyCode::NUM_5:
        self::gotoLink(5);
        return true;
      case \SPTK\KeyCode::NUM_6:
        self::gotoLink(6);
        return true;
      case \SPTK\KeyCode::NUM_7:
        self::gotoLink(7);
        return true;
      case \SPTK\KeyCode::NUM_8:
        self::gotoLink(8);
        return true;
      case \SPTK\KeyCode::NUM_9:
        self::gotoLink(9);
        return true;
    }
    return false;
  }

  public static function gotoLink($i) {
    $link = self::$presentation->getLink($i);
    if ($link === false) {
      return;
    }
    $cmd = self::$config['config']['browserCmd'];
    $cmd = str_replace('%url%', $link, $cmd);
    exec($cmd);
  }

  public static function openFile() {
    self::selectFile('\MADEMO\Controller::open', self::$config['config']['defaultDir']);
  }

  public static function selectFile($callback, $path) {
    $window = \SPTK\Element::firstByType('Window');
    $panel = new \SPTK\FilePanel($window);
    $panel->setFileFilter(['.md']);
    $panel->setPath($path);
    $panel->setCreate(true);
    $panel->setOnSelect($callback);
    $panel->show();
    \SPTK\Element::refresh();
  }

  public static function setCurrentSlide($parameter) {
    $menuBox = \SPTK\Element::byName('slide-list');
    if (is_int($parameter)) {
      $menuItem = $menuBox->nthChild(self::$currentSlide);
      $menuItem->deselect();
      $i = $parameter;
    } else {
      $i = $parameter->getValue();
    }
    self::$currentSlide = self::$presentation->showSlide($i);
    $menuItem = $menuBox->nthChild(self::$currentSlide);
    $menuItem->select();
    \SPTK\Element::refresh();
  }

  public static function buildSlideMenu() {
    $slides = self::$presentation->getSlideList();
    $menuBox = \SPTK\Element::byName('slide-list');
    $menuBox->clear();
    $menuBox->setOnSelect('\MADEMO\Controller::setCurrentSlide');
    foreach ($slides as $index => $title) {
      $menuItem = new \SPTK\MenuBoxItem($menuBox);
      $menuItem->setSelectable('slides');
      $menuItem->setFilterable(true);
      $menuItem->setValue($index);
      $menuItem->setText($title);
      if ($index === self::$currentSlide) {
        $menuItem->setSelected('true');
        $menuItem->setSelected('true');
      }
    }
  }

  public static function open($path) {
    self::$presentation = new Presentation($path);
    self::$currentSlide = 0;
    self::buildSlideMenu();
    self::$currentSlide = self::$presentation->showSlide(self::$currentSlide);
    \SPTK\Element::refresh();
  }

  public static function leavePresentationMode() {
    $presWin = \SPTK\Element::byName('presentation-window');
    $presWin->fullscreenOff();
    $presWin->recalculateStyle();
    $presWin->configure();
    $helperWin = \SPTK\Element::byName('helper-window');
    if ($helperWin !== false) {
      $helperWin->remove();
    }
    $menu = \SPTK\Element::firstByType('Menu');
    $menu->show();
    \SPTK\Element::refresh();
  }

  public static function enterPresentationMode() {
    $menu = \SPTK\Element::firstbyType('Menu');
    $menu->hide();
    $presWin = \SPTK\Element::byName('presentation-window');
    if (mb_strpos(self::$config['config']['presentationWindow'], 'full') !== false) {
      $presWin->fullscreenOn();
    } else {
      self::configureWindow($presWin, self::$config['config']['presentationWindow']);
    }
    // set display !
    // screen saver off ?
    if (mb_strpos(self::$config['config']['promptBox'], 'none') === false) {
      $helperWin = new \SPTK\Window(\SPTK\Element::$root, 'helper-window');
      $helperWin->addEvent('KeyPress', '\MADEMO\Controller::keyPressHandler');
      $helperWin->setTitle('PromptBox');
      self::configureWindow($helperWin, self::$config['config']['promptBox']);
      // set display !
      new \SPTK\Element($helperWin, false, false, 'PromptBoxTitle');
      new \SPTK\Element($helperWin, false, false, 'PromptBoxContent');
    }
  }

  public static function start() {
    self::enterPresentationMode();
    self::setCurrentSlide(0);
    self::$currentSlide = self::$presentation->showSlide(self::$currentSlide);
    \SPTK\Element::refresh();
  }

  public static function resume() {
    self::enterPresentationMode();
    self::$currentSlide = self::$presentation->showSlide(self::$currentSlide);
    \SPTK\Element::refresh();
  }

  public static function saveFile() {
    $path = self::$presentation->getFile();
    self::selectFile('\MADEMO\Controller::save', $path);
  }

  public static function save($path) {
    self::$presentation->save($path);
  }

  public static function add() {
    self::$newSlide = true;
    $panel = \SPTK\Element::byName('edit');
    $panel->show();
    $editor = \SPTK\Element::byName('mdeditor', $panel);
    $editor->setValue('');
    \SPTK\Element::refresh();
  }

  public static function edit() {
    self::$newSlide = false;
    $panel = \SPTK\Element::byName('edit');
    $panel->show();
    $code = self::$presentation->getCode(self::$currentSlide);
    $editor = \SPTK\Element::byName('mdeditor', $panel);
    $editor->setValue(implode("\n", $code));
    \SPTK\Element::refresh();
  }

  public static function saveSlide($panel) {
    $value = $panel->getValue();
    $code = $value['mdeditor'];
    if (self::$newSlide) {
      self::$presentation->changeSlide(self::$currentSlide, $code, true);
      self::$currentSlide++;
      self::buildSlideMenu();
    } else {
      self::$presentation->changeSlide(self::$currentSlide, $code);
    }
    $panel->hide();
    self::$currentSlide = self::$presentation->showSlide(self::$currentSlide);
    \SPTK\Element::refresh();
  }

  public static function cloneSlide() {
    $code = self::$presentation->getCode(self::$currentSlide);
    self::$presentation->changeSlide(self::$currentSlide, $code, true);
    self::$currentSlide++;
    self::buildSlideMenu();
    self::$currentSlide = self::$presentation->showSlide(self::$currentSlide);
    \SPTK\Element::refresh();
  }

  public static function delete() {
    self::$presentation->deleteSlide(self::$currentSlide);
    self::$currentSlide = self::$presentation->showSlide(self::$currentSlide);
    self::buildSlideMenu();
    \SPTK\Element::refresh();
  }

  public static function restore() {
    $i = self::$presentation->restoreSlide();
    if ($i !== false) {
      self::$currentSlide = self::$presentation->showSlide($i);
      self::buildSlideMenu();
    }
    \SPTK\Element::refresh();
  }

  public static function sort() {
    $slides = self::$presentation->getSlideList();
    $panel = \SPTK\Element::byName('sort');
    $listBox = \SPTK\Element::byName('order', $panel);
    $listBox->clear();
    foreach ($slides as $index => $title) {
      $listItem = new \SPTK\ListItem($listBox);
      $listItem->setValue($index);
      $listItem->setText($title);
    }
    $panel->show();
    \SPTK\Element::refresh();
  }

  public static function saveSort($panel) {
    $values = $panel->getValue();
    self::$presentation->sort($values['order']);
    self::$currentSlide = 0;
    self::buildSlideMenu();
    self::$currentSlide = self::$presentation->showSlide(self::$currentSlide);
    $panel->hide();
    \SPTK\Element::refresh();
  }


  public static function settings() {
    $panel = \SPTK\Element::byName('settings');
    $panel->setValue(self::$config['config']);
    $panel->show();
    \SPTK\Element::refresh();
  }

  public static function saveSettings($panel) {
    self::$config['config'] = $panel->getValue();
    $file = \SPTK\Config::getFilePath('config.xml');
    \SPTK\Config::save($file, self::$config['config'], 'config');
    $panel->hide();
    \SPTK\Element::refresh();
  }

  public static function closePanel($panel) {
    $panel->hide();
    \SPTK\Element::refresh();
  }

  public static function about() {
    $panel = \SPTK\Element::byName('about');
    $panel->show();
    \SPTK\Element::refresh();
  }

  public static function quit() {
    \SPTK\App::$instance->quit();
  }

  public static function configureWindow($window, $geometryString) {
    $geometry = self::parseGeometryString($geometryString);
    $style = $window->getStyle();
    $style->set('width', $geometry['w']);
    $style->set('height', $geometry['h']);
    $style->set('x', $geometry['x']);
    $style->set('y', $geometry['y']);
    $window->configure();
  }

  public static function parseGeometryString($string) {
    if (mb_strpos($string, 'max') !== false) {
      return ['w' => '100%', 'h' => '100%', 'x' => '0px', 'y' => '0px', 'd' => 0];
    }
    $string = mb_strtolower($string);
    if (preg_match("/([0-9]+%?)x([0-9]+%?)([+-][0-9]+%?)([+-][0-9]+%?)(:[0-9]+)?/", $string, $m)) {
      for ($i = 1; $i < 5; $i++) {
        if (strpos($m[$i], '%') === false) {
          $m[$i] .= 'px';
        }
      }
      return ['w' => $m[1], 'h' => $m[2], 'x' => $m[3], 'y' => $m[4], 'd' => $m[5] ?? 0];
    }
    return false;
  }

}
