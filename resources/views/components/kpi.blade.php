@props(['label', 'value', 'dark' => false, 'warn' => false, 'band' => null])

<div
    {{ $attributes->class(['kpi', 'is-dark' => $dark, 'is-warn' => $warn]) }}
    @if($band) style="box-shadow: inset 0 2px 0 {{ $band['fill'] }}" @endif
>
    <div class="kpi-label">{{ $label }}</div>
    <div class="kpi-value" @if($band) style="color: {{ $band['text'] }}" @endif>{!! $value !!}</div>
    @if($slot->isNotEmpty())
        <div class="kpi-note">{{ $slot }}</div>
    @endif
</div>
