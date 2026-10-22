@php
    /**
     * Inline SVG sparkline drawn from geometry pre-computed by
     * App\Weather\Support\Sparkline (same math server + client). The JS updater
     * (drawSparkline) targets the `.spark-area` and `.spark-line` paths by the
     * root's id, so keep those class hooks and the id wiring intact.
     *
     * @var array{path:?string,area:?string,view_box:string,count:int}|null $spark
     * @var string $id
     */
    $spark = $spark ?? null;
    $viewBox = $spark['view_box'] ?? '0 0 100 32';
    $path = $spark['path'] ?? null;
    $area = $spark['area'] ?? null;
@endphp
<svg class="spark" id="{{ $id }}" viewBox="{{ $viewBox }}" preserveAspectRatio="none" aria-hidden="true">
    <path class="spark-area" d="{{ $area }}" @if (! $area) hidden @endif></path>
    <path class="spark-line" d="{{ $path }}" @if (! $path) hidden @endif></path>
</svg>
