@props(['prospect'])

@php
    $colors = ['A' => '#1c6f50', 'B' => '#2f5576', 'C' => '#c28f1c', 'D' => '#9aa0a8'];
@endphp

<span class="inline-flex items-center gap-1.5">
    <span class="inline-flex size-5 items-center justify-center text-xs font-medium text-white" style="background: {{ $colors[$prospect->tier] ?? '#9aa0a8' }}">{{ $prospect->tier }}</span>
    <span class="text-xs text-muted">{{ $prospect->score }}/5</span>
</span>
