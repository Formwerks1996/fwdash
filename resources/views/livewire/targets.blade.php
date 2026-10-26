@php
    use App\Support\Format;

    $f = $this->figures;
    $months = $this->months;
    $signed = fn (?float $value) => $value === null ? '—' : ($value >= 0 ? '+' : '').Format::compact($value);
    $whole = fn (?float $value) => $value === null ? '' : number_format($value);
@endphp

<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="page-title">{{ __('Monthly Targets') }}</h1>
            <p class="page-subtitle">
                {{ __('What each month should invoice, against what it did') }}
                @if($f['lastEntered'])
                    · {{ __('billing entered to :month', ['month' => Format::month($f['lastEntered'])]) }}
                @endif
            </p>
        </div>

        <label class="flex items-center gap-2 text-xs text-muted">
            {{ __('Year') }}
            <select class="select" wire:change="setYear($event.target.value)">
                @foreach($this->years as $option)
                    <option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="kpis grid-cols-1 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi :label="__(':year target', ['year' => $year])" :value="Format::compact($f['yearTarget'])" dark>
            @if($f['targetCount'])
                {{ __(':count of 12 months set', ['count' => $f['targetCount']]) }}
            @else
                {{ __('type a target into any month below') }}
            @endif
        </x-kpi>
        <x-kpi :label="__('Invoiced vs target, to date')" :value="$f['doneTarget'] ? number_format($f['doneBilled'] / $f['doneTarget'] * 100).'%' : '—'">
            @if($f['doneCount'])
                {{ Format::compact($f['doneBilled']) }} {{ __('of') }} {{ Format::compact($f['doneTarget']) }} · <b>{{ $signed($f['doneBilled'] - $f['doneTarget']) }}</b>
            @else
                {{ __('no billed month has a target yet') }}
            @endif
        </x-kpi>
        <x-kpi :label="__('Months on target')" :value="$f['doneCount'] ? $f['hits'].' <small>'.__('of').' '.$f['doneCount'].'</small>' : '—'">
            {{ __('billed months that met their target') }} · {{ Format::compact($f['ytd']) }} {{ __('invoiced this year') }}
        </x-kpi>
        <x-kpi :label="__('Year on current pace')" :value="Format::compact($f['projected'] ?: null)">
            @if($f['projectedGap'] !== null)
                <b>{{ $signed($f['projectedGap']) }}</b> {{ __('against the year\'s target') }}
            @else
                {{ __('the Overview’s projection: billed months, then the run rate') }}
            @endif
        </x-kpi>
    </div>

    <div class="card">
        <h2 class="section-title">{{ __('Invoiced against target') }}</h2>
        <p class="section-note">{{ __('Bars are what was invoiced; pale bars are months with no billing entered yet, estimated at the year\'s run rate. The dashed line is the target.') }}</p>
        <x-chart kind="targets" :data="$f['chart']" height="16rem" class="mt-4" />
    </div>

    <div class="card p-0">
        <div class="toolbar px-6 pt-5 pb-4">
            <div>
                <h2 class="section-title">{{ __('Month by month') }}</h2>
                <p class="section-note">{{ __('Type a target and press Enter or click away to save. "300k" and "$300,000" both work; clear the box to remove a target.') }}</p>
            </div>
            @if($canEdit)
                <div class="flex flex-wrap items-center gap-2" x-data>
                    <input type="text" inputmode="decimal" class="field-input w-36 py-1" placeholder="{{ __('e.g. 300,000') }}" aria-label="{{ __('Amount for every month') }}" wire:model="fillAmount">
                    <button type="button" class="btn-secondary" wire:click="fillMonths(false)">{{ __('Fill empty months') }}</button>
                    <button
                        type="button"
                        class="btn-secondary"
                        x-on:click="if (! {{ $f['existingCount'] }} || confirm(@js(__('Replace :count existing target(s) with', ['count' => $f['existingCount']])) + ' ' + ($wire.fillAmount || '') + '?')) { $wire.fillMonths(true) }"
                    >{{ __('Set all 12') }}</button>
                </div>
            @endif
        </div>

        @error('target') <p class="error-note mx-6 mb-3">{{ $message }}</p> @enderror

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Month') }}</th>
                        <th class="is-num">{{ __('Target') }}</th>
                        <th class="is-num">{{ __('Invoiced') }}</th>
                        <th class="is-num">{{ __('vs target') }}</th>
                        <th>{{ __('Progress') }}</th>
                        <th class="is-num" title="{{ __('Payments received in the month') }}">{{ __('Collected') }}</th>
                        <th>{{ __('Note') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($months as $month)
                        @php
                            $progress = $month['target'] && $month['shown'] !== null ? $month['shown'] / $month['target'] : null;
                        @endphp
                        <tr wire:key="target-{{ $month['key'] }}" @class(['bg-gold-soft/50' => $month['current']])>
                            <td>
                                <b class="font-medium">{{ $month['name'] }}</b>
                                @if($month['current'])
                                    <span class="chip">{{ __('this month') }}</span>
                                @elseif(! $month['actual'] && $month['complete'])
                                    <span class="chip is-warn" title="{{ __('The month is over but month end has not been entered.') }}">{{ __('not billed yet') }}</span>
                                @endif
                            </td>
                            <td class="is-num">
                                <input
                                    type="text"
                                    inputmode="decimal"
                                    class="cell-input"
                                    placeholder="—"
                                    aria-label="{{ __('Target for :month', ['month' => $month['name']]) }}"
                                    value="{{ $whole($month['target']) }}"
                                    @disabled(! $canEdit)
                                    x-on:keydown.enter="$event.target.blur()"
                                    wire:change="saveTarget('{{ $month['key'] }}', $event.target.value)"
                                >
                            </td>
                            <td class="is-num">
                                @if($month['actual'])
                                    <b class="font-medium">{{ Format::compact($month['shown']) }}</b>
                                @elseif($month['estimate'])
                                    <span class="text-muted" title="{{ __('At this year’s pace, as on the Overview') }}">~{{ Format::compact($month['shown']) }} <small>{{ __('at pace') }}</small></span>
                                @else
                                    <span class="empty">—</span>
                                @endif
                            </td>
                            <td @class(['is-num', 'text-muted' => $month['estimate']])>
                                {{ $month['target'] !== null && $month['shown'] !== null ? $signed($month['shown'] - $month['target']) : '—' }}
                            </td>
                            <td>
                                @if($progress !== null)
                                    <span class="inline-flex items-center gap-2">
                                        <span class="bar-track w-24">
                                            <span class="bar-fill" style="width: {{ min(100, $progress * 100) }}%; background: {{ $month['estimate'] ? '#d6e1ea' : ($progress >= 1 ? '#3f7d4f' : '#00263E') }}"></span>
                                        </span>
                                        <span @class(['text-xs', 'text-muted' => $month['estimate']])>{{ number_format($progress * 100) }}%</span>
                                    </span>
                                @else
                                    <span class="empty">—</span>
                                @endif
                            </td>
                            <td class="is-num">{{ Format::compact($month['collected']) }}</td>
                            <td>
                                <input
                                    type="text"
                                    class="cell-input w-56 text-left"
                                    placeholder="{{ __('add a note') }}"
                                    value="{{ $month['note'] }}"
                                    @disabled(! $canEdit || $month['target'] === null)
                                    x-on:keydown.enter="$event.target.blur()"
                                    wire:change="saveNote('{{ $month['key'] }}', $event.target.value)"
                                >
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>{{ $year }}</td>
                        <td class="is-num">{{ Format::compact($f['yearTarget']) }}</td>
                        <td class="is-num">{{ Format::compact($f['ytd']) }} <span class="text-xs font-normal text-muted">{{ __('billed months') }}</span></td>
                        <td class="is-num">
                            {{ $f['doneCount'] ? $signed($f['doneBilled'] - $f['doneTarget']) : '—' }}
                            <div class="text-xs font-normal text-muted">{{ __('billed months with a target') }}</div>
                        </td>
                        <td></td>
                        <td class="is-num">{{ Format::compact($f['collected']) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <p class="section-note">
        {{ __('Invoiced is what month end billed, as on the Overview. Targets are kept in the dashboard only: they are not part of the workbook export or import, so they survive an upload but need their own backup.') }}
    </p>
</div>
