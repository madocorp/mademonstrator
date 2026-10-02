<?php

namespace MADEMO\Tests;

use MADEMO\App\{Presentation, PresentationTiming};

return [
  'ten-second frame warns in its final two seconds' => function(): void {
    $document = new Presentation();
    $document->changeSlide(0, ['# Opening']);
    $document->insert(0, ['## Timed', '<!-- TimeFrame: 10s -->']);
    $document->insert(1, ['## Closing']);
    $timing = new PresentationTiming();
    $now = 1_000_000_000;
    assertSame(['text' => '0:00:10', 'status' => 'green'], $timing->state($document, 1, $now), 'Ten-second slide begins green.');
    assertSame(['text' => '0:00:02', 'status' => 'yellow'], $timing->state($document, 1, $now + 8_000_000_000), 'Twenty percent of ten seconds is a two-second warning.');
    assertSame(['text' => '-0:00:01', 'status' => 'red'], $timing->state($document, 1, $now + 11_000_000_000), 'The timed slide turns red after its deadline.');
    assertSame(['text' => '-0:00:01', 'status' => 'red'], $timing->state($document, 2, $now + 11_000_000_000), 'Closing slide freezes overtime.');
  },
  'presentation timing starts after opening and recalculates color with each slide budget' => function(): void {
    $document = new Presentation();
    $document->changeSlide(0, ['# Opening', '<!-- TimeFrame: 1sec -->']);
    $document->insert(0, ['## First', '<!-- TimeFrame: 30sec -->']);
    $document->insert(1, ['## Second', '<!-- TimeFrame: 90sec -->']);
    $document->insert(2, ['## Default']);
    $timing = new PresentationTiming();
    $now = 1_000_000_000;
    assertSame(['text' => '0:02:00', 'status' => 'green'], $timing->state($document, 0, $now), 'Opening and closing slides do not count toward the timer.');
    assertSame(['text' => '0:02:00', 'status' => 'green'], $timing->state($document, 1, $now), 'Second slide starts the full timed budget.');
    assertSame(['text' => '0:01:36', 'status' => 'yellow'], $timing->state($document, 1, $now + 24_000_000_000), 'A slide close to its deadline is yellow.');
    assertSame(['text' => '0:01:29', 'status' => 'red'], $timing->state($document, 1, $now + 31_000_000_000), 'A slide past its deadline is red.');
    assertSame(['text' => '0:01:29', 'status' => 'green'], $timing->state($document, 2, $now + 31_000_000_000), 'The next slide adds its budget and immediately updates the color.');
    assertSame(['text' => '0:01:20', 'status' => 'green'], $timing->state($document, 3, $now + 40_000_000_000), 'Finishing early keeps the cumulative pace green.');
    assertSame(true, $timing->stopped(), 'Reaching the final slide stops the clock.');
    assertSame(['text' => '0:01:20', 'status' => 'green'], $timing->state($document, 3, $now + 240_000_000_000), 'The final slide keeps its arrival time and color.');
    assertSame(['text' => '0:01:20', 'status' => 'green'], $timing->state($document, 2, $now + 245_000_000_000), 'Moving backward after the finish does not restart the clock.');
    $late = new PresentationTiming();
    $late->state($document, 1, $now);
    assertSame(['text' => '-0:00:05', 'status' => 'red'], $late->state($document, 3, $now + 125_000_000_000), 'Finishing late freezes the overtime result.');
  },
];
