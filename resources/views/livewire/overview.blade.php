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
                <b class="font-medium text-ink-2">{{ __('Billing') }}</b>
                {{ __('entered through :month — :count of 12 months', ['month' => Format::month($f['lastEntered']), 'count' => $f['monthsEntered']]) }}
                · <b class="font-medium text-ink-2">{{ __('Costs') }}</b>
                {{ $f['costedMonths'] ? __('for :count of those months', ['count' => $f['costedMonths']]) : __('not entered yet') }}
            </p>
        </div>

        <div class="seg">
            @foreach($this->years as $option)
                <button type="button" wire:click="setYear({{ $option }})" @class(['is-active !bg-navy' => $option === $year])>{{ $option }}</button>
            @endforeach
        </div>
    </div>

    <div class="kpis grid-cols-1 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi :label="__('Billed')" :value="Format::compact($f['ytd'])" dark>
            @if($f['priorYtd'])
                <x-trend :current="$f['ytd']" :prior="$f['priorYtd']" /> {{ __('on :amount over the same :count months of :year', ['amount' => Format::compact($f['priorYtd']), 'count' => $f['monthsEntered'], 'year' => $year - 1]) }}
            @else
                {{ __('over :count months · nothing comparable in :year', ['count' => $f['monthsEntered'], 'year' => $year - 1]) }}
            @endif
        </x-kpi>
        <x-kpi :label="__('Owed to us')" :value="Format::compact($f['owed']['total'])" :warn="$f['owedLate'] > 0">
            {{ __('billed, not yet paid, on :count jobs', ['count' => $f['owed']['jobs']]) }}
            @if($f['owedLate'])
                · {{ Format::compact($f['owedLate']) }} {{ __('over 90 days or never paid') }}
            @endif
        </x-kpi>
        <x-kpi
            :label="__('Cost')"
            :value="$f['costedMonths'] ? Format::compact($f['cost']) : '—'"
            class="is-clickable"
            role="button"
            tabindex="0"
            wire:click="$dispatch('show-spending', { months: {{ json_encode($f['spendingMonths']) }} })"
            x-on:keydown.enter="$el.click()"
        >
            @if($f['costedMonths'] && ! $f['estimatedMonths'])
                {{ Format::compact($f['payroll']) }} {{ __('payroll') }}, {{ Format::compact($f['overhead']) }} {{ __('overhead') }} · {{ trans_choice(':count month|:count months', $f['costedMonths']) }}
            @elseif($f['costedMonths'])
                @if(count($f['estimatedMonths']) === $f['costedMonths'])
                    {{ __('estimate: staff time at cost, no overhead') }}
                @else
                    {{ __(':sheet from the Costs sheet; :months estimated from staff time, no overhead', ['sheet' => trans_choice(':count month|:count months', $f['costedMonths'] - count($f['estimatedMonths'])), 'months' => implode(', ', $f['estimatedMonths'])]) }}
                @endif
            @else
                {{ __('add a Costs sheet to the workbook to see this') }}
            @endif
            <span class="kpi-more">{{ __('Where it went') }} <i class="ph ph-arrow-right"></i></span>
        </x-kpi>
        <x-kpi :label="$f['estimatedMonths'] ? __('Profit (estimate)') : __('Profit')" :value="$f['profit'] === null ? '—' : Format::compact($f['profit'])" :warn="$f['profit'] !== null && $f['profit'] < 0">
            @if($f['profit'] !== null)
                {{ Format::pct($f['margin'], 1) }} {{ __('margin') }} · {{ __('billed minus cost over :months', ['months' => trans_choice(':count month|:count months', $f['costedMonths'])]) }}
                @if($f['estimatedMonths'])
                    · {{ __('before overhead, so it reads high') }}
                @endif
            @else
                {{ __('billed minus cost, once costs are in') }}
            @endif
        </x-kpi>
    </div>

    @if($f['estimatedUncostedHours'])
        <p class="-mt-4 text-xs text-[#9d6714]">
            <i class="ph ph-warning"></i> {{ __(':hours h in the estimated months are by people with no cost rate, so cost reads low and profit high.', ['hours' => Format::number($f['estimatedUncostedHours'])]) }}
            <a href="{{ route('staff-costs') }}" wire:navigate class="underline">{{ __('Set rates') }}</a>
        </p>
    @endif

    @if($f['uncosted'] && $f['costedMonths'])
        <p class="-mt-4 text-xs text-[#9d6714]">
            <i class="ph ph-warning"></i> {{ __('No costs entered for :months, so they are left out of cost and profit.', ['months' => implode(', ', $f['uncosted'])]) }}
        </p>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 class="section-title">{{ __('Billed, cost and profit by month') }}</h2>
                    <p class="section-note">
                        {{ __('Months not yet entered show the year\'s run rate of :rate a month. Click a month to see where its money went.', ['rate' => Format::compact($f['runRate'])]) }}
                        @if($f['costsWaiting'])
                            {{ __('Costs for :months are in but wait for month end.', ['months' => implode(', ', $f['costsWaiting'])]) }}
                        @endif
                    </p>
                </div>
                <div class="text-right text-xs text-muted">
                    {{ __('Billing on pace for') }}
                    <div class="font-heading text-xl text-navy">{{ Format::compact($f['projected']) }}</div>
                    @if($f['priorFull'])
                        {{ __(':year closed at :amount', ['year' => $year - 1, 'amount' => Format::compact($f['priorFull'])]) }}
                    @endif
                </div>
            </div>
            <x-chart kind="overview" :data="$f['chart']" height="18rem" class="mt-4" x-on:chart-click="$wire.dispatch('show-spending', { months: ['{{ $year }}-' + String($event.detail.index + 1).padStart(2, '0')] })" />
        </div>

        <div class="space-y-4">
            <x-kpi :label="__('Fee left to bill')" :value="Format::compact($f['backlog'])">
                {{ __('on :count ongoing contracts — not owed yet', ['count' => $f['backlogJobs']]) }}
            </x-kpi>
            <x-kpi :label="__('Realized rate')" :value="$f['realized'] ? '$'.number_format($f['realized']).'<small>/h</small>' : '—'">
                {{ __('billed per project hour') }} · {{ Format::number($f['realizedHours']) }} h
            </x-kpi>
            <x-kpi :label="__('Run rate')" :value="Format::compact($f['runRate']).'<small>/mo</small>'">
                {{ __('average billed per month this year') }}
            </x-kpi>
        </div>
    </div>

    @if($f['owed']['total'] > 0)
        <section class="card">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <div class="flex flex-wrap items-baseline gap-2">
                    <h2 class="section-title mr-2">{{ __('Who owes us') }}</h2>
                    <span class="text-xs text-muted">{{ __('as at :date, by time since their last payment', ['date' => Format::date($f['owedAsAt'])]) }}</span>
                </div>
                <a href="{{ route('monthEnd', ['only' => 'owing', 'sort' => 'outstanding', 'direction' => 'desc']) }}" wire:navigate class="text-xs text-navy underline">{{ __('Every job in Month End') }}</a>
            </div>

            <div class="mt-3 flex h-3 overflow-hidden rounded-[2px] bg-[#eef0f2]" role="img" aria-label="{{ __('Owed by time since last payment') }}">
                @foreach($f['owed']['bands'] as $band)
                    <i class="block h-full" style="width: {{ round($band['share'] * 100, 3) }}%; background: {{ $band['color'] }}" title="{{ $band['label'] }}: {{ Format::compact($band['amount']) }}"></i>
                @endforeach
            </div>
            <ul class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-[0.76rem]">
                @foreach($f['owed']['bands'] as $band)
                    <li class="flex items-center gap-1.5"><i class="inline-block size-2.5 rounded-[2px]" style="background: {{ $band['color'] }}"></i>{{ $band['label'] }} <b class="font-medium">{{ Format::compact($band['amount']) }}</b></li>
                @endforeach
            </ul>

            <ul class="mt-4 grid gap-x-8 text-sm md:grid-cols-2">
                @foreach(array_slice($f['owed']['clients'], 0, 10) as $client)
                    <li class="grid grid-cols-[1fr_auto_6.5em] gap-2 border-b border-[#f0f1f3] py-1">
                        <span class="min-w-0 truncate" title="{{ $client['client'] }}">{{ $client['client'] }} @if($client['jobs'] > 1)<em class="text-xs text-muted not-italic">{{ __(':count jobs', ['count' => $client['jobs']]) }}</em>@endif</span>
                        <b class="text-right font-medium tabular-nums">{{ Format::compact($client['owed']) }}</b>
                        <span @class(['text-right text-xs tabular-nums whitespace-nowrap', 'font-medium text-[#b8532f]' => $client['late'], 'text-muted' => ! $client['late']])>{{ $client['last'] ? \Carbon\CarbonImmutable::parse($client['last'])->format('j M Y') : __('never paid') }}</span>
                    </li>
                @endforeach
            </ul>
            @if(count($f['owed']['clients']) > 10)
                <p class="mt-2 text-xs text-muted">{{ __('and :count more clients', ['count' => count($f['owed']['clients']) - 10]) }}</p>
            @endif
        </section>
    @endif

    <div class="card">
        <div class="grid items-center gap-6 md:grid-cols-[14rem_1fr]">
            <div class="relative mx-auto w-full max-w-[14rem]">
                <x-chart kind="ring" :data="$f['ring']" height="14rem" />
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <span class="font-heading text-xl text-navy">{{ Format::compact($ringTotal) }}</span>
                    <span class="text-xs text-muted">{{ __('billed') }}</span>
                </div>
            </div>
            <div>
                <h2 class="section-title">{{ __('What was billed') }}</h2>
                <p class="section-note">{{ __('By division, :year.', ['year' => $year]) }}</p>
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
    </div>

    <details class="card text-sm text-ink-2">
        <summary class="cursor-pointer section-title">{{ __('Where these numbers come from') }}</summary>
        <ul class="mt-3 list-disc space-y-1 pl-5 text-xs">
            <li>{{ __('Billed is what month end invoiced: phase draws plus hourly billing, by billing period.') }}</li>
            <li>{{ __('Owed to us is everything billed and not yet paid, on every job, as Month End shows it. Older jobs with payments missing from the workbook inflate it — check the "never paid" and long-overdue clients before chasing.') }}</li>
            <li>{{ __('Cost comes from the Costs sheet of the workbook: one row per month, a column per expense (Payroll, Rent, Software…). A month with no row there is estimated from staff time at each person\'s hourly cost, which leaves overhead out. Profit is billed minus cost, over the months that have both.') }}</li>
            <li>{{ __('Fee left to bill is contract fee not yet invoiced. It is future work, not money owed.') }}</li>
            <li>{{ __('Realized rate is billed divided by hours logged to projects; office and admin time is not in it.') }}</li>
        </ul>
    </details>

    <livewire:spending-breakdown />
</div>
