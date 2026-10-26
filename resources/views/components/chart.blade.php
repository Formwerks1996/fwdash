@props(['kind', 'data', 'height' => '16rem'])

<div
    wire:key="chart-{{ $kind }}-{{ md5(json_encode($data)) }}"
    wire:ignore
    x-data="chart(@js($kind), @js($data))"
    {{ $attributes->merge(['style' => "height: {$height}", 'class' => 'relative']) }}
>
    <canvas x-ref="canvas"></canvas>
</div>
