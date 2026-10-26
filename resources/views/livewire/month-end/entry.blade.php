@php
    use App\Support\Format;

    $worklist = $this->worklist;
    $grid = $this->grid;
    $total = count($worklist['jobs']);
    $done = $total - $worklist['outstandingCount'];
    $monthName = Format::month($period);
    $editable = $canEdit && ! $closed;
    $back = $from === 'extras'
        ? ['label' => __('Extras'), 'url' => route('extras', ['period' => $period])]
        : ['label' => __('Month-end report'), 'url' => route('monthEnd', ['period' => $period])];
    $pct = fn ($value) => $value === null ? '' : rtrim(rtrim(number_format((float) $value * 100, 2, '.', ''), '0'), '.');
@endphp

<div
    class="space-y-6"
    x-data
    x-on:keydown.window="
        if ($event.metaKey || $event.ctrlKey || $event.altKey || ['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement?.tagName)) return;
        if ($event.key !== 'j' && $event.key !== 'k') return;
        const rows = [...$root.querySelectorAll('.me-layout [data-job]')];
        if (! rows.length) return;
        const current = rows.findIndex((row) => row.classList.contains('is-active'));
        const next = $event.key === 'j' ? Math.min(rows.length - 1, current + 1) : Math.max(0, current < 0 ? 0 : current - 1);
        $event.preventDefault();
        rows[next].click();
        rows[next].scrollIntoView({ block: 'nearest' });
    "
>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ $back['url'] }}" class="btn-secondary mb-3" wire:navigate><i class="ph ph-arrow-left"></i>{{ $back['label'] }}</a>
            <h1 class="page-title">{{ __('Month end') }}</h1>
            <p class="page-subtitle">{{ $monthName }}@if($closed) · <strong class="font-medium text-ink-2">{{ __('closed') }}</strong>@endif</p>
        </div>

        <div class="flex flex-wrap items-end gap-6">
            <div class="min-w-[16rem] text-sm">
                <div class="text-ink-2">
                    <b class="font-medium text-ink">{{ __(':done of :total jobs billed', ['done' => $done, 'total' => $total]) }}</b>
                    · {{ Format::money($worklist['billed']) }} {{ __('billed this month') }}
                    @if($worklist['outstandingCount'] > 0) · {{ __(':count to go', ['count' => $worklist['outstandingCount']]) }} @endif
                </div>
                <div class="mt-2 h-1.5 bg-[#edeff0]" role="progressbar" aria-label="{{ __('Jobs billed this month') }}" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $done }}">
                    <div class="h-full bg-positive" style="width: {{ $total ? $done / $total * 100 : 0 }}%"></div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <select class="select" aria-label="{{ __('Billing period') }}" x-on:change="$wire.changePeriod($event.target.value)">
                    @unless(in_array($period, $periods, true))
                        <option value="{{ $period }}" selected>{{ $monthName }}</option>
                    @endunless
                    @foreach($periods as $option)
                        <option value="{{ $option }}" @selected($option === $period)>{{ Format::month($option) }}@if($engine->periodStatus($option) === 'closed') ({{ __('closed') }})@endif</option>
                    @endforeach
                </select>
                @if($canEdit && ! $closed)
                    <button type="button" class="btn-secondary" wire:click="closePeriod" wire:confirm="{{ __('Close :month? Nothing billed in it can be changed until it is reopened.', ['month' => $monthName]) }}">
                        <i class="ph ph-lock"></i>{{ __('Close month') }}
                    </button>
                @endif
            </div>
        </div>
    </div>

    @if(! $closed && $total > 0 && $worklist['outstandingCount'] === 0)
        <div class="note flex flex-wrap items-center justify-between gap-3" role="status">
            <span><i class="ph ph-check-circle text-positive"></i> <b class="font-medium text-ink">{{ __(':month is fully billed.', ['month' => $monthName]) }}</b> {{ Format::money($worklist['billed']) }} {{ trans_choice('across :count job.|across :count jobs.', $total, ['count' => $total]) }}</span>
            <a href="{{ route('monthEnd', ['period' => $period]) }}" class="btn" wire:navigate>{{ __('Open the month-end report') }}</a>
        </div>
    @endif

    @if($closed)
        <div class="notice flex flex-wrap items-center justify-between gap-3">
            <span>{{ __('This period is closed, so nothing can be changed. Reopen it to edit.') }}</span>
            @if($canEdit)
                <button type="button" class="btn-secondary" wire:click="reopenPeriod" wire:confirm="{{ __('Reopen :month? Its phase draws and hour lines become editable again.', ['month' => $monthName]) }}">
                    <i class="ph ph-lock-open"></i>{{ __('Reopen') }}
                </button>
            @endif
        </div>
    @endif

    @if($errors->any())
        <div class="notice" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="me-layout grid items-start gap-5 lg:grid-cols-[minmax(240px,320px)_1fr]">
        <div class="card flex max-h-[calc(100vh-260px)] flex-col p-0">
            <div class="sticky top-0 border-b border-line bg-white p-3">
                <div class="seg" role="group" aria-label="{{ __('Show jobs') }}">
                    @foreach(['todo' => __('To do'), 'done' => __('Billed'), 'all' => __('All')] as $key => $label)
                        <button type="button" wire:click="$set('filter', '{{ $key }}')" @class(['is-active' => $filter === $key])>{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <div class="flex-1 overflow-y-auto">
                @forelse($listed as $job)
                    <button
                        type="button"
                        wire:key="job-{{ $job['jobNumber'] }}"
                        data-job="{{ $job['jobNumber'] }}"
                        wire:click="openJob(@js($job['jobNumber']))"
                        @class([
                            'grid w-full grid-cols-[1fr_auto] gap-x-3 border-b border-rule px-4 py-2.5 text-left hover:bg-hover',
                            'is-active bg-gold-soft shadow-[inset_0_2px_0_var(--color-navy)]' => $job['jobNumber'] === $jobNumber,
                        ])
                    >
                        <span @class(['truncate text-sm', 'text-muted' => $job['done'], 'font-medium text-ink' => ! $job['done']])>{{ $job['jobName'] }}</span>
                        <span class="row-span-2 self-center text-right text-xs tabular-nums">
                            <span class="block text-muted" title="{{ implode(', ', $job['reasons']) }}">{{ $job['hoursLogged'] ? Format::number($job['hoursLogged'], 1).' h' : '—' }}</span>
                            @if((float) $job['billed'])
                                <b class="block font-medium text-positive">{{ Format::money($job['billed']) }}</b>
                            @endif
                        </span>
                        <span class="truncate text-xs text-muted">{{ $job['jobNumber'] }} · {{ $job['developer'] ?: '—' }}</span>
                    </button>
                @empty
                    <p class="p-4 text-sm text-muted">{{ $filter === 'todo' ? __('Every job on the list is billed.') : __('Nothing in this group.') }}</p>
                @endforelse
            </div>
            <p class="border-t border-line px-4 py-2 text-xs text-muted">{{ __('J / K moves to the next or previous job') }}</p>
        </div>

        <div class="min-w-0 space-y-5">
            @if(! $grid)
                <div class="card">
                    <p class="text-sm text-ink">{{ __('Pick a job to bill it.') }}</p>
                    <p class="section-note mt-1">{{ __('The list on the left is every job with time logged this month, every job already part-billed, and every active job with a CA monthly draw due.') }}</p>
                </div>
            @else
                <section class="card" wire:key="grid-{{ $grid['jobNumber'] }}">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="section-title text-[1.3rem]">
                                <a href="{{ route('projects.show', $grid['jobNumber']) }}" class="hover:underline" wire:navigate>{{ $grid['jobName'] }}</a>
                            </h3>
                            <p class="section-note">{{ $grid['jobNumber'] }} · {{ $grid['developer'] ?: '—' }} · {{ $grid['division'] ?: '—' }} · {{ __('lead') }} {{ $grid['lead'] ?: '—' }}</p>
                        </div>
                        <div class="text-right">
                            <div class="kpi-label">{{ __('This month') }}</div>
                            <div class="font-heading text-[1.6rem] text-navy">{{ Format::money($grid['totals']['billed'], 2) }}</div>
                            <div class="text-xs text-positive" aria-live="polite" wire:key="flash-{{ md5((string) $flash.$flashKey.microtime()) }}">
                                @if($flash)
                                    <span x-data="{ on: true }" x-init="setTimeout(() => on = false, 2600)" x-show="on" x-transition.opacity.duration.600ms>{{ __('Saved') }} ✓ {{ $flash }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($undo)
                        <div class="note mt-4 flex items-center justify-between gap-3 text-sm">
                            <span>{{ __('Removed :staff’s :hours h line.', ['staff' => $undo['staff'] ?? __('a'), 'hours' => Format::number($undo['hours'], 2)]) }}</span>
                            <button type="button" class="btn-secondary" wire:click="undoLine">{{ __('Undo') }}</button>
                        </div>
                    @endif

                    <div class="mt-5 overflow-x-auto">
                        <table class="data-table me-phases">
                            <thead>
                                <tr>
                                    <th>{{ __('Phase') }}</th>
                                    <th class="is-num">{{ __('Fee') }}</th>
                                    <th class="is-num">{{ __('Billed before') }}</th>
                                    <th class="is-num">{{ __('Complete') }}</th>
                                    <th class="is-num">{{ __('% this month') }}</th>
                                    <th class="is-num">{{ __('or amount') }}</th>
                                    <th class="is-num">{{ __('Bills') }}</th>
                                    <th>{{ __('Note') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($grid['phases'] as $row)
                                    @php
                                        $hasFee = $row['fee'] !== null && (float) $row['fee'] != 0;
                                        $bills = (float) $row['billedThisMonth'];
                                        $isCa = $row['phase'] === 'CA';
                                    @endphp
                                    <tr wire:key="ph-{{ $grid['jobNumber'] }}-{{ $row['phase'] }}" @class(['bg-[rgba(16,185,129,.06)]' => $bills, 'outline outline-1 outline-positive/40' => $flashKey === 'phase:'.$row['phase']])>
                                        <td>
                                            <b class="font-medium">{{ $row['phase'] }}</b>
                                            @if($row['feeNote'])
                                                <div class="max-w-[14rem] truncate text-xs text-muted" title="{{ $row['feeNote'] }}">{{ $row['feeNote'] }}</div>
                                            @endif
                                        </td>
                                        <td class="is-num">{{ $hasFee ? Format::money($row['fee']) : '—' }}</td>
                                        <td class="is-num">{!! (float) $row['billedBefore'] ? e(Format::money($row['billedBefore'])) : '<span class="empty">—</span>' !!}</td>
                                        <td class="is-num">{{ $row['completionBefore'] !== null ? Format::pct($row['completionBefore']) : '—' }}</td>
                                        <td class="is-num">
                                            <input
                                                type="number" step="0.5" min="0" inputmode="decimal"
                                                class="cell-input me-pct w-[5.5rem] border-[#e2e4e6]"
                                                value="{{ $pct($row['pctThisMonth']) }}"
                                                aria-label="{{ __(':phase percent this month', ['phase' => $row['phase']]) }}"
                                                @disabled(! $editable || ! $hasFee)
                                                @unless($hasFee) title="{{ __('no fee on this phase') }}" @endunless
                                                x-on:keydown.enter="$event.target.blur()"
                                                wire:change="savePct(@js($row['phase']), $event.target.value)"
                                            >
                                        </td>
                                        <td class="is-num">
                                            <input
                                                type="number" step="0.01" inputmode="decimal"
                                                class="cell-input me-amt w-[7rem] border-[#e2e4e6]"
                                                value="{{ $row['amountThisMonth'] !== null ? (float) $row['amountThisMonth'] : '' }}"
                                                placeholder="{{ $isCa && $grid['caPerMonth'] ? (float) $grid['caPerMonth'] : '' }}"
                                                aria-label="{{ __(':phase amount this month', ['phase' => $row['phase']]) }}"
                                                @disabled(! $editable)
                                                x-on:keydown.enter="$event.target.blur()"
                                                wire:change="saveAmount(@js($row['phase']), $event.target.value)"
                                            >
                                        </td>
                                        <td class="is-num font-medium">{{ $bills ? Format::money($row['billedThisMonth'], 2) : '' }}</td>
                                        <td>
                                            <input
                                                type="text"
                                                class="cell-input w-[8rem] text-left"
                                                value="{{ $row['note'] }}"
                                                placeholder="—"
                                                aria-label="{{ __(':phase note', ['phase' => $row['phase']]) }}"
                                                @disabled(! $editable)
                                                x-on:keydown.enter="$event.target.blur()"
                                                wire:change="saveNote(@js($row['phase']), $event.target.value)"
                                            >
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="6">{{ __('Phase billing') }}</td>
                                    <td class="is-num">{{ Format::money($grid['totals']['phaseBilled'], 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mt-8 flex flex-wrap items-baseline justify-between gap-2">
                        <div>
                            <h4 class="section-title">{{ __('Hourly') }}</h4>
                            <p class="section-note">{{ __('time and materials — the workbook calls this "extras"') }}</p>
                        </div>
                        <span class="text-xs text-muted">{{ $hoursLogged ? __(':hours h logged on this job this month', ['hours' => Format::number($hoursLogged, 1)]) : __('no hours logged this month') }}</span>
                    </div>

                    <div class="mt-3 overflow-x-auto">
                        <table class="data-table me-hours">
                            <thead>
                                <tr>
                                    <th>{{ __('Staff') }}</th>
                                    <th class="is-num">{{ __('Hours') }}</th>
                                    <th class="is-num">{{ __('Rate') }}</th>
                                    <th class="is-num">{{ __('or amount') }}</th>
                                    <th class="is-num">{{ __('Total') }}</th>
                                    <th>{{ __('Note') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($grid['hourLines'] as $line)
                                    @php
                                        $departs = $line['rate'] !== null && $line['resolvedRate'] !== null && abs((float) $line['rate'] - (float) $line['resolvedRate']) > 0.01;
                                    @endphp
                                    <tr wire:key="hl-{{ $line['id'] }}">
                                        <td>{{ $line['staff'] ?? '—' }}</td>
                                        <td class="is-num">
                                            <input type="number" step="0.25" min="0" class="cell-input w-[5.5rem]" value="{{ $line['hours'] !== null ? (float) $line['hours'] : '' }}" placeholder="—" @disabled(! $editable)
                                                aria-label="{{ __('Hours') }}" x-on:keydown.enter="$event.target.blur()" wire:change="updateLine({{ $line['id'] }}, 'hours', $event.target.value)">
                                        </td>
                                        <td class="is-num whitespace-nowrap">
                                            <input type="number" step="1" min="0" class="cell-input w-[5rem]" value="{{ $line['rate'] !== null ? (float) $line['rate'] : '' }}" placeholder="—" @disabled(! $editable)
                                                aria-label="{{ __('Rate') }}" x-on:keydown.enter="$event.target.blur()" wire:change="updateLine({{ $line['id'] }}, 'rate', $event.target.value)">
                                            @if($departs)
                                                <span class="text-muted" title="{{ __('the rate table would give :rate', ['rate' => Format::money($line['resolvedRate'])]) }}">≠</span>
                                            @endif
                                        </td>
                                        <td class="is-num">
                                            <input type="number" step="0.01" class="cell-input w-[6rem]" value="{{ $line['amount'] !== null ? (float) $line['amount'] : '' }}" placeholder="—" @disabled(! $editable)
                                                aria-label="{{ __('Amount') }}" title="{{ __('A figure typed in place of hours × rate; 0 means worked, not billed') }}" x-on:keydown.enter="$event.target.blur()" wire:change="updateLine({{ $line['id'] }}, 'amount', $event.target.value)">
                                        </td>
                                        <td class="is-num">
                                            {{ Format::money($line['total'], 2) }}
                                            @if($line['amount'] !== null && (float) $line['amount'] == 0)
                                                <span class="chip ml-1">{{ __('not billed') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <input type="text" class="cell-input w-[9rem] text-left" value="{{ $line['note'] }}" placeholder="—" @disabled(! $editable)
                                                aria-label="{{ __('Note') }}" x-on:keydown.enter="$event.target.blur()" wire:change="updateLine({{ $line['id'] }}, 'note', $event.target.value)">
                                        </td>
                                        <td class="text-right">
                                            @if($editable)
                                                <button type="button" class="btn-danger" wire:click="deleteLine({{ $line['id'] }})" title="{{ __('Remove this line') }}" aria-label="{{ __('Remove :staff’s line', ['staff' => $line['staff'] ?? '']) }}"><i class="ph ph-x"></i></button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach

                                @if($editable)
                                    <tr class="bg-[#fafbfb]" wire:key="hl-new">
                                        <td>
                                            <select wire:model="newLine.staff" class="select w-full min-w-[12rem]" aria-label="{{ __('Staff') }}">
                                                <option value="">{{ __('add staff…') }}</option>
                                                @foreach($this->staff as $person)
                                                    <option value="{{ $person->name }}">{{ $person->name }} ({{ $person->rate_title ?? '?' }})</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="is-num"><input type="number" step="0.25" min="0" wire:model="newLine.hours" placeholder="{{ __('hrs') }}" class="cell-input w-[5.5rem] border-[#e2e4e6]" aria-label="{{ __('Hours') }}" x-on:keydown.enter="$wire.addLine()"></td>
                                        <td class="is-num"><input type="number" step="1" min="0" wire:model="newLine.rate" placeholder="{{ __('auto') }}" class="cell-input w-[5rem] border-[#e2e4e6]" aria-label="{{ __('Rate') }}" x-on:keydown.enter="$wire.addLine()"></td>
                                        <td class="is-num"><input type="number" step="0.01" wire:model="newLine.amount" placeholder="—" class="cell-input w-[6rem] border-[#e2e4e6]" aria-label="{{ __('Amount') }}" x-on:keydown.enter="$wire.addLine()"></td>
                                        <td class="is-num text-muted">—</td>
                                        <td><input type="text" wire:model="newLine.note" placeholder="{{ __('note') }}" class="cell-input w-[9rem] border-[#e2e4e6] text-left" aria-label="{{ __('Note') }}" x-on:keydown.enter="$wire.addLine()"></td>
                                        <td class="text-right"><button type="button" class="btn py-1" wire:click="addLine">{{ __('Add') }}</button></td>
                                    </tr>
                                @endif
                            </tbody>
                            <tfoot>
                                @php
                                    $linesTotal = array_sum(array_map(fn ($line) => (float) $line['total'], $grid['hourLines']));
                                @endphp
                                <tr>
                                    <td colspan="4">
                                        {{ $grid['hourlyOverride'] !== null ? __('Billed as a flat figure (lines total :amount)', ['amount' => Format::money($linesTotal, 2)]) : __('Hourly billing') }}
                                    </td>
                                    <td class="is-num">{{ Format::money($grid['totals']['hourlyBilled'], 2) }}</td>
                                    <td colspan="2">
                                        <span class="flex items-center justify-end gap-2">
                                            <input type="number" step="0.01" wire:model="flatAmount" placeholder="{{ __('flat amount') }}" class="cell-input w-[7rem] border-[#e2e4e6] bg-white" aria-label="{{ __('Flat hourly amount') }}" @disabled(! $editable) x-on:keydown.enter="$wire.setOverride()">
                                            @if($editable)
                                                <button type="button" class="btn-secondary" wire:click="setOverride">{{ __('Set') }}</button>
                                                @if($grid['hourlyOverride'] !== null)
                                                    <button type="button" class="btn-secondary" wire:click="clearOverride">{{ __('Clear') }}</button>
                                                @endif
                                            @endif
                                        </span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <section class="card">
                    <h4 class="section-title">{{ __('Consultants') }}</h4>
                    <p class="section-note">{{ __('Consultant fees charged to this job in :month. They come off the job\'s $/hr; they are not billed. Given hours and no total, the total is the consultant\'s latest rate on or before the month, at its exchange rate.', ['month' => $monthName]) }}</p>
                    <div class="mt-3 overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Consultant') }}</th>
                                    <th class="is-num">{{ __('Hours') }}</th>
                                    <th class="is-num">{{ __('Total') }}</th>
                                    <th>{{ __('Note') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($consultantFees as $fee)
                                    <tr wire:key="cf-{{ $fee->id }}">
                                        <td>{{ $fee->consultant ?? '—' }}</td>
                                        <td class="is-num">{{ Format::number($fee->hours, 2) }}</td>
                                        <td class="is-num">{{ Format::money(\App\Support\Money::round($fee->total), 2) }}</td>
                                        <td class="text-muted">{{ $fee->note }}</td>
                                        <td></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted">{{ __('No consultant fees this month.') }}</td></tr>
                                @endforelse
                                @if($canEdit)
                                    <tr class="bg-[#fafbfb]" wire:key="cf-new">
                                        <td>
                                            <input type="text" list="consultant-names" wire:model="newFee.consultant" placeholder="{{ __('consultant') }}" class="cell-input w-[9rem] border-[#e2e4e6] text-left" aria-label="{{ __('Consultant') }}">
                                            <datalist id="consultant-names">
                                                @foreach($this->consultants as $name)
                                                    <option value="{{ $name }}"></option>
                                                @endforeach
                                            </datalist>
                                        </td>
                                        <td class="is-num"><input type="number" step="0.25" min="0" wire:model="newFee.hours" placeholder="{{ __('hrs') }}" class="cell-input w-[5.5rem] border-[#e2e4e6]" aria-label="{{ __('Hours') }}"></td>
                                        <td class="is-num"><input type="number" step="0.01" wire:model="newFee.total" placeholder="{{ __('auto') }}" class="cell-input w-[7rem] border-[#e2e4e6]" aria-label="{{ __('Total') }}"></td>
                                        <td><input type="text" wire:model="newFee.note" placeholder="{{ __('note') }}" class="cell-input w-[9rem] border-[#e2e4e6] text-left" aria-label="{{ __('Note') }}"></td>
                                        <td class="text-right"><button type="button" class="btn py-1" wire:click="addConsultantFee">{{ __('Add') }}</button></td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>
    </div>
</div>
