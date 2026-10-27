@php
    use App\Billing\ProjectBurn;
    use App\Support\Format;

    $report = $this->report;
    $flags = [
        'over' => ['ph-fill ph-warning-octagon', __('Over budget'), 'bg-[#fbe7e1] text-[#a8432a]', '#c25a3a'],
        'warn' => ['ph-fill ph-warning', __('Nearing budget'), 'bg-[#fbf1da] text-[#8a6a15]', '#d9a21b'],
        'ok' => ['ph ph-check-circle', __('On track'), 'bg-[#e4f1ea] text-[#2f6b52]', '#2f8a67'],
    ];
    $ago = fn (?int $months) => match (true) {
        $months === null => '—',
        $months === 0 => __('as-of month'),
        $months === 1 => __('1 month ago'),
        default => __(':count months ago', ['count' => $months]),
    };
@endphp

<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="page-title">{{ __('Project Alerts') }}</h1>
            <p class="page-subtitle">
                {{ __('Active projects whose hours are at or past 80% of what the fee pays for') }}@if($assignedOnly){{ __(', limited to your projects') }}@endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-[0.9rem] text-[0.82rem] text-ink-2">
            <label class="flex items-center gap-2">
                {{ __('Active = hours logged in') }}
                <select wire:model.live="months" class="select">
                    @foreach($windows as $value => $label)
                        <option value="{{ $value }}">{{ __($label) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex cursor-pointer items-center gap-1.5">
                <input type="checkbox" wire:model.live="paused" class="accent-navy"> {{ __('include paused') }}
            </label>
            <label class="flex cursor-pointer items-center gap-1.5">
                <input type="checkbox" wire:model.live="onTrack" class="accent-navy"> {{ __('show on-track projects') }}
            </label>
        </div>
    </div>

    @if($asOf === null)
        <div class="card text-sm text-muted">{{ __('No hours uploaded yet. Upload a workbook with an Hours sheet.') }}</div>
    @else
        <div class="-mt-5 flex flex-wrap items-center gap-2 text-xs text-muted">
            <span>
                {{ __('Hours are as of :month', ['month' => Format::month($asOf)]) }}@if($uploadedAt) {{ __('from the workbook uploaded :date', ['date' => Format::date((string) $uploadedAt)]) }}@endif.
                {{ __('Plan rate') }}
            </span>
            @if($editingRate)
                <form wire:submit="saveRate" class="flex items-center gap-1">
                    $<input type="number" step="1" min="20" max="500" wire:model="planRate" class="cell-input w-[4.5rem]" aria-label="{{ __('Plan rate per hour') }}" autofocus>/h
                    <button type="submit" class="btn-secondary">{{ __('Save') }}</button>
                    <button type="button" class="btn-secondary" wire:click="$set('editingRate', false)">{{ __('Cancel') }}</button>
                </form>
                @error('planRate') <span class="text-[#a8432a]">{{ $message }}</span> @enderror
            @else
                <span>{{ Format::money($rate) }}/h.</span>
                @if($canEdit)
                    <button type="button" class="text-gold-text underline-offset-2 hover:underline" wire:click="editRate">{{ __('Change') }}</button>
                @endif
            @endif
        </div>

        <div class="kpis grid-cols-1 sm:grid-cols-2 xl:grid-cols-4">
            <x-kpi :label="__('Over budget')" :value="$overCount" :band="['fill' => '#c25a3a', 'text' => '#ba5638']">
                {{ __('projects past 100% of their fee-hours') }}
            </x-kpi>
            <x-kpi :label="__('Nearing budget')" :value="$warnCount" :band="['fill' => '#d9a21b', 'text' => '#986b26']">
                {{ __('projects at 80%–99%, or well ahead of billing') }}
            </x-kpi>
            <x-kpi :label="__('Overrun so far')" :value="Format::compact($overrun)" :band="$overrun > 0 ? ['fill' => 'transparent', 'text' => '#ba5638'] : null">
                {{ __('hours beyond the fee, at the plan rate, across the red projects') }}
            </x-kpi>
            <x-kpi :label="__('Projects flagged')" :value="$flaggedCount">
                {{ __('of :count active projects watched, :window', ['count' => $report['watched'], 'window' => __($windows[$months] ?? $windows[3])]) }}
            </x-kpi>
        </div>

        <div class="card p-0">
            <div class="px-6 pt-5 pb-4">
                <h2 class="section-title">{{ $onTrack ? __('All active projects') : __('Flagged projects') }}</h2>
                <p class="section-note mt-1">
                    <b class="font-medium text-ink-2">{{ __('Hours') }}</b> {{ __('are everything logged against the project, including opening hours from before the system.') }}
                    <b class="font-medium text-ink-2">{{ __('Budget used') }}</b> {{ __('is hours against the hours the fee pays for at the plan rate.') }}
                    <b class="font-medium text-ink-2">{{ __('Billed') }}</b> {{ __('is how much of the fee has been invoiced; when hours run well ahead of it, the project is burning faster than it is progressing. Click a row to open the project.') }}
                </p>
            </div>

            @if($shown->isEmpty())
                <p class="flex items-center gap-2 px-6 pb-6 text-sm text-[#2f6b52]"><i class="ph ph-check-circle"></i>{{ __('No active project is at 80% of its fee-hours or more.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Project') }}</th>
                                <th>{{ __('Phase now') }}</th>
                                <th class="is-num">{{ __('Fee') }}</th>
                                <th class="is-num">{{ __('Hours') }}</th>
                                <th>{{ __('Budget used') }}</th>
                                <th class="is-num">{{ __('Left') }}</th>
                                <th class="is-num">{{ __('Billed') }}</th>
                                <th>{{ __('Last hours') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($shown as $row)
                                @php
                                    [$icon, $label, $chip, $color] = $flags[$row['flag']];
                                    $used = (float) $row['used'];
                                    $scale = max(1.2, min($used, 2));
                                    $left = $row['feeHours'] - $row['hours'];
                                    $url = route('projects.show', $row['jobNumber']);
                                @endphp
                                <tr
                                    wire:key="al-{{ $row['jobNumber'] }}"
                                    class="is-link align-top"
                                    tabindex="0"
                                    x-on:click="Livewire.navigate(@js($url))"
                                    x-on:keydown.enter="Livewire.navigate(@js($url))"
                                >
                                    <td @if($row['flag'] !== 'ok') style="box-shadow: inset 0 2px 0 {{ $color }}" @endif>
                                        <span class="inline-flex items-center gap-1 rounded-full px-[0.55rem] py-[0.22rem] text-xs font-medium whitespace-nowrap {{ $chip }}"><i class="{{ $icon }}"></i>{{ $label }}</span>
                                    </td>
                                    <td class="whitespace-normal">
                                        <b class="font-medium">{{ $row['jobNumber'] }}</b> {{ $row['jobName'] }}
                                        <div class="mt-0.5 max-w-[26rem] text-xs text-muted">
                                            {{ $row['developer'] ?? '' }}
                                            @if($row['status'] === 'paused') <span class="chip">{{ __('paused') }}</span> @endif
                                        </div>
                                    </td>
                                    <td>
                                        <b class="font-medium">{{ $row['currentPhase'] ?? '—' }}</b>
                                        <div class="mt-0.5 text-xs text-muted">{{ $row['phaseCount'] ? __(':n of :m phases billed', ['n' => $row['phasesBilled'], 'm' => $row['phaseCount']]) : '—' }}</div>
                                    </td>
                                    <td class="is-num">
                                        {{ Format::money($row['fee']) }}
                                        <div class="mt-0.5 text-xs text-muted">{{ Format::number($row['feeHours']) }} h</div>
                                    </td>
                                    <td class="is-num">
                                        {{ Format::number($row['hours']) }} h
                                        <div class="mt-0.5 text-xs text-muted">{{ __(':hours h in window', ['hours' => Format::number($row['recentHours'])]) }}</div>
                                    </td>
                                    <td>
                                        <div class="flex min-w-[9.5rem] items-center gap-2" title="{{ __(':hours h of :fee h the fee pays for', ['hours' => Format::number($row['hours']), 'fee' => Format::number($row['feeHours'])]) }}">
                                            <span class="relative h-2 flex-1 rounded-full bg-[#eef0f2]">
                                                <i class="absolute inset-y-0 left-0 rounded-full" style="width: {{ min($used, $scale) / $scale * 100 }}%; background: {{ $color }}"></i>
                                                <i class="absolute -top-[0.18rem] -bottom-[0.18rem] w-px bg-[#b8bdc4]" style="left: {{ 0.8 / $scale * 100 }}%"></i>
                                                <i class="absolute -top-[0.18rem] -bottom-[0.18rem] w-px bg-[#5b616a]" style="left: {{ 1 / $scale * 100 }}%"></i>
                                            </span>
                                            <span @class(['min-w-[2.6rem] text-right text-[0.8rem] tabular-nums', 'font-medium' => $row['flag'] !== 'ok']) @if($row['flag'] !== 'ok') style="color: {{ $row['flag'] === 'over' ? '#a8432a' : '#8a6a15' }}" @endif>{{ ProjectBurn::burnPct($used) }}</span>
                                        </div>
                                    </td>
                                    <td class="is-num">
                                        @if($left < 0)
                                            <span class="font-medium text-[#a8432a]">−{{ Format::number(abs($left)) }} h</span>
                                        @else
                                            {{ Format::number($left) }} h
                                        @endif
                                    </td>
                                    <td class="is-num">
                                        {{ Format::pct($row['progress']) }}
                                        @if($used - (float) $row['progress'] > ProjectBurn::SpendAheadBy && $row['progress'] < 1)
                                            <span class="ml-0.5 inline-block rounded-full bg-[#fbe7e1] px-[0.38rem] py-[0.08rem] text-xs text-[#a8432a]" title="{{ __('Hours have used :burn of the fee but only :billed has been billed', ['burn' => ProjectBurn::burnPct($used), 'billed' => Format::pct($row['progress'])]) }}">{{ __('spend ahead') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ Format::shortMonth($row['lastHoursPeriod']) }}
                                        <div class="mt-0.5 text-xs text-muted">{{ $ago($row['monthsAgo']) }}</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if($report['noFee']->isNotEmpty())
            <div class="card p-0">
                <div class="px-6 pt-5 pb-4">
                    <h2 class="section-title">{{ __('Active projects with no fee') }}</h2>
                    <p class="section-note mt-1">{{ __('Hours are being logged against these projects but the contract ladder has no fee for them, so there is nothing to measure against. Either the fee is missing or the work is unbudgeted.') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Project') }}</th>
                                <th class="is-num">{{ __('Hours') }}</th>
                                <th class="is-num">{{ __('Hours in window') }}</th>
                                <th>{{ __('Last hours') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['noFee'] as $row)
                                @php $url = route('projects.show', $row['jobNumber']); @endphp
                                <tr wire:key="al-nofee-{{ $row['jobNumber'] }}" class="is-link" tabindex="0" x-on:click="Livewire.navigate(@js($url))" x-on:keydown.enter="Livewire.navigate(@js($url))">
                                    <td><b class="font-medium">{{ $row['jobNumber'] }}</b> {{ $row['jobName'] }}</td>
                                    <td class="is-num">{{ Format::number($row['hours']) }} h</td>
                                    <td class="is-num">{{ Format::number($row['recentHours']) }} h</td>
                                    <td>{{ Format::shortMonth($row['lastHoursPeriod']) }} <span class="text-xs text-muted">· {{ $ago($row['monthsAgo']) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <p class="flex items-start gap-2 text-[0.78rem] text-ink-2">
            <i class="ph ph-info mt-0.5"></i>
            <span>{{ __("Hours come from the Hours sheet of the uploaded workbook, one total per project per month; there is no phase or person detail, so this page watches whole projects. 'Budget used' prices hours at the plan rate (:rate/h), not at anyone's pay, and ignores consultants. 'Phase now' is the first phase not yet fully billed.", ['rate' => Format::money($rate)]) }}</span>
        </p>
    @endif
</div>
