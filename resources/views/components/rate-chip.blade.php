@props(['rate', 'hours' => null])

@php
    $band = \App\Support\RateBand::classify($rate === null ? null : (float) $rate, $hours === null ? null : (float) $hours);
@endphp

@if($band)
    <span
        class="rate-chip"
        style="background: {{ $band['wash'] }}; color: {{ $band['text'] }}; border-color: {{ $band['border'] }}"
        title="{{ $band['reason'] ?? $band['label'] }}"
    >
        @unless($band['reliable'])
            <i class="ph ph-warning"></i>
        @endunless
        ${{ number_format((float) $rate) }}
    </span>
@else
    <span class="empty">—</span>
@endif
