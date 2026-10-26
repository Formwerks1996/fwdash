@php
    use App\Support\Format;
    use App\Support\RateBand;

    $totals = $this->totals;
    $rows = $this->rows;
    $blendedHours = $rows->sum('hours');
    $blended = $blendedHours ? ($rows->sum('billed') - $rows->sum('consultantTotal')) / $blendedHours : null;
@endphp

<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="page-title">{{ __('Invoicing') }}</h1>
            <p class="page-subtitle">
                {{ __('Billing period') }} · <span class="text-ink">{{ Format::month($period) }}</span>
                · {{ __('calculated live from month-end entries') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <label class="flex items-center gap-2 text-xs text-muted">
                {{ __('Period') }}
                <select wire:model.live="period" class="select">
                    @foreach($periods as $option)
                        <option value="{{ $option }}">
                            {{ Format::month($option) }}@if($engine->periodStatus($option) === 'closed') ({{ __('closed') }})@endif
                        </option>
                    @endforeach
                </select>
            </label>
            @if($period)
                <a href="{{ route('reports.billing', $period) }}" target="_blank" class="btn-secondary" title="{{ __('The month\'s billing, job by job. Print it or save it as a PDF.') }}">
                    <i class="ph ph-file-text"></i>{{ __('Billing report') }}
                </a>
            @endif
        </div>
    </div>

    <div class="kpis grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        <x-kpi :label="__('Billed this month')" :value="Format::money($totals['billedThisMonth'])" dark>
            {{ Format::money($totals['extrasThisMonth']) }} {{ __('of that is hourly') }}
        </x-kpi>
        <x-kpi :label="__('Ongoing contract value')" :value="Format::money($totals['contract'])">
            {{ $this->ongoing->count() }} {{ __('ongoing projects') }}, {{ $totals['divisions'] }} {{ __('divisions') }} · {{ $completedCount }} {{ __('completed hidden') }}
        </x-kpi>
        <x-kpi :label="__('Billed to date')" :value="Format::money($totals['billed'])">
            <b>{{ Format::pct($totals['contract'] ? $totals['billed'] / $totals['contract'] : null, 1) }}</b> {{ __('of contract value') }}
        </x-kpi>
        <x-kpi :label="__('Fee remaining')" :value="Format::money($totals['balance'])">
            {{ Format::money($totals['balanceDesign']) }} {{ __('design') }} · {{ Format::money($totals['balanceCA']) }} {{ __('CA') }}
        </x-kpi>
        <x-kpi :label="__('Billed, no payment recorded')" :value="Format::money($totals['outstanding'])">
            {{ __('billed minus payments on :count projects · not money owed: many old jobs have no payments entered', ['count' => $totals['owingCount']]) }}
        </x-kpi>
        <x-kpi :label="__('Hours this month')" :value="Format::number($totals['hoursThisMonth'])">
            {{ Format::number($totals['hours']) }} {{ __('hours to date') }}
        </x-kpi>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <h2 class="section-title">{{ __('Activity') }}</h2>
            <p class="section-note">{{ __('Hours logged against payments received, last 18 months.') }}</p>
            <x-chart kind="activity" :data="$this->activity" class="mt-4" />
        </div>

        <div class="card">
            <h2 class="section-title">{{ __('Billing exceptions') }}</h2>
            <dl class="mt-4 divide-y divide-line text-sm">
                <div class="flex items-center justify-between py-3">
                    <dt class="text-ink-2">{{ __('Billed past contract') }}</dt>
                    <dd class="font-heading text-xl text-navy">{{ $totals['overContract'] }}</dd>
                </div>
                <div class="flex items-center justify-between py-3">
                    <dt class="text-ink-2">{{ __('Active, billed, no contract amount') }}</dt>
                    <dd class="font-heading text-xl text-navy">
                        {{ collect($engine->allSummaries())->filter(fn ($row) => $row['active'] && (float) $row['contract'] == 0 && (float) $row['billed'] != 0)->count() }}
                    </dd>
                </div>
                <div class="flex items-center justify-between py-3">
                    <dt class="text-ink-2">{{ __('Nothing billed this month') }}</dt>
                    <dd class="font-heading text-xl text-navy">{{ $totals['nothingThisMonth'] }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="card p-0">
        <div class="toolbar px-6 pt-5 pb-4">
            <div class="relative w-full max-w-sm">
                <i class="ph ph-magnifying-glass absolute top-1/2 left-3 -translate-y-1/2 text-muted"></i>
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="{{ __('Search developer, project, job #, lead') }}" class="search-input">
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <select wire:model.live="division" class="select">
                    <option value="">{{ __('All divisions') }}</option>
                    @foreach($allDivisions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                <select wire:model.live="flag" class="select">
                    <option value="all">{{ __('All projects') }}</option>
                    <option value="billed">{{ __('Billed this month') }}</option>
                    <option value="over">{{ __('Past contract') }}</option>
                    <option value="owing">{{ __('Payment outstanding') }}</option>
                </select>
                <span class="text-xs text-muted">{{ $rows->count() }} {{ __('of') }} {{ $this->ongoing->count() }} {{ __('projects') }}</span>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 px-6 pb-3 text-xs text-muted">
            @foreach(RateBand::Bands as $band)
                <span class="inline-flex items-center gap-1"><span class="size-2.5" style="background: {{ $band['fill'] }}"></span>{{ $band['label'] }}/h</span>
            @endforeach
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <x-sort-header column="jobNumber" :$sort :$direction>{{ __('Job #') }}</x-sort-header>
                        <x-sort-header column="jobName" :$sort :$direction>{{ __('Project') }}</x-sort-header>
                        <x-sort-header column="division" :$sort :$direction>{{ __('Div') }}</x-sort-header>
                        <x-sort-header column="ratePerHour" :$sort :$direction numeric>{{ __('$/hr') }}</x-sort-header>
                        <x-sort-header column="billedThisMonth" :$sort :$direction numeric>{{ __('This month') }}</x-sort-header>
                        <x-sort-header column="contract" :$sort :$direction numeric>{{ __('Contract') }}</x-sort-header>
                        <x-sort-header column="billed" :$sort :$direction numeric>{{ __('Billed') }}</x-sort-header>
                        <x-sort-header column="billedPct" :$sort :$direction numeric>{{ __('% Billed') }}</x-sort-header>
                        <x-sort-header column="balance" :$sort :$direction numeric>{{ __('Balance') }}</x-sort-header>
                        <x-sort-header column="hours" :$sort :$direction numeric>{{ __('Hours') }}</x-sort-header>
                        <x-sort-header column="outstanding" :$sort :$direction numeric>{{ __('Owing') }}</x-sort-header>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr class="is-link" wire:key="inv-{{ $row['jobNumber'] }}" onclick="Livewire.navigate('{{ route('projects.show', $row['jobNumber']) }}')">
                            <td class="text-muted">{{ $row['jobNumber'] }}</td>
                            <td>
                                <a href="{{ route('projects.show', $row['jobNumber']) }}" class="font-medium text-ink hover:text-navy" wire:navigate>{{ $row['jobName'] }}</a>
                                <div class="text-xs text-muted">{{ $row['developer'] }}</div>
                            </td>
                            <td>{{ $row['division'] ?? '—' }}</td>
                            <td class="is-num"><x-rate-chip :rate="$row['ratePerHour']" :hours="$row['hours']" /></td>
                            <td class="is-num">{!! $row['billedThisMonth'] ? e(Format::money($row['billedThisMonth'])) : '<span class="empty">—</span>' !!}</td>
                            <td class="is-num">{{ Format::money($row['contract']) }}</td>
                            <td class="is-num">{{ Format::money($row['billed']) }}</td>
                            <td class="is-num">
                                @if($row['billedPct'] === null)
                                    <span class="text-warning">{{ __('no contract') }}</span>
                                @else
                                    <span class="inline-flex items-center gap-2">
                                        <span class="bar-track"><span @class(['bar-fill', 'is-over' => $row['billedPct'] > 1]) style="width: {{ min(100, $row['billedPct'] * 100) }}%"></span></span>
                                        <span @class(['text-over' => $row['billedPct'] > 1])>{{ Format::pct($row['billedPct']) }}</span>
                                    </span>
                                @endif
                            </td>
                            <td class="is-num">{{ Format::money($row['balance']) }}</td>
                            <td class="is-num">{{ Format::number($row['hours']) }}</td>
                            <td class="is-num">{{ Format::money($row['outstanding']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-10 text-center text-muted">{{ __('No projects match.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td colspan="3">{{ __('Subtotal — :count projects', ['count' => $rows->count()]) }}</td>
                            <td class="is-num"><x-rate-chip :rate="$blended" :hours="$blendedHours" /></td>
                            <td class="is-num">{{ Format::money($rows->sum('billedThisMonth')) }}</td>
                            <td class="is-num">{{ Format::money($rows->sum('contract')) }}</td>
                            <td class="is-num">{{ Format::money($rows->sum('billed')) }}</td>
                            <td></td>
                            <td class="is-num">{{ Format::money($rows->sum('balance')) }}</td>
                            <td class="is-num">{{ Format::number($blendedHours) }}</td>
                            <td class="is-num">{{ Format::money($rows->sum('outstanding')) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <p class="section-note">
        {{ __('Ongoing means active with fee still to bill. Hourly billing is part of "Billed" and uses up the contract balance, so a project can read as past contract on time and materials alone. $/hr is billed less consultant fees, over every hour logged.') }}
    </p>
</div>
