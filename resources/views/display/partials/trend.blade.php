@php
    /**
     * A single trend chip (arrow + label + signed delta + comparison window).
     *
     * Rendered server-side for the first paint and then updated in place by
     * public/display/app.js (updateTrend), which relies on this exact structure:
     * a root [data-dir] element containing a `use` (the arrow), a `.trend-label`,
     * and a `.trend-delta`. Keep those hooks stable if you restyle this.
     *
     * @var array{direction:string,label:string,delta:float|null,comparison:string}|null $t
     * @var string $id
     */
    use App\Weather\Support\Format;

    $t = $t ?? null;
    $dir = $t['direction'] ?? 'unknown';
    $arrow = match ($dir) {
        'rising' => 'ui-arrow-up',
        'falling' => 'ui-arrow-down',
        'steady' => 'ui-arrow-right',
        default => 'ui-arrow-right',
    };
    $label = $t['label'] ?? Format::DASH;
    $delta = isset($t['delta']) ? Format::delta($t['delta']) : '';
    $comparison = $t['comparison'] ?? '';
@endphp
<span class="trend" id="{{ $id }}" data-dir="{{ $dir }}">
    <svg class="trend-arrow" aria-hidden="true" @if ($dir === 'unknown') hidden @endif><use href="#{{ $arrow }}"></use></svg>
    <span class="trend-label">{{ $label }}</span>
    <span class="trend-delta">{{ $delta }}</span>
    <span class="trend-when">{{ $comparison }}</span>
</span>
