<?php

namespace MADEMO\App;

class Slide {

  private $slide;
  private $bulletImage = false;
  public $links = [];

  public function __construct($code) {
    $this->slide = \SPTK\Element::firstByType('Slide');
    $this->slide->clear();
    $this->build($code);
  }

  private function build($code) {
    $tokens = \SPTK\Tokenizer::start($code, '\MADEMO\Tokenizer\Md');
    $content = new \SPTK\Element($this->slide, false, false, 'Content');
    $block = $content;
    $paragraph = false;
    $this->clearHelpText(true);
    foreach ($tokens as $line) {
      if (empty($line['tokens'])) {
        $paragraph = false;
      }
      foreach ($line['tokens'] as $token) {
        switch ($token['type']) {
          case 'T1':
            $paragraph = false;
            $this->createMainTitle($token, $content);
            break;
          case 'T2':
            $paragraph = false;
            $this->createTitle($token, $this->slide);
            break;
          case 'T3':
            $block = $this->createBlock('Block1', $content);
            $paragraph = false;
            $this->createSubTitle($token, $block);
            break;
          case 'T4':
            $block = $this->createBlock('Block2', $content);
            $paragraph = false;
            $this->createSubTitle($token, $block);
            break;
          case 'T5':
            $block = $this->createBlock('Block3', $content);
            $paragraph = false;
            $this->createSubTitle($token, $block);
            break;
          case 'T6':
            $paragraph = false;
            $this->createSubTitle($token, $block);
            break;
          case 'WORD':
            if ($paragraph === false) {
              $paragraph = $this->createBlock('Paragraph', $block);
            }
            $this->createWord($token['value'], $paragraph, false);
            break;
          case 'EMPHASIZED':
            if ($token['value'] !== '**') {
              if ($paragraph === false) {
                $paragraph = $this->createBlock('Paragraph', $block);
              }
              $this->createWord($token['value'], $paragraph, 'emphasized');
            }
            break;
          case 'LIST':
          case 'ORDERED_LIST':
            $paragraph = $this->createBlock('List', $block);
            if ($token['type'] === 'LIST') {
              if ($this->bulletImage === false) {
                $bullet = new \SPTK\Element($paragraph, false, false, 'Bullet');
                $bullet->setText('*');
              } else {
                $bullet = new \SPTK\Elements\Image($paragraph, false, 'bullet');
                $bullet->setValue($this->bulletImage);
              }
            } else {
              $numbering = new \SPTK\Element($paragraph, false, false, 'Numbering');
              $numbering->setText(trim($token['value']));
            }
            break;
          case 'BLOCKQUOTE':
            if ($paragraph === false || $paragraph->getType() !== 'Quotation') {
              $paragraph = $this->createBlock('Quotation', $block);
              $value = ltrim($token['value'], '> ');
              $paragraph->setText($value);
            } else {
              new \SPTK\Elements\NL($paragraph);
              $value = ltrim($token['value'], '> ');
              $paragraph->addText($value);
            }
            break;
          case 'INLINE_CODE':
            if ($paragraph === false) {
              $paragraph = $this->createBlock('Paragraph', $block);
            }
            $value = trim($token['value'], '`');
            $words = explode(' ', $value);
            foreach ($words as $codeword) {
              $this->createWord($codeword, $paragraph, 'inlinecode');
            }
            break;
          case 'CODE':
            if ($paragraph === false || $paragraph->getType() !== 'Code') {
              if ($token['value'] !== '```') {
                $paragraph = $this->createBlock('Code', $block);
                $value = trim($token['value'], '`');
                $paragraph->setText($value);
              }
            } else {
              if ($token['value'] !== '```') {
                new \SPTK\Elements\NL($paragraph);
                $value = trim($token['value'], '`');
                $paragraph->addText($value);
              } else {
                $paragraph = false;
              }
            }
            break;
          case 'LINK':
            if ($paragraph === false) {
              $paragraph = $this->createBlock('Paragraph', $block);
            }
            $this->createLink($token, $paragraph);
            break;
          case 'IMAGE':
            if (!$this->specialImage($token, $paragraph)) {
              if ($paragraph === false) {
                $paragraph = $this->createBlock('Paragraph', $block);
              }
              $this->createImage($token, $paragraph);
            }
            break;
          case 'COMMENT':
            $this->createHelpText($token['value']);
            break;
          case 'WHITESPACE':
            if ($paragraph !== false) {
              new \SPTK\Elements\Space($paragraph);
            }
            break;
          case 'HLINE':
            break;
        }
      }
      if ($paragraph !== false) {
        new \SPTK\Elements\Space($paragraph);
      }
    }
  }

