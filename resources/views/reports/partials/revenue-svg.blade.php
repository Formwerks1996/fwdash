{{-- Revenue by month as inline SVG at the 6:1 shape it has on paper. --}}
@php
    use App\Support\Format;

    $width = 1200;
    $height = 200;
    $left = 56;
    $right = 12;
    $top = 10;
    $bottom = 24;

    $values = array_filter([...$chart['revenue'], ...$chart['prior']], fn ($value) => $value !== null);
    $max = $values ? max($values) : 0;
    $raw = $max > 0 ? $max / 5 : 1;
    $magnitude = 10 ** floor(log10($raw));
    $step = collect([1, 2, 2.5, 5, 10])->map(fn ($factor) => $factor * $magnitude)->first(fn ($candidate) => $candidate >= $raw);
    $ceiling = max($step, ceil($max / $step) * $step);

    $x = fn (int $index) => $left + ($width - $left - $right) * ($index + 0.5) / 12;
    $y = fn (float $value) => $top + ($height - $top - $bottom) * (1 - $value / $ceiling);

    $segments = [];
    foreach ($chart['revenue'] as $index => $value) {
        $previous = $chart['revenue'][$index - 1] ?? null;
        if ($index > 0 && $value !== null && $previous !== null) {
            $segments[] = ['x1' => $x($index - 1), 'y1' => $y($previous), 'x2' => $x($index), 'y2' => $y($value), 'estimate' => $chart['bases'][$index] !== 'actual'];
        }
    }

    $priorPoints = collect($chart['prior'])
        ->map(fn ($value, $index) => $value === null ? null : round($x($index), 1).','.round($y($value), 1))
        ->filter()
        ->implode(' ');
@endphp

<svg viewBox="0 0 {{ $width }} {{ $height }}" class="me-rev-svg block h-auto w-full" role="img" aria-label="{{ __('Revenue by month') }}">
    @for($tick = 0; $tick <= $ceiling + 0.001; $tick += $step)
        <line x1="{{ $left }}" x2="{{ $width - $right }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" stroke="#f0f1f2" stroke-width="1" />
        <text x="{{ $left - 8 }}" y="{{ $y($tick) + 4 }}" text-anchor="end" font-size="11" fill="#5c6873">{{ Format::compact($tick) }}</text>
    @endfor

    @foreach($chart['labels'] as $index => $label)
        <text x="{{ $x($index) }}" y="{{ $height - 6 }}" text-anchor="middle" font-size="11" fill="#5c6873">{{ $label }}</text>
    @endforeach

    @if($priorPoints)
        <polyline points="{{ $priorPoints }}" fill="none" stroke="#5c6873" stroke-width="1.5" stroke-dasharray="4 3" />
    @endif

    @foreach($segments as $segment)
        <line x1="{{ $segment['x1'] }}" y1="{{ $segment['y1'] }}" x2="{{ $segment['x2'] }}" y2="{{ $segment['y2'] }}"
            stroke="{{ $segment['estimate'] ? '#a9bccb' : '#00263E' }}" stroke-width="2.5" @if($segment['estimate']) stroke-dasharray="5 4" @endif />
    @endforeach

    @foreach($chart['revenue'] as $index => $value)
        @continue($value === null)
        @php
            $isSelected = $index === $chart['selected'];
            $isActual = $chart['bases'][$index] === 'actual';
        @endphp
        <circle cx="{{ $x($index) }}" cy="{{ $y($value) }}" r="{{ $isSelected ? 6 : 3 }}"
            fill="{{ $isSelected ? '#0b3a5a' : ($isActual ? '#00263E' : '#fff') }}"
            stroke="{{ $isSelected ? '#0b3a5a' : ($isActual ? '#00263E' : '#a9bccb') }}" stroke-width="1.5" />
    @endforeach
</svg>
