@php
    use App\Support\Format;

    $f = $this->figures;
    $ringTotal = array_sum($f['ring']['values']);
@endphp

<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-heading text-[2.6rem] leading-none font-medium tracking-[-0.01em] text-navy">
                {{ $year }} <span class="font-sans text-[0.9rem] font-normal tracking-normal text-muted">{{ __('year to date') }}</span>
            </h1>
            <p class="page-subtitle">
                <b class="font-medium text-ink-2">{{ __('Revenue') }}</b>
                {{ __('entered through :month — :count of 12 months', ['month' => Format::month($f['lastEntered']), 'count' => $f['monthsEntered']]) }}
                · <b class="font-medium text-ink-2">{{ __('Hours') }}</b> {{ __('loaded through :month', ['month' => Format::month($f['hoursLoaded'])]) }}
            </p>
        </div>

        <div class="seg">
            @foreach($this->years as $option)
                <button type="button" wire:click="setYear({{ $option }})" @class(['is-active !bg-navy' => $option === $year])>{{ $option }}</button>
            @endforeach
        </div>
    </div>

    <div class="kpis grid-cols-1 sm:grid-cols-2 xl:grid-cols-5">
        <x-kpi :label="__('Revenue billed')" :value="Format::compact($f['ytd'])" dark>
            @if($f['priorYtd'])
                <x-trend :current="$f['ytd']" :prior="$f['priorYtd']" /> {{ __('on :amount over the same :count months of :year', ['amount' => Format::compact($f['priorYtd']), 'count' => $f['monthsEntered'], 'year' => $year - 1]) }}
            @else
                {{ __('over :count months · nothing comparable in :year', ['count' => $f['monthsEntered'], 'year' => $year - 1]) }}
            @endif
        </x-kpi>
        <x-kpi :label="__('Fee remaining')" :value="Format::compact($f['backlog'])">
            {{ __('still to bill on :count ongoing contracts', ['count' => $f['backlogJobs']]) }} · {{ Format::compact($f['backlogDesign']) }} {{ __('design') }}, {{ Format::compact($f['backlogCa']) }} {{ __('CA') }}
        </x-kpi>
        <x-kpi :label="__('Realized rate')" :value="$f['realized'] ? '$'.number_format($f['realized']).'<small>/h</small>' : '—'">
            @if($f['priorRealized'])
                <x-trend :current="$f['realized']" :prior="$f['priorRealized']" /> {{ __('vs the same months of :year', ['year' => $year - 1]) }}
            @else
                {{ __('billed per hour logged') }} · {{ Format::number($f['realizedHours']) }} h
            @endif
        </x-kpi>
        <x-kpi :label="__('Cash collected')" :value="Format::compact($f['collected'])">
            @if($f['priorCollected'])
                <x-trend :current="$f['collected']" :prior="$f['priorCollected']" /> {{ __('on :amount by this point in :year', ['amount' => Format::compact($f['priorCollected']), 'year' => $year - 1]) }}
            @else
                {{ __('dated payments received this year') }}
            @endif
        </x-kpi>
        <x-kpi :label="__('Consultant fees')" :value="Format::compact($f['consultants'])">
            {{ __('over the months entered') }} · {{ $f['ytd'] ? Format::pct($f['consultants'] / $f['ytd'], 1) : '—' }} {{ __('of revenue') }}
        </x-kpi>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 class="section-title">{{ __('Revenue by month') }}</h2>
                    <p class="section-note">
                        {{ __('Billed at month end. Months not yet entered show the year\'s run rate of :rate a month; the dashed line is :year.', ['rate' => Format::compact($f['runRate']), 'year' => $year - 1]) }}
                    </p>
                </div>
                <div class="text-right text-xs text-muted">
                    {{ __('On pace for') }}
                    <div class="font-heading text-xl text-navy">{{ Format::compact($f['projected']) }}</div>
                    @if($f['priorFull'])
                        {{ __(':year closed at :amount', ['year' => $year - 1, 'amount' => Format::compact($f['priorFull'])]) }}
                    @endif
                </div>
            </div>
            <x-chart kind="revenue" :data="$f['chart']" height="18rem" class="mt-4" />
        </div>

        <div class="card">
            <h2 class="section-title">{{ __('What was billed') }}</h2>
            <p class="section-note">{{ __('By division, :year.', ['year' => $year]) }}</p>
            <div class="relative mx-auto mt-4 w-full max-w-[14rem]">
                <x-chart kind="ring" :data="$f['ring']" height="14rem" />
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <span class="font-heading text-xl text-navy">{{ Format::compact($ringTotal) }}</span>
                    <span class="text-xs text-muted">{{ __('billed') }}</span>
                </div>
            </div>
            <ul class="mt-4 space-y-1.5 text-sm">
                @foreach($f['divisions'] as $label => $value)
                    <li @class(['flex items-center justify-between gap-2', 'text-muted' => ! $value])>
                        <span class="flex items-center gap-2"><span class="size-2.5" style="background: {{ $slots[$loop->index] }}"></span>{{ $label }}</span>
                        <span class="tabular-nums">{{ Format::compact($value) }} <span class="text-xs text-muted">{{ $ringTotal ? Format::pct($value / $ringTotal) : '' }}</span></span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <details class="card text-sm text-ink-2">
        <summary class="cursor-pointer section-title">{{ __('What this page is reading') }}</summary>
        <ul class="mt-3 list-disc space-y-1 pl-5 text-xs">
            <li>{{ __('Revenue is what month end billed: phase draws plus hourly billing, by billing period.') }}</li>
            <li>{{ __('Hours come from the Hours sheet of the uploaded workbook; the pre-system opening balance is left out of every month.') }}</li>
            <li>{{ __('Cash collected counts dated payments only. There is deliberately no receivables total: many older jobs have no payments recorded, so billed minus paid would measure a gap in the data, not money owed.') }}</li>
            <li>{{ __('Nothing here is profit: labour and overhead are not in the billing data.') }}</li>
        </ul>
    </details>
</div>
