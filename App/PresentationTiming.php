<?php

namespace MADEMO\App;

/** Tracks presentation time against the accumulated budgets of shown slides. */
final class PresentationTiming {

  public const DEFAULT_SECONDS = 120;

  private ?int $startedAt = null;
  private int $firstSlide = 1;
  private ?array $finishedState = null;

  /** Report whether the closing slide has frozen the clock. */
  public function stopped(): bool {
    return $this->finishedState !== null;
  }

  /** Start when the first timed slide is shown; the opening slide has no budget. */
  public function state(Presentation $document, int $index, ?int $now = null): array {
    if ($this->finishedState !== null) {
      return $this->finishedState;
    }
    $now ??= hrtime(true);
    $lastSlide = $document->count() - 1;
    if ($this->startedAt === null && $index >= 1) {
      $this->startedAt = $now;
      $this->firstSlide = $index;
    }
    $budgets = [];
    for ($slide = 1; $slide < $lastSlide; $slide++) {
      $budgets[$slide] = $document->slide($slide)['timeFrame'] ?? self::DEFAULT_SECONDS;
    }
    $first = $this->startedAt === null ? 1 : $this->firstSlide;
    $total = array_sum(array_slice($budgets, $first - 1));
    $elapsed = $this->startedAt === null ? 0 : max(0, intdiv($now - $this->startedAt, 1_000_000_000));
    $remaining = $total - $elapsed;
    if ($index >= $lastSlide) {
      $lastBudget = $budgets[$lastSlide - 1] ?? self::DEFAULT_SECONDS;
      $margin = min(30, max(1, (int)ceil($lastBudget * 0.2)));
      $status = $total === 0 ? 'green' : ($remaining < 0 ? 'red' : ($remaining <= $margin ? 'yellow' : 'green'));
      return $this->finishedState = ['text' => self::format($remaining), 'status' => $status];
    }
    $deadline = 0;
    for ($slide = $first; $slide <= $index; $slide++) {
      $deadline += $budgets[$slide] ?? 0;
    }
    $currentBudget = $budgets[$index] ?? self::DEFAULT_SECONDS;
    $margin = min(30, max(1, (int)ceil($currentBudget * 0.2)));
    $status = $this->startedAt === null || $index < 1 ? 'green'
      : ($elapsed > $deadline ? 'red' : ($deadline - $elapsed <= $margin ? 'yellow' : 'green'));

    return ['text' => self::format($remaining), 'status' => $status];
  }

  /** Keep an overrun visible with a minus sign. */
  public static function format(int $seconds): string {
    $absolute = abs($seconds);
    return ($seconds < 0 ? '-' : '') . sprintf('%d:%02d:%02d', intdiv($absolute, 3600), intdiv($absolute % 3600, 60), $absolute % 60);
  }

}
