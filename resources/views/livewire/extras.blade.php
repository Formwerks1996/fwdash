@php
    use App\Livewire\Extras;
    use App\Support\Format;

    $month = $this->month;
    $window = $this->window;
    $rows = $this->jobRows;
    $people = $this->people;
    $matrix = $this->matrix;
    $year = substr((string) $period, 0, 4);
    $short = $period ? \Carbon\CarbonImmutable::parse($period)->format('M Y') : '';
    $signed = fn (float $value) => ($value > 0 ? '+' : '').Extras::compact($value);
@endphp

<div class="space-y-8">
    @if($this->months->isEmpty())
        <div class="card">
            <h1 class="page-title">{{ __('Extras') }}</h1>
            <p class="mt-2 text-sm text-muted">{{ __('No hourly billing has been entered yet.') }}</p>
        </div>
    @else
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="page-title">{{ __('Extras') }}</h1>
                <p class="page-subtitle">
                    {{ __('Hourly billing, month by month') }} · <span class="text-ink">{{ Format::month($period) }}</span>
                    @if(($month['status'] ?? null) === 'closed') · {{ __('closed') }}@endif
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <select wire:model.live="period" class="select" aria-label="{{ __('Month') }}">
                    @foreach($this->months->reverse() as $option)
                        <option value="{{ $option['period'] }}">{{ Format::month($option['period']) }}@if($option['status'] === 'closed') ({{ __('closed') }})@endif</option>
                    @endforeach
                </select>
                <select wire:model.live="range" class="select" aria-label="{{ __('Window') }}">
                    @foreach($this->ranges() as $option)
                        <option value="{{ $option['key'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <a href="{{ route('monthEnd.entry', ['period' => $period, 'from' => 'extras']) }}" class="btn" wire:navigate>
                    {{ __('Enter billing') }} <i class="ph ph-arrow-right"></i>
                </a>
            </div>
        </div>

        <div class="kpis grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
            <x-kpi :label="__('Extras hours')" :value="Extras::hours($month['hours'] ?? null).'<small> h</small>'" dark>
                @if($previous && $previous['hours'])
                    @php $change = ($month['hours'] - $previous['hours']) / $previous['hours']; @endphp
                    <b>{{ $change >= 0 ? '+' : '−' }}{{ number_format(abs($change) * 100) }}%</b>
                    {{ __('on :month (:hours h)', ['month' => \Carbon\CarbonImmutable::parse($previous['period'])->format('M y'), 'hours' => Extras::hours($previous['hours'])]) }}
                @else
                    {{ __('no month before it to compare') }}
                @endif
            </x-kpi>
            <x-kpi :label="__('Extras invoiced')" :value="Extras::compact($month['billed'] ?? null)">
                @if((float) ($month['billed'] ?? 0) && (float) $month['totalBilled'])
                    <b>{{ Format::pct((float) $month['billed'] / (float) $month['totalBilled']) }}</b> {{ __('of the month\'s :total', ['total' => Extras::compact($month['totalBilled'])]) }}
                @else
                    {{ __('nothing invoiced as hourly') }}
                @endif
            </x-kpi>
            <x-kpi :label="__('Billed per line hour')" :value="$month && $month['rate'] !== null ? '$'.number_format($month['rate']).'<small>/hr</small>' : Format::Empty">
                {{ __('what the lines with hours bill, over those hours') }}
            </x-kpi>
            <x-kpi :label="__('Written-off hours')" :value="Extras::hours($month['writtenOffHours'] ?? 0).'<small> h</small>'">
                {{ __('worked and deliberately not billed') }}
                @if(abs((float) ($month['billed'] ?? 0) - (float) ($month['linesTotal'] ?? 0)) >= 0.5)
                    · <b>{{ $signed((float) $month['billed'] - (float) $month['linesTotal']) }}</b> {{ __('off the timesheets') }}
                @endif
            </x-kpi>
            <x-kpi :label="$rangeLabel" :value="Format::number($window->sum('hours')).'<small> h</small>'">
                {{ Extras::compact($window->sum(fn ($row) => (float) $row['billed'])) }} {{ __('invoiced') }}
                @if(Extras::rateOf($window)) · ${{ number_format(Extras::rateOf($window)) }}/hr @endif
            </x-kpi>
        </div>

        <div class="card">
            <h2 class="section-title">{{ __('Month by month') }}</h2>
            <p class="section-note">
                {{ __('Click a month to open it below. Invoiced can fall below what the hours suggest, or go negative: a month carries any flat amounts, caps and credits a job was billed.') }}
            </p>
            <div class="mt-4 grid gap-6 lg:grid-cols-2">
                <div>
                    <div class="text-xs font-medium tracking-[0.1em] text-muted uppercase">{{ __('Extras hours') }} <span class="normal-case tracking-normal">· {{ __('hours on billing lines') }}</span></div>
                    <x-chart kind="extrasBars" :data="$hoursChart" height="13rem" class="mt-2" x-on:chart-click="$wire.selectIndex($event.detail.index)" />
                </div>
                <div>
                    <div class="text-xs font-medium tracking-[0.1em] text-muted uppercase">{{ __('Extras invoiced') }} <span class="normal-case tracking-normal">· {{ __('invoiced as hourly') }}</span></div>
                    <x-chart kind="extrasBars" :data="$billedChart" height="13rem" class="mt-2" x-on:chart-click="$wire.selectIndex($event.detail.index)" />
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="card p-0 xl:col-span-2">
                <div class="toolbar px-6 pt-5 pb-3">
                    <div>
                        <h2 class="section-title">
                            {{ $scope === 'year' ? __(':year, job by job', ['year' => $year]) : __(':month, job by job', ['month' => Format::month($period)]) }}
                        </h2>
                        <p class="section-note">
                            {{ $scope === 'year'
                                ? __('Every month of :year so far, added up per job. Click a job for its months; click a month to open it.', ['year' => $year])
                                : __('Click a job for who logged the time and what was billed against it. The pencil opens that job\'s month on the entry screen.') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="seg">
                            <button type="button" wire:click="setScope('month')" @class(['is-active' => $scope === 'month'])>{{ $short }}</button>
                            <button type="button" wire:click="setScope('year')" @class(['is-active' => $scope === 'year'])>{{ __('Year :year', ['year' => $year]) }}</button>
                        </div>
                        @if($rows->isNotEmpty())
                            <button type="button" class="btn-secondary" wire:click="toggleAll">
                                {{ count($open) >= $rows->count() ? __('Collapse all') : __('Expand all') }}
                            </button>
                        @endif
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Job') }}</th>
                                <th class="is-num">{{ __('Hours') }}</th>
                                <th class="is-num">{{ __('$/hr') }}</th>
                                <th class="is-num" title="{{ __('Flat amounts, caps, top-ups and credits: invoiced as hourly but not from the lines.') }}">{{ __('Off the lines') }}</th>
                                <th class="is-num">{{ __('Invoiced') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $row)
                                @php $isOpen = in_array($row['jobNumber'], $open, true); @endphp
                                <tr class="is-link" wire:key="ex-{{ $scope }}-{{ $row['jobNumber'] }}" wire:click="toggle('{{ $row['jobNumber'] }}')">
                                    <td class="!whitespace-normal">
                                        <div class="flex items-start gap-2">
                                            <i @class(['ph mt-0.5 text-muted', 'ph-caret-down' => $isOpen, 'ph-caret-right' => ! $isOpen])></i>
                                            <div>
                                                <b class="font-medium text-ink">{{ $row['jobName'] }}</b>
                                                <span class="text-xs text-muted">{{ $row['jobNumber'] }}</span>
                                                @if($row['kind'] === 'flat')
                                                    <span class="chip" title="{{ __('Invoiced as a figure with no billing lines behind it.') }}">{{ __('flat, no lines') }}</span>
                                                @elseif($row['kind'] === 'adjusted')
                                                    <span class="chip is-warn" title="{{ __('The staff lines came to :lines; :billed was invoiced.', ['lines' => Format::money($row['linesTotal'], 2), 'billed' => Format::money($row['billed'], 2)]) }}">{{ $signed($row['offLines']) }} {{ __('on the lines') }}</span>
                                                @endif
                                                <div class="text-xs text-muted">{{ $row['developer'] ?? '—' }}@if($row['people']) · {{ implode(', ', $row['people']) }}@endif</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="is-num">{{ $row['hours'] ? Extras::hours($row['hours']) : '' }}@if(! $row['hours'])<span class="empty">—</span>@endif</td>
                                    <td class="is-num">{{ $row['rate'] !== null ? '$'.number_format($row['rate']) : '' }}@if($row['rate'] === null)<span class="empty">—</span>@endif</td>
                                    <td class="is-num">
                                        @if(abs($row['offLines']) >= 0.5)
                                            <span @class(['text-[#20825c]' => $row['offLines'] > 0, 'text-[#b33a32]' => $row['offLines'] < 0])>{{ $signed($row['offLines']) }}</span>
                                        @else
                                            <span class="empty">—</span>
                                        @endif
                                    </td>
                                    <td class="is-num font-medium">{{ Extras::compact($row['billed']) }}</td>
                                    <td class="text-right whitespace-nowrap" wire:click.stop>
                                        <a href="{{ route('monthEnd.entry', ['period' => $period, 'job' => $row['jobNumber'], 'from' => 'extras']) }}" class="px-1 text-muted hover:text-navy" title="{{ __('Open this month on the entry screen') }}" wire:navigate><i class="ph ph-pencil-simple"></i></a>
                                        <a href="{{ route('projects.show', $row['jobNumber']) }}" class="px-1 text-muted hover:text-navy" title="{{ __('Open the project') }}" wire:navigate><i class="ph ph-arrow-right"></i></a>
                                    </td>
                                </tr>

                                @if($isOpen)
                                    <tr wire:key="ex-{{ $scope }}-{{ $row['jobNumber'] }}-detail">
                                        <td colspan="6" class="bg-[#fafbfb] !whitespace-normal">
                                            @if($scope === 'year')
                                                <div class="mb-1 text-xs font-medium tracking-[0.08em] text-ink-2 uppercase">{{ __(':year month by month', ['year' => $year]) }}</div>
                                                <table class="w-full max-w-xl text-xs">
                                                    <thead class="text-muted">
                                                        <tr><th class="py-1 text-left font-normal">{{ __('Month') }}</th><th class="text-right font-normal">h</th><th class="text-right font-normal">$/hr</th><th class="text-right font-normal">{{ __('Invoiced') }}</th></tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($row['months'] as $cell)
                                                            <tr class="cursor-pointer hover:bg-white" wire:click="openMonth('{{ $cell['period'] }}')">
                                                                <td class="py-1">{{ Format::month($cell['period']) }}</td>
                                                                <td class="text-right tabular-nums">{{ $cell['hours'] ? Extras::hours($cell['hours']) : '—' }}</td>
                                                                <td class="text-right tabular-nums">{{ $cell['rate'] !== null ? '$'.number_format($cell['rate']) : '—' }}</td>
                                                                <td class="text-right tabular-nums">{{ Format::money($cell['billed']) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            @else
                                                <div class="mb-1 text-xs font-medium tracking-[0.08em] text-ink-2 uppercase">{{ __('On the billing lines') }}</div>
                                                @if($row['lines'])
                                                    <table class="w-full text-xs">
                                                        <thead class="text-muted">
                                                            <tr>
                                                                <th class="py-1 text-left font-normal">{{ __('Staff') }}</th>
                                                                <th class="text-right font-normal">{{ __('Hours') }}</th>
                                                                <th class="text-right font-normal">{{ __('Rate') }}</th>
                                                                <th class="text-right font-normal">{{ __('Total') }}</th>
                                                                <th class="pl-4 text-left font-normal">{{ __('Note') }}</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($row['lines'] as $line)
                                                                <tr>
                                                                    <td class="py-1">{{ $line['staff'] ?? '—' }}</td>
                                                                    <td class="text-right tabular-nums">{!! $line['hours'] !== null && (float) $line['hours'] ? e(Extras::hours($line['hours'])) : '<span class="empty">—</span>' !!}</td>
                                                                    <td class="text-right tabular-nums">{{ Format::money($line['rate']) }}</td>
                                                                    <td class="text-right tabular-nums">
                                                                        {{ Format::money($line['total'], 2) }}
                                                                        @if($line['amount'] !== null && (float) $line['amount'] == 0)
                                                                            <span class="chip">{{ __('not billed') }}</span>
                                                                        @elseif($line['amount'] !== null)
                                                                            <span class="chip" title="{{ __('A figure typed in place of hours × rate') }}">{{ __('typed') }}</span>
                                                                        @endif
                                                                    </td>
                                                                    <td class="pl-4 text-muted">{{ $line['note'] }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                @else
                                                    <p class="text-xs text-muted">{{ __('No staff lines — billed as one figure, or not billed hourly.') }}</p>
                                                @endif
                                                @if($row['override'] !== null)
                                                    <p class="mt-2 text-xs text-ink-2">{{ __('Invoiced :billed in place of the lines\' :lines.', ['billed' => Format::money($row['billed'], 2), 'lines' => Format::money($row['linesTotal'], 2)]) }}</p>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-muted">{{ __('No extras in :label.', ['label' => $scope === 'year' ? $year : Format::month($period)]) }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($rows->isNotEmpty())
                            @php
                                $rated = $scope === 'year'
                                    ? $this->months->filter(fn ($row) => str_starts_with($row['period'], $year))
                                    : collect([$month]);
                                $ratedHours = $rated->sum('hours');
                            @endphp
                            <tfoot>
                                <tr>
                                    <td>{{ trans_choice(':count job|:count jobs', $rows->count()) }}</td>
                                    <td class="is-num">{{ Extras::hours($rows->sum('hours')) }}</td>
                                    <td class="is-num">{{ $ratedHours ? '$'.number_format($rated->sum(fn ($row) => (float) $row['hoursTotal']) / $ratedHours) : '—' }}</td>
                                    <td class="is-num">{{ $signed($rows->sum('offLines')) }}</td>
                                    <td class="is-num">{{ Extras::compact($rows->sum('billed')) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            <div class="card">
                <h2 class="section-title">{{ __('Who logged it') }}</h2>
                <p class="section-note">{{ __('Hours on the billing lines, by person.') }}</p>
                @php $maxHours = $people->max('hours') ?: 1; @endphp
                <ul class="mt-4 space-y-3">
                    @forelse($people as $person)
                        <li wire:key="ex-person-{{ $person['staff'] }}" title="{{ implode(', ', $person['jobs']) }}">
                            <div class="flex items-baseline justify-between gap-2 text-sm">
                                <span class="text-ink">{{ $person['staff'] }} <span class="text-xs text-muted">{{ trans_choice(':count job|:count jobs', count($person['jobs'])) }}</span></span>
                                <b class="font-medium tabular-nums">{{ Extras::hours($person['hours']) }} h</b>
                            </div>
                            <div class="mt-1 h-1.5 bg-[#edeff0]"><div class="h-full bg-navy" style="width: {{ $person['hours'] / $maxHours * 100 }}%"></div></div>
                        </li>
                    @empty
                        <li class="text-sm text-muted">{{ __('No extras hours logged this month.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="card p-0">
            <div class="toolbar px-6 pt-5 pb-3">
                <div>
                    <h2 class="section-title">{{ __('Where the extras came from') }}</h2>
                    <p class="section-note">
                        {{ $group === 'person' ? __('Hours only: dollars are invoiced per job, not per person.') : __(':label, by job.', ['label' => $rangeLabel]) }}
                        @if($matrix['byYear']) {{ __('Pick a shorter window above to see single months.') }} @endif
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="seg">
                        <button type="button" wire:click="setGroup('job')" @class(['is-active' => $group === 'job'])>{{ __('By job') }}</button>
                        <button type="button" wire:click="setGroup('person')" @class(['is-active' => $group === 'person'])>{{ __('By person') }}</button>
                    </div>
                    @if($group === 'job')
                        <div class="seg">
                            <button type="button" wire:click="setMetric('hours')" @class(['is-active' => $metric === 'hours'])>{{ __('Hours') }}</button>
                            <button type="button" wire:click="setMetric('billed')" @class(['is-active' => $metric === 'billed'])>{{ __('Dollars') }}</button>
                        </div>
                    @endif
                </div>
            </div>

            @php
                $cell = fn ($value) => $matrix['dollars'] ? Extras::compact($value) : Extras::hours($value);
                $shade = fn ($value) => $value > 0 && $matrix['max'] ? 'background: rgba(42,120,214,'.round(0.07 + 0.48 * $value / $matrix['max'], 3).')' : '';
            @endphp
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ $group === 'job' ? __('Job') : __('Person') }}</th>
                            @foreach($matrix['columns'] as $column)
                                <th @class(['is-num', 'bg-gold-soft' => ! $matrix['byYear'] && $column === $period || $matrix['byYear'] && $column === $year])>
                                    @if($matrix['byYear'])
                                        {{ $column }}
                                    @else
                                        <button type="button" wire:click="selectMonth('{{ $column }}')">{{ \Carbon\CarbonImmutable::parse($column)->format('M y') }}</button>
                                    @endif
                                </th>
                            @endforeach
                            <th class="is-num">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($matrix['rows'] as $row)
                            <tr wire:key="ex-matrix-{{ $group }}-{{ $row['key'] }}" @if($row['jobNumber']) class="is-link" onclick="Livewire.navigate('{{ route('projects.show', $row['jobNumber']) }}')" @endif>
                                <td class="max-w-[16rem] truncate">{{ $row['label'] }}</td>
                                @foreach($matrix['columns'] as $column)
                                    @php $value = $row['cells'][$column] ?? 0; @endphp
                                    <td class="is-num" style="{{ $shade($value) }}">
                                        @if(abs($value) >= 0.005)
                                            <span @class(['text-[#b33a32]' => $value < 0])>{{ $cell($value) }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="is-num font-medium">{{ $cell($row['total']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($matrix['columns']) + 2 }}" class="py-10 text-center text-muted">{{ __('No extras in this window.') }}</td></tr>
                        @endforelse
                    </tbody>
                    @if($matrix['count'])
                        <tfoot>
                            <tr>
                                <td>
                                    @if($matrix['count'] > Extras::MatrixTop)
                                        <button type="button" class="text-navy hover:underline" wire:click="$toggle('matrixAll')">
                                            {{ $matrixAll ? __('Show the top :count', ['count' => Extras::MatrixTop]) : __('Show all :count', ['count' => $matrix['count']]) }}
                                        </button>
                                    @else
                                        {{ __('Total') }}
                                    @endif
                                </td>
                                @foreach($matrix['columns'] as $column)
                                    <td class="is-num">{{ $cell($matrix['totals'][$column]) }}</td>
                                @endforeach
                                <td class="is-num">{{ $cell($matrix['total']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <div class="card p-0">
            <div class="px-6 pt-5 pb-3">
                <h2 class="section-title">{{ __('The ledger') }}</h2>
                <p class="section-note">{{ __('One row per month of the window, newest first. Click a month to open it.') }}</p>
            </div>
            @php
                $ledger = $window->reverse()->values();
                $off = fn ($row) => (float) $row['billed'] - (float) $row['linesTotal'];
                $ledgerBilled = $ledger->sum(fn ($row) => (float) $row['billed']);
                $ledgerTotal = $ledger->sum(fn ($row) => (float) $row['totalBilled']);
            @endphp
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('Month') }}</th>
                            <th class="is-num">{{ __('Hours') }}</th>
                            <th class="is-num">{{ __('$/hr') }}</th>
                            <th class="is-num" title="{{ __('Flat amounts, caps, top-ups and credits: invoiced as hourly but not from the lines.') }}">{{ __('Off the timesheets') }}</th>
                            <th class="is-num" title="{{ __('What was invoiced as hourly: the Monthly Billing Report\'s Total Extras.') }}">{{ __('Invoiced') }}</th>
                            <th class="is-num" title="{{ __('Share of everything billed that month, phase draws included.') }}">{{ __('Of billing') }}</th>
                            <th class="is-num">{{ __('Jobs') }}</th>
                            <th class="is-num">{{ __('People') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ledger as $row)
                            <tr wire:key="ex-ledger-{{ $row['period'] }}" @class(['is-link', 'bg-gold-soft' => $row['period'] === $period]) wire:click="selectMonth('{{ $row['period'] }}')">
                                <td>
                                    <b class="font-medium">{{ Format::month($row['period']) }}</b>
                                    @if($row['status'] === 'closed') <span class="chip"><i class="ph ph-lock-simple"></i>{{ __('closed') }}</span> @endif
                                </td>
                                <td class="is-num">{!! $row['hours'] ? e(Extras::hours($row['hours'])) : '<span class="empty">—</span>' !!}</td>
                                <td class="is-num">{!! $row['rate'] !== null ? '$'.e(number_format($row['rate'])) : '<span class="empty">—</span>' !!}</td>
                                <td class="is-num">
                                    @if(abs($off($row)) >= 0.5)
                                        <span @class(['text-[#20825c]' => $off($row) > 0, 'text-[#b33a32]' => $off($row) < 0])>{{ $signed($off($row)) }}</span>
                                    @endif
                                </td>
                                <td class="is-num font-medium">{{ Extras::compact($row['billed']) }}</td>
                                <td class="is-num">{{ (float) $row['totalBilled'] ? Format::pct((float) $row['billed'] / (float) $row['totalBilled']) : '—' }}</td>
                                <td class="is-num">{{ $row['jobs'] ?: '' }}</td>
                                <td class="is-num">{{ $row['people'] ?: '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>{{ trans_choice(':count month|:count months', $ledger->count()) }}</td>
                            <td class="is-num">{{ Format::number($ledger->sum('hours')) }}</td>
                            <td class="is-num">{{ Extras::rateOf($ledger) ? '$'.number_format(Extras::rateOf($ledger)) : '—' }}</td>
                            <td class="is-num">{{ $signed($ledger->sum($off)) }}</td>
                            <td class="is-num">{{ Extras::compact($ledgerBilled) }}</td>
                            <td class="is-num">{{ $ledgerTotal ? Format::pct($ledgerBilled / $ledgerTotal) : '—' }}</td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="notice">
            <p class="font-medium">{{ __('Where the figures come from') }}</p>
            <ul class="mt-1 list-disc space-y-1 pl-5">
                <li>{{ __('Hours are the staff lines typed at month end; dollars are what was invoiced as hourly billing (time and materials, which the workbook calls Extras — not change orders).') }}</li>
                <li>{{ __('$/hr divides what the lines with hours bill by those hours, so flat-fee and capped months do not read as rates nobody charged.') }}</li>
                <li>{{ __('A line with hours and an amount of 0 is time deliberately not billed: it stays in the hours and is counted as written-off hours.') }}</li>
                <li>{{ __('Off the timesheets is what was invoiced as hourly less what the lines add up to: flat amounts, caps, top-ups and credits.') }}</li>
            </ul>
        </div>
    @endif
</div>
