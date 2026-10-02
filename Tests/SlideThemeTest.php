<?php

namespace MADEMO\Tests;

use MADEMO\App\SlideTheme;

return [
  'slide theme parses css like selectors and properties' => function(): void {
    $theme = SlideTheme::parse('
      Slide { backgoundColor: #112233; padding: 3% 7% 9% 11%; }
      MainTitle { fontSize: 8vh; fontWeight: bold; textAlign: center; margin: 1vh 2vw; }
      Quote { borderWidth: 0 0 0 5px; padding: 2vh 3vw; }
      InlineCode { fontFamily: TeX Gyre Cursor, Noto Sans Mono, monospace; background-color: #223344; }
    ');
    assertSame('#112233', $theme['slide']['background'], 'Slide background should parse from backgroundColor aliases.');
    assertSame(['top' => '3%', 'right' => '7%', 'bottom' => '9%', 'left' => '11%'], $theme['slide']['padding'], 'Slide padding should retain percentages in CSS side order.');
    assertSame('8vh', $theme['main-title']['fontSize'], 'Font size should keep supported units before resolution.');
    assertSame('bold', $theme['main-title']['fontWeight'], 'Font weight should parse.');
    assertSame(['top' => '1vh', 'right' => '2vw', 'bottom' => '1vh', 'left' => '2vw'], $theme['main-title']['margin'], 'Text margin should parse like CSS boxes.');
    assertSame(['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => '5px'], $theme['quote']['borderWidth'], 'Four value border widths should parse like CSS boxes.');
    assertSame(['top' => '2vh', 'right' => '3vw', 'bottom' => '2vh', 'left' => '3vw'], $theme['quote']['padding'], 'Two value padding should parse like CSS boxes.');
    assertSame('#223344', $theme['inline-code']['background'], 'Kebab-case properties should parse.');
    assertSame(['TeX Gyre Cursor', 'Noto Sans Mono', 'monospace'], $theme['inline-code']['fontFamily'], 'Comma-separated font families should parse as fallbacks.');
  },

  'slide theme resolves style files into styled text box properties' => function(): void {
    $style = SlideTheme::textStyle('Default', 'slide-title', 1200, 800);
    $code = SlideTheme::runStyle('Default', 'code', 800);
    $palette = SlideTheme::palette('Default');
    assertSame('#050505', $palette['bg'], 'Palette should read slide background from the style file.');
    assertSame(['top' => 5.0, 'right' => 5.0, 'bottom' => 5.0, 'left' => 5.0], SlideTheme::slidePadding('Default'), 'Slide padding should read percentages from its style file.');
    assertSame(38, $style['fontSize'], 'Slide title font size should resolve vh units.');
    assertSame(true, $style['bold'], 'Bold font weight should map to StyledTextBox shorthand.');
    assertSame(['top' => 0, 'right' => 24, 'bottom' => 0, 'left' => 24], SlideTheme::textStyle('Default', 'main-title', 1200, 800)['margin'], 'Main title margin should resolve against the slide.');
    assertSame(20, $code['fontSize'], 'Inline code font size should resolve against slide height.');
    assertSame('monospace', $code['fontFamily'], 'Inline code should keep font family from the style file.');
  },
];
