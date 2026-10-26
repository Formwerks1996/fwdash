@php
    use App\Billing\PeriodReports;
    use App\Support\Format;
    use App\Support\RateBand;

    $f = $this->figures;
    $rows = $this->rows;
    $closed = $period && $engine->periodStatus($period) === 'closed';
    $monthName = Format::month($period);
    $printQuery = ['period' => $period, 'only' => $only, 'sort' => $sort, 'direction' => $direction];
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="page-title">{{ __('Month end') }}</h1>
            <p class="page-subtitle">
                {{ $monthName }}@if($closed) · <strong class="font-medium text-ink-2">{{ __('closed') }}</strong>@endif
            </p>
        </div>

        @if($period)
            <div class="flex flex-wrap items-center gap-2">
                <select wire:model.live="period" class="select" aria-label="{{ __('Billing period') }}">
                    @foreach($periods as $option)
                        <option value="{{ $option }}">{{ Format::month($option) }}@if($engine->periodStatus($option) === 'closed') ({{ __('closed') }})@endif</option>
                    @endforeach
                </select>
                <select wire:model.live="only" class="select" aria-label="{{ __('Show') }}">
                    @foreach(PeriodReports::Filters as $key => $filter)
                        <option value="{{ $key }}">{{ __($filter['label']) }}</option>
                    @endforeach
                </select>
                <a href="{{ route('monthEnd.print', $printQuery) }}" target="_blank" class="btn-secondary" title="{{ __('Open this report as a printable page, then save it as a PDF') }}">
                    <i class="ph ph-file-pdf"></i>{{ __('PDF') }}
                </a>
                <a href="{{ route('monthEnd.print', [...$printQuery, 'variant' => 'classic']) }}" target="_blank" class="btn-secondary" title="{{ __('The old "Report for Jim" sheet\'s layout: same columns, same colours, 11x17 landscape') }}">
                    <i class="ph ph-file-pdf"></i>{{ __('PDF — original layout') }}
                </a>
                @can(\App\Enums\Permission::EditBilling->value)
                    @if($closed)
                        <button type="button" class="btn-secondary" wire:click="reopenPeriod" wire:confirm="{{ __('Reopen :month? Its phase draws and hour lines become editable again.', ['month' => $monthName]) }}">
                            <i class="ph ph-lock-open"></i>{{ __('Reopen') }}
                        </button>
                    @else
                        <button type="button" class="btn-secondary" wire:click="closePeriod" wire:confirm="{{ __('Close :month? Nothing billed in it can be changed until it is reopened.', ['month' => $monthName]) }}">
                            <i class="ph ph-lock"></i>{{ __('Close month') }}
                        </button>
                    @endif
                @endcan
                <a href="{{ route('monthEnd.entry', ['period' => $period]) }}" class="btn" wire:navigate>{{ __('Enter billing') }} <i class="ph ph-arrow-right"></i></a>
            </div>
        @endif
    </div>

    @error('billing') <p class="error-note">{{ $message }}</p> @enderror

    @if(! $period)
        <div class="card text-sm text-muted">{{ __('No billing period has been entered yet.') }}</div>
    @else
        <div class="kpis grid-cols-1 md:grid-cols-3">
            <x-kpi :label="__('Revenue')" :value="Format::compact($f['total'])" dark>
                {{ __('invoiced in :month', ['month' => $monthName]) }}
                @if($f['change'] !== null)
                    · <span @class(['font-medium', 'text-[#7fc6a1]' => $f['change'] >= 0, 'text-[#e3b341]' => $f['change'] < 0])>{{ $f['change'] >= 0 ? '+' : '−' }}{{ number_format(abs($f['change']) * 100) }}%</span> {{ __('on last month') }}
                @endif
                @if($f['ytd'])
                    · {{ Format::compact($f['ytd']) }} {{ __('year to date') }}
                @endif
            </x-kpi>
            @php
                $band = RateBand::classify($f['realized'], $f['hoursListed']);
            @endphp
            <x-kpi :label="__('Realized rate')" :value="$f['realized'] ? '$'.number_format($f['realized']).'<small>/hr</small>' : '—'" :band="$band && $band['reliable'] ? $band : null">
                @if($band)
                    <span class="rate-chip" style="background: {{ $band['wash'] }}; color: {{ $band['text'] }}; border-color: {{ $band['border'] }}">{{ $band['label'] }}</span>
                    {{ Format::number($f['hoursListed']) }} h
                @else
                    {{ __('no hours to divide by') }}
                @endif
            </x-kpi>
            <x-kpi :label="__('Under $95/hr')" :value="(string) $f['under']['count']" :band="$f['under']['count'] ? RateBand::Bands['low'] : null">
                {{ $f['under']['count'] ? $f['under']['names'] : __('nothing below the line') }}
            </x-kpi>
        </div>

        <section class="card">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="section-title">{{ __('Revenue by month') }} <small class="ml-1 font-sans text-xs font-normal text-muted">{{ $f['year'] }}</small></h2>
                    <p class="section-note">
                        {{ __(':month is the large point · :amount invoiced. Click a month to open its report.', ['month' => $monthName, 'amount' => Format::money($f['total'])]) }}
                        @if($f['estimated']) {{ __('Dashed is an estimate at the year\'s pace: no billing entered yet.') }} @endif
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-4 text-xs text-muted">
                    <span class="flex items-center gap-1.5"><span class="inline-block w-4 border-t-[2.5px] border-navy"></span>{{ __('Billed') }}</span>
                    @if($f['estimated'])
                        <span class="flex items-center gap-1.5"><span class="inline-block w-4 border-t-[2.5px] border-dashed border-[#a9bccb]"></span>{{ __('Estimated') }}</span>
                    @endif
                    <span class="flex items-center gap-1.5"><span class="inline-block w-4 border-t-[1.5px] border-dashed border-muted"></span>{{ $f['year'] - 1 }}</span>
                </div>
            </div>
            <x-chart kind="monthEndRevenue" :data="$f['chart']" height="14rem" class="mt-4" x-on:chart-click="$wire.openMonth($event.detail.index)" />
        </section>

        <section class="card">
            <div class="grid gap-8 lg:grid-cols-2">
                @foreach($f['rings'] as $ring)
                    @include('reports.partials.ring', ['ring' => $ring, 'monthName' => $monthName])
                @endforeach
            </div>
            @php
                $billedSlices = collect($f['rings']['billed']['slices'])->keyBy('label');
            @endphp
            @if(! $billedSlices['Single family']['value'] && ! $billedSlices['Interior']['value'])
                <p class="section-note mt-4"><i class="ph ph-info"></i> <b class="font-medium text-ink-2">{{ __('Single family and Interior bill nothing here') }}</b> — {{ __('no job billed this month carries either division.') }}</p>
            @endif
        </section>

        @include('reports.partials.outstanding', ['outstanding' => $f['outstanding'], 'period' => $period])

        @include('reports.partials.rate-legend')

        <div class="card p-0">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <x-sort-header column="jobName" :$sort :$direction>{{ __('Job') }}</x-sort-header>
                            <x-sort-header column="hoursMonth" :$sort :$direction numeric><span title="{{ __('Hours logged against this job during the month.') }}">{{ __('Hrs (mo)') }}</span></x-sort-header>
                            <x-sort-header column="billedMonth" :$sort :$direction numeric><span title="{{ __('Invoiced this month — phase draws plus hourly.') }}">{{ __('Billed (mo)') }}</span></x-sort-header>
                            <x-sort-header column="rateLive" :$sort :$direction numeric><span title="{{ __('Realized rate to date: everything billed through the period less consultant fees, over every hour ever logged. A month that billed nothing still has a rate.') }}">{{ __('$/hr') }}</span></x-sort-header>
                            <x-sort-header column="contract" :$sort :$direction numeric>{{ __('Contract') }}</x-sort-header>
                            <x-sort-header column="billed" :$sort :$direction numeric>{{ __('Billed') }}</x-sort-header>
                            <x-sort-header column="hourlyBilled" :$sort :$direction numeric><span title="{{ __('Billed to date on hourly work — part of Billed, beside it.') }}">{{ __('Extras') }}</span></x-sort-header>
                            <x-sort-header column="billedPct" :$sort :$direction numeric>{{ __('Billed %') }}</x-sort-header>
                            <x-sort-header column="outstanding" :$sort :$direction numeric>{{ __('Outstanding') }}</x-sort-header>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr class="is-link" wire:key="me-{{ $row['jobNumber'] }}" title="{{ __('Open :job', ['job' => $row['jobName']]) }}" onclick="Livewire.navigate('{{ route('projects.show', $row['jobNumber']) }}')">
                                <td><b class="font-medium text-ink">{{ $row['jobName'] }}</b> <span class="text-xs text-muted">{{ $row['jobNumber'] }}</span></td>
                                <td class="is-num">{!! $row['hoursMonth'] ? e(Format::number($row['hoursMonth'])) : '<span class="empty">—</span>' !!}</td>
                                <td class="is-num">{!! $row['billedMonth'] ? e(Format::compact($row['billedMonth'])) : '<span class="empty">—</span>' !!}</td>
                                <td class="is-num"><x-rate-chip :rate="$row['rateLive']" :hours="$row['hoursLive']" /></td>
                                <td class="is-num">{{ Format::compact($row['contract']) }}</td>
                                <td class="is-num">{{ Format::compact($row['billed']) }}</td>
                                <td class="is-num">{!! $row['hourlyBilled'] ? e(Format::compact($row['hourlyBilled'])) : '<span class="empty">—</span>' !!}</td>
                                <td class="is-num">
                                    @if($row['billedPct'] === null)
                                        <span class="empty">—</span>
                                    @else
                                        @php
                                            $over = $row['billedPct'] > 1.02;
                                            $full = ! $over && $row['billedPct'] >= 0.999;
                                        @endphp
                                        <span class="inline-flex items-center gap-2">
                                            <span class="relative h-1 w-[3.4rem] bg-[#edeff0]"><span class="absolute inset-y-0 left-0" style="width: {{ min(100, $row['billedPct'] * 100) }}%; background: {{ $over ? '#c4694a' : ($full ? '#5f8f6f' : '#8fa9bd') }}"></span></span>
                                            <span @class(['font-medium text-[#b8532f]' => $over])>{{ Format::pct($row['billedPct']) }}</span>
                                        </span>
                                    @endif
                                </td>
                                <td class="is-num">
                                    @if($row['outstanding'])
                                        <span class="{{ PeriodReports::ageClass($row['daysSincePayment']) }}" title="{{ $row['lastPaymentDate'] ? __('last payment :date — :days days before period end', ['date' => Format::date($row['lastPaymentDate']), 'days' => $row['daysSincePayment']]) : __('no payment recorded') }}">{{ Format::compact($row['outstanding']) }}</span>
                                    @else
                                        <span class="empty">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="py-10 text-center text-muted">{{ __('Nothing in this group.') }}</td></tr>
                        @endforelse
                    </tbody>
                    @if($rows->isNotEmpty())
                        @php
                            $hoursLive = $rows->sum('hoursLive');
                            $blended = $hoursLive ? ($rows->sum('billed') - $rows->sum('consultantTotal')) / $hoursLive : null;
                        @endphp
                        <tfoot>
                            <tr>
                                <td>{{ trans_choice(':count job|:count jobs', $rows->count(), ['count' => $rows->count()]) }}</td>
                                <td class="is-num">{{ Format::number($rows->sum('hoursMonth')) }}</td>
                                <td class="is-num">{{ Format::compact($rows->sum('billedMonth')) }}</td>
                                <td class="is-num">{{ $blended === null ? '—' : '$'.number_format($blended) }}</td>
                                <td class="is-num">{{ Format::compact($rows->sum('contract')) }}</td>
                                <td class="is-num">{{ Format::compact($rows->sum('billed')) }}</td>
                                <td class="is-num">{{ Format::compact($rows->sum('hourlyBilled')) }}</td>
                                <td class="is-num">{{ $rows->sum('contract') ? Format::pct($rows->sum('billed') / $rows->sum('contract')) : '—' }}</td>
                                <td class="is-num">{{ Format::compact($rows->sum('outstanding')) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        @include('reports.partials.provenance')
    @endif
</div>
