@php
    use App\Models\TimesheetEntry;

    $days = $this->days;
    $rows = $this->rows;
    $totals = $this->totals;
    $isOwn = $this->isOwn;
    $weekStart = $this->weekStart;
    $hours = fn (mixed $value) => (float) $value == 0 ? '' : rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
@endphp

<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="page-title">{{ __('Timesheets') }}</h1>
            <p class="page-subtitle">
                @if($isOwn)
                    {{ __('Log your hours by project and day: one row for each project, office time or kind of time off.') }}
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
            {{ trans_choice('logged on :count day|logged across :count days', $totals['daysLogged'], ['count' => $totals['daysLogged']]) }}
        </x-kpi>
        <x-kpi :label="__('Office')" :value="$hours($totals['office']) ?: '0'">
            {{ __('admin, meetings and other non-project time') }}
        </x-kpi>
        <x-kpi :label="__('On projects')" :value="$hours($totals['project']) ?: '0'">
            {{ $totals['total'] ? number_format($totals['project'] / $totals['total'] * 100).'% '.__('of the week') : __('no hours yet') }}
        </x-kpi>
        <x-kpi :label="__('Time off')" :value="$hours($totals['timeOff']) ?: '0'">
            {{ __('vacation, sick, banked and unpaid') }}
        </x-kpi>
    </div>

    @if($isOwn)
        <x-modal wire:model="confirmingRemove" :title="__('Remove this row?')" max-width="460px">
            @php($removingRow = $rows->firstWhere('key', $removing))
            <div class="grid gap-4">
                <p class="text-sm text-ink-2">
                    @if($removingRow)
                        {{ __('This takes :row off your week and deletes its :hours h.', [
                            'row' => $removingRow['jobName'] ?? $removingRow['category']->label(),
                            'hours' => $hours($removingRow['total']),
                        ]) }}
                    @endif
                </p>
                <div class="flex justify-end gap-2 border-t border-line pt-4">
                    <button type="button" class="btn-secondary" x-on:click="$wire.confirmingRemove = false">{{ __('Cancel') }}</button>
                    <button type="button" class="btn" wire:click="removeRow(@js($removing))" wire:loading.attr="disabled" wire:target="removeRow">{{ __('Remove row') }}</button>
                </div>
            </div>
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
                    <button type="button" class="btn" x-data x-on:click="$dispatch('add-row')">
                        <i class="ph ph-plus"></i>{{ __('Add row') }}
                    </button>
                </div>
            @endif
        </div>

        @error('timesheet') <p class="error-note mx-6 mb-3">{{ $message }}</p> @enderror

        <div>
            <table class="data-table timesheet-grid table-fixed">
                <colgroup>
                    <col class="w-[28%]">
                    @foreach($days as $day)
                        <col>
                    @endforeach
                    <col class="w-[3.75rem]">
                    @if($isOwn)
                        <col class="w-8">
                    @endif
                </colgroup>
                <thead>
                    <tr>
                        <th>{{ __('Project') }}</th>
                        @foreach($days as $day)
                            <th @class(['text-center', 'text-navy' => $day['isToday'], 'opacity-60' => $day['isWeekend']])>
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
                            <td class="whitespace-normal">
                                @if($row['jobNumber'])
                                    <span class="block truncate font-medium text-ink" title="{{ $row['jobName'] }}">{{ $row['jobName'] }}</span>
                                    <span class="block truncate text-xs text-muted">{{ collect([$row['jobNumber'], $row['phase']])->filter()->join(' · ') }}</span>
                                @else
                                    <span class="font-medium text-ink">{{ $row['category']->label() }}</span>
                                    @if($row['category']->isTimeOff())
                                        <span class="chip is-info">{{ __('time off') }}</span>
                                    @endif
                                @endif
                            </td>
                            @foreach($days as $day)
                                @php($entry = $row['cells'][$day['date']] ?? null)
                                <td @class(['timesheet-cell', 'bg-gold-soft/40' => $day['isToday'], 'bg-[#fafafa]' => $day['isWeekend'] && ! $day['isToday']])>
                                    <span class="flex items-center">
                                        <input
                                            type="text"
                                            inputmode="decimal"
                                            class="cell-input w-full min-w-0 text-center"
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
                                                @class(['shrink-0 transition-colors', 'text-gold' => $entry?->note, 'text-[#c9cdd1] hover:text-navy' => ! $entry?->note])
                                                title="{{ $entry?->note ?: __('Add a note') }}"
                                                wire:click="editCell(@js($row['key']), '{{ $day['date'] }}')"
                                            ><i @class(['ph text-sm', 'ph-fill ph-note' => $entry?->note, 'ph-note-pencil' => ! $entry?->note])></i></button>
                                        @elseif($entry?->note)
                                            <i class="ph-fill ph-note shrink-0 text-sm text-gold" title="{{ $entry->note }}"></i>
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
                                        aria-label="{{ __('Remove :row', ['row' => $row['jobName'] ?? $row['category']->label()]) }}"
                                        wire:click="askToRemoveRow(@js($row['key']))"
                                    ><i class="ph ph-x"></i></button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isOwn ? 10 : 9 }}" class="py-10 text-center text-muted">
                                @if($isOwn)
                                    {{ __('No time logged this week. Add a row below, or copy last week’s rows.') }}
                                @else
                                    {{ __('No time logged this week.') }}
                                @endif
                            </td>
                        </tr>
                    @endforelse

                    @if($isOwn)
                        <tr class="timesheet-add" wire:key="ts-add-row">
                            <td colspan="10" class="whitespace-normal">
                                <div
                                    class="relative"
                                    x-data="rowPicker(@js($picker))"
                                    x-on:click.outside="close()"
                                    x-on:add-row.window="start()"
                                >
                                    <label class="flex items-center gap-2.5 text-muted">
                                        <span class="grid size-5 shrink-0 place-items-center border border-dashed border-[#c9cdd1] text-xs transition-colors" :class="open && 'border-solid border-navy bg-navy text-white'">
                                            <i class="ph ph-plus" x-show="! busy"></i>
                                            <i class="ph ph-circle-notch animate-spin" x-show="busy" x-cloak></i>
                                        </span>
                                        <template x-if="project">
                                            <span class="chip shrink-0 bg-gold-soft text-ink-2">
                                                <span class="max-w-48 truncate" x-text="project.name"></span>
                                                <button type="button" class="hover:text-navy" x-on:click.prevent="back()" aria-label="{{ __('Choose another project') }}"><i class="ph ph-x"></i></button>
                                            </span>
                                        </template>
                                        <input
                                            x-ref="search"
                                            x-model="query"
                                            x-on:focus="open = true"
                                            x-on:input="open = true; cursor = 0"
                                            x-on:keydown.arrow-down.prevent="move(1)"
                                            x-on:keydown.arrow-up.prevent="move(-1)"
                                            x-on:keydown.enter.prevent="options[cursor] && choose(options[cursor])"
                                            x-on:keydown.escape.prevent.stop="project ? back() : close(true)"
                                            x-on:keydown.backspace="! query && project && back()"
                                            x-on:keydown.tab="close()"
                                            :placeholder="project ? @js(__('Which phase? Type to filter, or press Enter for none')) : @js(__('Add a project, office time or time off…'))"
                                            class="min-w-0 flex-1 bg-transparent py-1 text-[0.8rem] text-ink placeholder:text-muted focus:outline-none"
                                            role="combobox"
                                            aria-label="{{ __('Add a row') }}"
                                            :aria-expanded="open"
                                            autocomplete="off"
                                        >
                                    </label>

                                    <div
                                        x-show="open"
                                        x-cloak
                                        x-transition:enter="transition duration-150 ease-out"
                                        x-transition:enter-start="-translate-y-1 opacity-0"
                                        x-transition:enter-end="translate-y-0 opacity-100"
                                        class="absolute top-full left-0 z-30 mt-2 w-[30rem] max-w-[calc(100vw-3rem)] border border-line bg-white shadow-[0_16px_32px_-12px_rgba(0,38,62,0.25)]"
                                    >
                                        <div x-show="! project && ! query" class="flex flex-wrap items-center gap-1.5 border-b border-line px-3 py-2.5">
                                            <template x-for="category in categories" :key="category.value">
                                                <button type="button" class="chip transition-colors hover:bg-navy hover:text-white" x-on:click="add({ category: category.value })" x-text="category.label"></button>
                                            </template>
                                        </div>

                                        <ul x-ref="list" class="max-h-72 overflow-y-auto py-1 text-[0.8rem]" role="listbox">
                                            <template x-for="section in sections" :key="section.label || 'phases'">
                                                <li>
                                                    <p x-show="section.label" class="sticky top-0 bg-white px-3 pt-2.5 pb-1 text-[0.66rem] font-medium tracking-[0.09em] text-muted uppercase" x-text="section.label"></p>
                                                    <ul>
                                                        <template x-for="option in section.items" :key="option.key">
                                                            <li>
                                                                <button
                                                                    type="button"
                                                                    class="flex w-full items-baseline gap-3 px-3 py-1.5 text-left"
                                                                    :class="options[cursor]?.key === option.key && 'bg-gold-soft'"
                                                                    :data-active="options[cursor]?.key === option.key"
                                                                    role="option"
                                                                    x-on:mousemove="cursor = options.findIndex((candidate) => candidate.key === option.key)"
                                                                    x-on:click="choose(option)"
                                                                >
                                                                    <span class="truncate text-ink" x-text="option.name"></span>
                                                                    <span class="ml-auto shrink-0 text-xs text-muted tabular-nums" x-text="option.meta"></span>
                                                                </button>
                                                            </li>
                                                        </template>
                                                    </ul>
                                                </li>
                                            </template>
                                            <li x-show="! options.length" class="px-3 py-4 text-muted">{{ __('Nothing matches.') }}</li>
                                        </ul>

                                        <p class="flex gap-4 border-t border-line bg-[#fafafa] px-3 py-1.5 text-[0.68rem] text-muted">
                                            <span><kbd>↑</kbd> <kbd>↓</kbd> {{ __('move') }}</span>
                                            <span><kbd>Enter</kbd> <span x-text="project ? @js(__('add row')) : @js(__('choose'))"></span></span>
                                            <span><kbd>Esc</kbd> <span x-text="project ? @js(__('back')) : @js(__('close'))"></span></span>
                                            <span x-show="! project && ! query" class="ml-auto">{{ __('Inactive projects show when you search') }}</span>
                                        </p>
                                    </div>
                                </div>
                                @error('newRow.job_number') <p class="error-note mt-2">{{ $message }}</p> @enderror
                                @error('newRow.category') <p class="error-note mt-2">{{ $message }}</p> @enderror
                            </td>
                        </tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr>
                        <td>{{ __('Total') }}</td>
                        @foreach($days as $day)
                            @php($dayTotal = $totals['byDay'][$day['date']] ?? 0)
                            <td class="text-center tabular-nums">
                                <span @class(['text-muted' => $dayTotal == 0])>{{ $hours($dayTotal) ?: '0' }}</span>
                            </td>
                        @endforeach
                        <td class="is-num">{{ $hours($totals['total']) ?: '0' }}</td>
                        @if($isOwn)
                            <td></td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
