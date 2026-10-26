{{-- A doughnut as inline SVG: no script, so it prints and saves to PDF as drawn. --}}
@php
    use App\Support\Format;

    $outer = 48;
    $inner = $outer * 0.62;
    $radius = ($outer + $inner) / 2;
    $width = $outer - $inner;
    $circumference = 2 * M_PI * $radius;
    $filled = array_values(array_filter($ring['slices'], fn ($slice) => $slice['value'] > 0));
    $gap = count($filled) > 1 ? 0.9 : 0;
    $offset = 0;
    $format = fn ($value) => $ring['money'] ? Format::compact($value) : Format::number($value).' h';
@endphp

<div class="me-mix">
    <div class="me-mix-head">
        <h3 class="section-title">{{ $ring['title'] }}</h3>
        <p class="section-note">{{ $ring['note'] }}</p>
    </div>
    <div class="me-mix-body flex flex-wrap items-center gap-5">
        <div class="me-ring relative shrink-0" style="width: {{ $size ?? 168 }}px; height: {{ $size ?? 168 }}px">
            <svg viewBox="0 0 100 100" class="size-full -rotate-90" role="img" aria-label="{{ $ring['title'] }}">
                <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke="#eef0f2" stroke-width="{{ $width }}" />
                @foreach($filled as $slice)
                    @php
                        $length = $ring['total'] ? $slice['value'] / $ring['total'] * $circumference : 0;
                        $drawn = max(0, $length - $gap);
                    @endphp
                    <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke="{{ $slice['color'] }}" stroke-width="{{ $width }}"
                        stroke-dasharray="{{ round($drawn, 3) }} {{ round($circumference - $drawn, 3) }}" stroke-dashoffset="{{ round(-$offset, 3) }}">
                        <title>{{ $slice['label'] }}: {{ $format($slice['value']) }}</title>
                    </circle>
                    @php($offset += $length)
                @endforeach
            </svg>
            <div class="me-ring-mid pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center leading-tight">
                <b class="font-heading text-lg font-medium text-navy">{{ $format($ring['total']) }}</b>
                <span class="text-[0.7rem] text-muted">{{ $monthName }}</span>
            </div>
        </div>
        <ul class="me-legend min-w-[12rem] flex-1 space-y-1 text-[0.8rem]">
            @foreach($ring['slices'] as $slice)
                <li @class(['flex items-center justify-between gap-3', 'text-muted' => ! $slice['value']])>
                    <span class="flex items-center gap-2"><span class="inline-block size-2.5" style="background: {{ $slice['color'] }}"></span>{{ $slice['label'] }}</span>
                    <span class="tabular-nums">
                        {{ $slice['value'] ? $format($slice['value']) : '—' }}
                        <span class="inline-block w-9 text-right text-xs text-muted">{{ $ring['total'] && $slice['value'] ? Format::pct($slice['value'] / $ring['total']) : '' }}</span>
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