  private function createMainTitle($token, $element) {
    $value = ltrim($token['value'], '# ');
    if (!empty($value)) {
      $title = new \SPTK\Element($element, false, false, 'MainTitle');
      $title->setText($value);
      $this->createHelpTitle($value);
    }
  }

  private function createTitle($token, $element) {
    $value = ltrim($token['value'], '# ');
    if (!empty($value)) {
      $title = new \SPTK\Element($element, false, false, 'Title');
      $title->setText($value);
      $this->createHelpTitle($value);
    }
  }

  private function createSubTitle($token, $element) {
    $value = ltrim($token['value'], '# ');
    if (!empty($value)) {
      $title = new \SPTK\Element($element, false, false, 'SubTitle');
      $title->setText($value);
    }
  }

  private function createBlock($type, $element) {
    return new \SPTK\Element($element, false, false, $type);
  }

  private function createWord($text, $block, $class) {
    $word = new \SPTK\Elements\Word($block, false, $class);
    $word->setValue($text);
  }

  private function createLink($token, $element) {
    if (mb_strpos($token['value'], '<') === 0) {
      $text = trim($token['value'], ' <>');
      $this->links[] = $text;
    } else {
      preg_match('/\[([^\]]+)\]\(([^\)]+)\)/', $token['value'], $match);
      $text = $match[1];
      $this->links[] = $match[2];
    }
    $id = count($this->links) - 1;
    $link = new \SPTK\Element($element, false, false, 'Link');
    $ide = new \SPTK\Element($link, false, false, 'LinkId');
    $ide->setText("[{$id}]");
    $link->addText($text);
  }

  private function createImage($token, $element) {
    preg_match('/!\[([^\]]+)\]\(([^\)]+)\)/', $token['value'], $match);
    $path = $match[2];
    $img = new \SPTK\Elements\Image($element);
    $img->setValue($path);
  }

  private function specialImage($token, $paragraph) {
    preg_match('/!\[([^\]]+)\]\(([^\)]+)\)/', $token['value'], $match);
    $config = $match[1];
    $path = $match[2];
    if ($config === ':bullet') {
      $this->bulletImage = $path;
      return true;
    } else if (mb_strpos($config, ':absolute:') === 0) {
      $img = new \SPTK\Elements\Image($this->slide);
      $config = str_replace(':absolute:', '', $config);
      $geometry = Controller::parseGeometryString($config);
      $style = $img->getStyle();
      $style->set('position', 'absolute');
      $style->set('width', $geometry['w']);
      $style->set('height', $geometry['h']);
      $style->set('x', $geometry['x']);
      $style->set('y', $geometry['y']);
      $img->setValue($path);
      return true;
    } else if (mb_strpos($config, ':inline:') === 0) {
      $img = new \SPTK\Elements\Image($paragraph);
      $config = str_replace(':inline:', '', $config);
      $geometry = Controller::parseGeometryString($config);
      $style = $img->getStyle();
      $style->set('width', $geometry['w']);
      $style->set('height', $geometry['h']);
      $img->setValue($path);
      return true;
    }
    return false;
  }

  private function createHelpTitle($title) {
    $helperWin = \SPTK\Element::byName('helper-window');
    if ($helperWin === false) {
      return;
    }
    $promptBoxTitle = \SPTK\Element::firstByType('PromptBoxTitle', $helperWin);
    $promptBoxTitle->setText($title);
  }

  private function clearHelpText() {
    $helperWin = \SPTK\Element::byName('helper-window');
    if ($helperWin === false) {
      return;
    }
    $promptBoxContent = \SPTK\Element::firstByType('PromptBoxContent', $helperWin);
    $promptBoxContent->clear();
  }

  private function createHelpText($text) {
    $helperWin = \SPTK\Element::byName('helper-window');
    if ($helperWin === false) {
      return;
    }
    $promptBoxContent = \SPTK\Element::firstByType('PromptBoxContent', $helperWin);
    $nl = ($text === "\n");
    $text = trim($text, "<!- >");
    if (!empty($text)) {
      $promptBoxContent->addText($text);
      $nl = true;
    }
    if ($nl) {
      new \SPTK\Elements\NL($promptBoxContent);
    }
  }

}
