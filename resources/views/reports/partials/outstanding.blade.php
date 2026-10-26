{{-- Outstanding by age and by client; on the page and on page 1 of the PDF. --}}
@php
    use App\Support\Format;
    use Carbon\CarbonImmutable;

    $periodEnd = CarbonImmutable::parse($period);
@endphp

@if($outstanding['total'] > 0)
    <section class="card me-cash">
        <div class="mc-head mb-2 flex flex-wrap items-baseline gap-2">
            <h3 class="section-title mr-2">{{ __('Outstanding') }} <small class="ml-1 font-sans text-xs font-normal text-muted">{{ __('as at :date', ['date' => $periodEnd->format('j M Y')]) }}</small></h3>
            <b class="font-heading text-[1.3rem] font-medium text-navy">{{ Format::compact($outstanding['total']) }}</b>
            <span class="text-[0.8rem] text-muted">{{ __('still owed by :clients clients, :jobs jobs', ['clients' => count($outstanding['clients']), 'jobs' => $outstanding['jobs']]) }}</span>
        </div>

        <div class="mc-bar mb-2 flex h-3 overflow-hidden rounded-[2px] bg-[#eef0f2]" role="img" aria-label="{{ __('Outstanding by time since last payment') }}">
            @foreach($outstanding['bands'] as $band)
                <i class="block h-full" style="width: {{ round($band['share'] * 100, 3) }}%; background: {{ $band['color'] }}" title="{{ $band['label'] }}: {{ Format::compact($band['amount']) }}"></i>
            @endforeach
        </div>

        <ul class="mc-key mb-3 flex flex-wrap gap-x-5 gap-y-1 text-[0.76rem]">
            <li class="text-muted">{{ __('By time since last payment:') }}</li>
            @foreach($outstanding['bands'] as $band)
                <li class="flex items-center gap-1.5"><i class="inline-block size-2.5 rounded-[2px]" style="background: {{ $band['color'] }}"></i>{{ $band['label'] }} <b class="font-medium">{{ Format::compact($band['amount']) }}</b></li>
            @endforeach
        </ul>

        <div class="mc-cols-head grid grid-cols-1 gap-x-6 pb-1 text-xs tracking-[0.08em] text-muted uppercase md:grid-cols-2 xl:grid-cols-3">
            @foreach(range(1, 3) as $column)
                <div @class(['grid grid-cols-[1fr_auto_7.4em] gap-2', 'hidden md:grid' => $column === 2, 'hidden xl:grid' => $column === 3])>
                    <span>{{ __('Client') }}</span><span class="text-right">{{ __('Still owed') }}</span><span class="text-right">{{ __('Last paid') }}</span>
                </div>
            @endforeach
        </div>
        <ul class="mc-all columns-1 gap-x-6 border-t border-dark pt-1 text-[0.8rem] leading-snug md:columns-2 xl:columns-3">
            @foreach($outstanding['clients'] as $client)
                <li class="grid break-inside-avoid grid-cols-[1fr_auto_7.4em] gap-2 border-b border-[#f0f1f3] py-[0.18rem]">
                    <span class="mc-name min-w-0 truncate" title="{{ $client['client'] }}">{{ $client['client'] }} @if($client['jobs'] > 1)<em class="text-[0.85em] text-muted not-italic">{{ __(':count jobs', ['count' => $client['jobs']]) }}</em>@endif</span>
                    <b class="text-right font-medium tabular-nums">{{ Format::compact($client['owed']) }}</b>
                    <span @class(['mc-d text-right tabular-nums whitespace-nowrap', 'font-medium text-[#b8532f]' => $client['late'], 'text-muted' => ! $client['late']])>{{ $client['last'] ? CarbonImmutable::parse($client['last'])->format('j M Y') : __('never') }}</span>
                </li>
            @endforeach
        </ul>
    </section>
@endif
