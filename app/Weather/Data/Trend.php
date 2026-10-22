<?php

declare(strict_types=1);

namespace App\Weather\Data;

/**
 * An immutable description of how a metric has changed over a comparison
 * window. `direction` is a machine-friendly key; `label` is the display word;
 * `delta` is the signed change in the metric's own units (null when unknown).
 */
final readonly class Trend
{
    public const RISING = 'rising';

    public const FALLING = 'falling';

    public const STEADY = 'steady';

    public const UNKNOWN = 'unknown';

    public function __construct(
        public string $direction,
        public string $label,
        public ?float $delta,
        public string $comparison,
    ) {}

    public static function unknown(string $comparison = ''): self
    {
        return new self(self::UNKNOWN, '—', null, $comparison);
    }

    /**
     * @return array{direction: string, label: string, delta: float|null, comparison: string}
     */
    public function toArray(): array
    {
        return [
            'direction' => $this->direction,
            'label' => $this->label,
            'delta' => $this->delta === null ? null : round($this->delta, 2),
            'comparison' => $this->comparison,
        ];
    }
}
