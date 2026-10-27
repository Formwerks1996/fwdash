@php
    use App\Enums\TimeCategory;
    use App\Models\TimesheetEntry;

    $days = $this->days;
    $rows = $this->rows;
    $totals = $this->totals;
    $isOwn = $this->isOwn;
    $weekStart = $this->weekStart;
    $hours = fn (mixed $value) => (float) $value == 0 ? '' : rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    $balance = $totals['total'] - $totals['capacity'];
@endphp

<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="page-title">{{ __('Timesheets') }}</h1>
            <p class="page-subtitle">
                @if($isOwn)
                    {{ __('Log your hours by project and day. A full day is :hours h.', ['hours' => $hours(TimesheetEntry::DailyCapacity)]) }}
                @else
                    {{ __(':name’s week, read-only.', ['name' => $this->person->name]) }}
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if($people->isNotEmpty())
                <select class="select" wire:model.live="personId" aria-label="{{ __('Whose timesheet') }}">
                    @foreach($people as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $this->person->id)>{{ $option->name }}{{ $option->id === auth()->id() ? ' ('.__('you').')' : '' }}</option>
                    @endforeach
                </select>
            @endif

            <div class="seg">
                <button type="button" wire:click="previousWeek" aria-label="{{ __('Previous week') }}"><i class="ph ph-caret-left"></i></button>
                <button type="button" wire:click="goToWeek(null)" @class(['is-active' => $weekStart->isSameDay(now()->startOfWeek())])>{{ __('This week') }}</button>
                <button type="button" wire:click="nextWeek" aria-label="{{ __('Next week') }}"><i class="ph ph-caret-right"></i></button>
            </div>
            <input type="date" class="field-input w-auto py-1" value="{{ $weekStart->toDateString() }}" aria-label="{{ __('Go to week') }}" wire:change="goToWeek($event.target.value)">
        </div>
    </div>

    <div class="kpis grid-cols-1 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi :label="__('Week of :date', ['date' => $weekStart->format('j M Y')])" :value="($hours($totals['total']) ?: '0').'<small> h</small>'" dark>
            {{ __('of :capacity h capacity', ['capacity' => $hours($totals['capacity'])]) }}
        </x-kpi>
        <x-kpi :label="$balance >= 0 ? __('Over capacity') : __('Still to log')" :value="$hours(abs($balance)) ?: '0'" :warn="$balance > 0">
            {{ $balance == 0 ? __('the week is full') : ($balance > 0 ? __('more than :h h a day', ['h' => $hours(TimesheetEntry::DailyCapacity)]) : __('to reach a full week')) }}
        </x-kpi>
        <x-kpi :label="__('On projects')" :value="$hours($totals['project']) ?: '0'">
            {{ $totals['total'] ? number_format($totals['project'] / $totals['total'] * 100).'% '.__('of the week') : __('no hours yet') }}
        </x-kpi>
        <x-kpi :label="__('Time off')" :value="$hours($totals['timeOff']) ?: '0'">
            {{ __('vacation, sick, banked and unpaid') }}
        </x-kpi>
    </div>

    @if($isOwn)
        <x-modal wire:model="addingRow" :title="__('Add a row')" max-width="560px">
            <form wire:submit="addRow" class="grid gap-4 sm:grid-cols-3" x-data>
                <div class="sm:col-span-3">
                    <label class="field-label" for="new_category">{{ __('Time for') }}</label>
                    <select id="new_category" wire:model.live="newRow.category" class="field-input">
                        @foreach($categories as $category)
                            <option value="{{ $category->value }}">{{ $category->label() }}</option>
                        @endforeach
                    </select>
                </div>
                @if($newRow['category'] === TimeCategory::Project->value)
                    <div class="sm:col-span-2">
                        <label class="field-label" for="new_project">{{ __('Project') }}</label>
                        <select id="new_project" wire:model="newRow.job_number" class="field-input">
                            <option value="">{{ __('Pick a project') }}</option>
                            @foreach($projectGroups as $group => $options)
                                <optgroup label="{{ $group }}">
                                    @foreach($options as $project)
                                        <option value="{{ $project->job_number }}">{{ $project->job_name }} · {{ $project->job_number }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('newRow.job_number') <p class="error-note mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label" for="new_phase">{{ __('Phase') }}</label>
                        <select id="new_phase" wire:model="newRow.phase_code" class="field-input">
                            <option value="">{{ __('Any') }}</option>
                            @foreach($phases as $phase)
                                <option value="{{ $phase }}">{{ $phase }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="mt-2 flex justify-end gap-2 border-t border-line pt-4 sm:col-span-3">
                    <button type="button" class="btn-secondary" x-on:click="$wire.addingRow = false">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="addRow">{{ __('Add row') }}</button>
                </div>
            </form>
        </x-modal>

        <x-modal wire:model="editingCell" :title="__('Hours and note')" max-width="520px">
            <form wire:submit="saveCell" class="grid gap-4" x-data>
                <p class="text-sm text-ink-2">
                    @if($cell['row'] !== '')
                        @php($cellRow = TimesheetEntry::parseRowKey($cell['row']))
                        <b class="font-medium">{{ $rows->firstWhere('key', $cell['row'])['jobName'] ?? $cellRow['category']->label() }}</b>
                        @if($cellRow['phase_code']) · {{ $cellRow['phase_code'] }} @endif
                        · {{ \Carbon\CarbonImmutable::parse($cell['date'])->format('l j M') }}
                    @endif
                </p>
                <div>
                    <label class="field-label" for="cell_hours">{{ __('Hours') }}</label>
                    <input id="cell_hours" wire:model="cell.hours" inputmode="decimal" class="field-input w-32" placeholder="0">
                </div>
                <div>
                    <label class="field-label" for="cell_note">{{ __('Note') }}</label>
                    <textarea id="cell_note" wire:model="cell.note" rows="3" class="field-input" placeholder="{{ __('What you worked on') }}"></textarea>
                    @error('cell.note') <p class="error-note mt-1">{{ $message }}</p> @enderror
                </div>
                @error('timesheet') <p class="error-note">{{ $message }}</p> @enderror
                <div class="flex justify-end gap-2 border-t border-line pt-4">
                    <button type="button" class="btn-secondary" x-on:click="$wire.editingCell = false">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="saveCell">{{ __('Save') }}</button>
                </div>
            </form>
        </x-modal>
    @endif

    <div class="card p-0">
        <div class="toolbar px-6 pt-5 pb-4">
            <div>
                <h2 class="section-title">{{ $weekStart->format('j M') }} – {{ $weekStart->addDays(6)->format('j M Y') }}</h2>
                <p class="section-note">
                    @if($isOwn)
                        {{ __('Type hours in quarter hours ("1.5" or "1:30") and press Enter or click away to save. Clear a cell to remove it. The note icon adds what you worked on.') }}
                    @else
                        {{ __('Only :name can change these hours.', ['name' => $this->person->name]) }}
                    @endif
                </p>
            </div>
            @if($isOwn)
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="btn-secondary" wire:click="copyPreviousRows">
                        <i class="ph ph-copy"></i>{{ __('Copy last week’s rows') }}
                    </button>
                    <button type="button" class="btn" x-data x-on:click="$wire.addingRow = true">
                        <i class="ph ph-plus"></i>{{ __('Add row') }}
                    </button>
                </div>
            @endif
        </div>

        @error('timesheet') <p class="error-note mx-6 mb-3">{{ $message }}</p> @enderror

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Project') }}</th>
                        @foreach($days as $day)
                            <th @class(['is-num', 'text-navy' => $day['isToday'], 'opacity-60' => $day['isWeekend']])>
                                {{ $day['label'] }}
                                <span class="block font-normal normal-case tracking-normal">{{ $day['day'] }}</span>
                            </th>
                        @endforeach
                        <th class="is-num">{{ __('Total') }}</th>
                        @if($isOwn)
                            <th><span class="sr-only">{{ __('Remove') }}</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr wire:key="ts-{{ md5($row['key'].$weekStart) }}">
                            <td class="max-w-[18rem]">
                                @if($row['jobNumber'])
                                    <span class="block truncate font-medium text-ink">{{ $row['jobName'] }}</span>
                                    <span class="text-xs text-muted">{{ collect([$row['jobNumber'], $row['phase']])->filter()->join(' · ') }}</span>
                                @else
                                    <span class="font-medium text-ink">{{ $row['category']->label() }}</span>
                                    @if($row['category']->isTimeOff())
                                        <span class="chip is-info">{{ __('time off') }}</span>
                                    @endif
                                @endif
                            </td>
                            @foreach($days as $day)
                                @php($entry = $row['cells'][$day['date']] ?? null)
                                <td @class(['is-num', 'bg-gold-soft/40' => $day['isToday'], 'bg-[#fafafa]' => $day['isWeekend'] && ! $day['isToday']])>
                                    <span class="inline-flex items-center">
                                        <input
                                            type="text"
                                            inputmode="decimal"
                                            class="cell-input w-14"
                                            placeholder="·"
                                            aria-label="{{ __('Hours on :day', ['day' => $day['label'].' '.$day['day']]) }}"
                                            value="{{ $hours($entry?->hours) }}"
                                            @disabled(! $isOwn)
                                            x-on:keydown.enter="$event.target.blur()"
                                            wire:change="saveHours(@js($row['key']), '{{ $day['date'] }}', $event.target.value)"
                                        >
                                        @if($isOwn)
                                            <button
                                                type="button"
                                                @class(['px-0.5 transition-colors', 'text-gold' => $entry?->note, 'text-[#c9cdd1] hover:text-navy' => ! $entry?->note])
                                                title="{{ $entry?->note ?: __('Add a note') }}"
                                                wire:click="editCell(@js($row['key']), '{{ $day['date'] }}')"
                                            ><i @class(['ph text-sm', 'ph-fill ph-note' => $entry?->note, 'ph-note-pencil' => ! $entry?->note])></i></button>
                                        @elseif($entry?->note)
                                            <i class="ph-fill ph-note px-0.5 text-sm text-gold" title="{{ $entry->note }}"></i>
                                        @endif
                                    </span>
                                </td>
                            @endforeach
                            <td class="is-num font-medium">{{ $hours($row['total']) ?: '—' }}</td>
                            @if($isOwn)
                                <td>
                                    <button
                                        type="button"
                                        class="text-muted transition-colors hover:text-danger"
                                        title="{{ __('Remove row') }}"
                                        x-data
                                        x-on:click="if (! {{ $row['total'] ? 'true' : 'false' }} || confirm(@js(__('Remove this row and its :hours h this week?', ['hours' => $hours($row['total'])])))) { $wire.removeRow(@js($row['key'])) }"
                                    ><i class="ph ph-x"></i></button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isOwn ? 10 : 9 }}" class="py-10 text-center text-muted">
                                @if($isOwn)
                                    {{ __('No time logged this week. Add a row, or copy last week’s rows.') }}
                                @else
                                    {{ __('No time logged this week.') }}
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td>{{ __('Total') }}</td>
                        @foreach($days as $day)
                            @php($dayTotal = $totals['byDay'][$day['date']] ?? 0)
                            <td class="is-num">
                                <span @class([
                                    'text-over' => $dayTotal > TimesheetEntry::DailyCapacity,
                                    'text-success' => $day['capacity'] > 0 && $dayTotal == $day['capacity'],
                                    'text-muted' => $dayTotal == 0,
                                ])>{{ $hours($dayTotal) ?: '0' }}</span>
                                @if($day['capacity'] > 0)
                                    <span class="block text-xs font-normal text-muted">/ {{ $hours($day['capacity']) }}</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="is-num">
                            {{ $hours($totals['total']) ?: '0' }}
                            <span class="block text-xs font-normal text-muted">/ {{ $hours($totals['capacity']) }}</span>
                        </td>
                        @if($isOwn)
                            <td></td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
