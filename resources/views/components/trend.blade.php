@props(['current', 'prior'])

@if($prior)
    @php
        $change = ($current - $prior) / abs($prior);
    @endphp
    <span @class(['inline-flex items-center gap-0.5 font-medium', 'text-[#20825c]' => $change >= 0, 'text-[#9d6714]' => $change < 0])>
        <i @class(['ph', 'ph-arrow-up-right' => $change >= 0, 'ph-arrow-down-right' => $change < 0])></i>
        {{ $change >= 0 ? '+' : '−' }}{{ number_format(abs($change) * 100, 1) }}%
    </span>
@endif
