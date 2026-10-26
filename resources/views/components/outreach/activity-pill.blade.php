@props(['prospect'])

@php
    $status = \App\Livewire\BusinessDevelopment\OutreachIndex::status($prospect);
    [$color, $tip] = \App\Livewire\BusinessDevelopment\OutreachIndex::Activity[$status] ?? \App\Livewire\BusinessDevelopment\OutreachIndex::Activity['Unknown'];
    $unconfirmed = $status !== 'Unknown' && ! ($prospect->activity['confirmed'] ?? false);
@endphp

<span class="inline-flex items-center gap-1.5 text-xs" title="{{ $tip }}">
    <span class="size-2 rounded-full" style="background: {{ $color }}"></span>
    {{ $status }}@if($unconfirmed)<span title="{{ __('Unconfirmed: rests on an undated page or an earlier record') }}">~</span>@endif
    @if($prospect->activity['distress'] ?? false)
        <span class="chip is-warn" title="{{ __('Receivership, foreclosure, a halted or rejected project, etc. See the Activity notes') }}">{{ __('Distress') }}</span>
    @endif
</span>
