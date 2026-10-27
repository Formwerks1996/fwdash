@php
    use App\Livewire\Extras;
    use App\Support\Format;

    $chips = ['proposed' => 'is-info', 'active' => 'is-on', 'complete' => '', 'rejected' => 'is-danger'];
    $jobNames = $this->jobNames;
@endphp

<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="page-title">{{ __('Extras') }}</h1>
            <p class="page-subtitle">{{ __('Work beyond a job\'s contract phases: what was agreed, what it has billed, and every hourly line tagged to it.') }}</p>
        </div>
        @if($canEdit)
            <button type="button" class="btn" wire:click="create"><i class="ph ph-plus"></i>{{ __('New extra') }}</button>
        @endif
    </div>

    <div class="card p-0">
        <div class="toolbar px-6 pt-5 pb-4">
            <div class="relative w-full max-w-sm">
                <i class="ph ph-magnifying-glass absolute top-1/2 left-3 -translate-y-1/2 text-muted"></i>
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="{{ $view === 'untagged' ? __('Search job, person, note') : __('Search extra, job #, note') }}" class="search-input">
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="seg">
                    @foreach(['active' => __('Active'), 'proposed' => __('Proposed'), 'complete' => __('Complete'), 'rejected' => __('Rejected'), 'all' => __('All'), 'untagged' => __('Untagged')] as $value => $label)
                        <button type="button" wire:click="setView('{{ $value }}')" @class(['is-active' => $view === $value])>{{ $label }}@if($value === 'untagged' && $untaggedCount) <span class="ml-0.5 opacity-70">{{ $untaggedCount }}</span>@endif</button>
                    @endforeach
                </div>
                <select wire:model.live="job" class="select max-w-[16rem]" aria-label="{{ __('Project') }}">
                    <option value="">{{ __('All projects') }}</option>
                    @foreach($jobNames as $number => $name)
                        <option value="{{ $number }}">{{ $number }} {{ $name }}</option>
                    @endforeach
                </select>
                @if($canEdit && $view === 'untagged')
                    <button type="button" class="btn-secondary" wire:click="autoTag" title="{{ __('Tag lines whose note names an extra on their job, by its number or title') }}"><i class="ph ph-magic-wand"></i>{{ __('Tag from notes') }}</button>
                @endif
            </div>
        </div>
        @if($notice)
            <p class="px-6 pb-3 text-xs text-ink-2"><i class="ph ph-check"></i> {{ $notice }}</p>
        @endif
        @error('tag') <p class="error-note px-6 pb-3">{{ $message }}</p> @enderror

        @if($view === 'untagged')
            @php $groups = $this->untagged; @endphp
            @if($groups->isEmpty())
                <p class="px-6 py-10 text-center text-muted">{{ __('Every hourly line is on an extra.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Project') }}</th>
                                <th class="is-num">{{ __('Lines') }}</th>
                                <th class="is-num">{{ __('Hours') }}</th>
                                <th class="is-num">{{ __('Billed') }}</th>
                                <th>{{ __('Latest') }}</th>
                                <th>{{ __('Notes on them') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($groups as $jobNumber => $lines)
                                <tr wire:key="ut-{{ $jobNumber }}" class="cursor-pointer" wire:click="toggleJob(@js($jobNumber))">
                                    <td>
                                        <i @class(['ph text-muted', 'ph-caret-down' => $openJob === $jobNumber, 'ph-caret-right' => $openJob !== $jobNumber])></i>
                                        <span class="font-medium text-ink">{{ $jobNames[$jobNumber] ?? $jobNumber }}</span>
                                        <span class="ml-1 text-xs text-muted">{{ $jobNumber }}</span>
                                    </td>
                                    <td class="is-num">{{ $lines->count() }}</td>
                                    <td class="is-num">{{ Format::number($lines->sum(fn ($line) => (float) $line->hours), 1) }}</td>
                                    <td class="is-num">{{ Format::compact($lines->sum(fn ($line) => Extras::lineTotal($line))) }}</td>
                                    <td class="text-muted">{{ Format::shortMonth($lines->first()->period) }}</td>
                                    <td class="max-w-[22rem] truncate text-xs text-muted">{{ $lines->pluck('note')->filter()->unique()->take(4)->implode(' · ') ?: __('no notes') }}</td>
                                </tr>
                                @if($openJob === $jobNumber)
                                    @php $options = $this->extrasFor($jobNumber); @endphp
                                    <tr wire:key="ut-{{ $jobNumber }}-lines">
                                        <td colspan="6" class="bg-[#fafbfb] px-6 py-4">
                                            @if($canEdit)
                                                <div class="mb-3 flex flex-wrap items-center gap-2 text-sm" x-data="{ to: '' }">
                                                    <span class="text-ink-2">{{ __('Tag the ticked lines to') }}</span>
                                                    <select class="select" x-model="to" aria-label="{{ __('Extra') }}">
                                                        <option value="">{{ __('Pick an extra…') }}</option>
                                                        @foreach($options as $option)
                                                            <option value="{{ $option->id }}">{{ $option->label() }} ({{ $option->status }})</option>
                                                        @endforeach
                                                        <option value="new">{{ __('+ A new extra…') }}</option>
                                                    </select>
                                                    <button type="button" class="btn" x-bind:disabled="! to" x-on:click="$wire.tagPicked(@js($jobNumber), to)">{{ __('Tag') }}</button>
                                                    <button type="button" class="text-xs text-navy underline" x-on:click="$el.closest('td').querySelectorAll('input[type=checkbox]').forEach(box => { if (! box.checked) box.click() })">{{ __('Tick all') }}</button>
                                                </div>
                                            @endif
                                            <table class="w-full text-sm">
                                                <thead class="text-left text-xs text-muted">
                                                    <tr>
                                                        @if($canEdit)<th class="w-6 py-1"></th>@endif
                                                        <th class="py-1">{{ __('Month') }}</th>
                                                        <th class="py-1">{{ __('Who') }}</th>
                                                        <th class="py-1 text-right">{{ __('Hours') }}</th>
                                                        <th class="py-1 text-right">{{ __('Billed') }}</th>
                                                        <th class="py-1 pl-4">{{ __('Note') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($lines as $line)
                                                        <tr wire:key="ut-line-{{ $line->id }}" class="border-t border-[#eef0f2]">
                                                            @if($canEdit)<td class="py-1"><input type="checkbox" class="accent-navy" wire:model="picked.{{ $line->id }}" aria-label="{{ __('Tick line') }}"></td>@endif
                                                            <td class="py-1 text-muted">{{ Format::shortMonth($line->period) }}</td>
                                                            <td class="py-1">{{ $line->staff ?? '—' }}</td>
                                                            <td class="py-1 text-right tabular-nums">{{ $line->hours !== null ? Format::number($line->hours, 2) : '—' }}</td>
                                                            <td class="py-1 text-right tabular-nums">{{ Format::money(Extras::lineTotal($line)) }}</td>
                                                            <td class="py-1 pl-4 text-ink-2">{{ $line->note ?? '' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('Extra') }}</th>
                            <th>{{ __('Project') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="is-num">{{ __('Agreed') }}</th>
                            <th class="is-num">{{ __('Billed') }}</th>
                            <th class="is-num">{{ __('Left') }}</th>
                            <th class="is-num">{{ __('Hours billed') }}</th>
                            <th class="is-num" title="{{ __('Hours logged on its Mosaic phase, at charge-out') }}">{{ __('Logged') }}</th>
                            <th>{{ __('Last billed') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->rows as $row)
                            @php $extra = $row['extra']; @endphp
                            <tr wire:key="ex-{{ $extra->id }}" class="cursor-pointer" wire:click="toggle({{ $extra->id }})">
                                <td>
                                    <i @class(['ph text-muted', 'ph-caret-down' => $open === $extra->id, 'ph-caret-right' => $open !== $extra->id])></i>
                                    @if($extra->code)<b class="font-medium text-gold-text">{{ $extra->code }}</b>@endif
                                    <span class="font-medium text-ink">{{ $extra->title }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('projects.show', $extra->job_number) }}" wire:navigate class="hover:text-navy" x-on:click.stop>{{ $row['jobName'] ?? $extra->job_number }}</a>
                                    <span class="ml-1 text-xs text-muted">{{ $extra->job_number }}</span>
                                </td>
                                <td x-on:click.stop>
                                    @if($canEdit)
                                        <flux:dropdown>
                                            <button type="button" class="chip {{ $chips[$extra->status] }}">{{ ucfirst($extra->status) }} <i class="ph ph-caret-down text-[0.6rem]"></i></button>
                                            <flux:menu>
                                                @foreach($statuses as $status)
                                                    <flux:menu.item wire:click="setStatus({{ $extra->id }}, '{{ $status }}')">{{ ucfirst($status) }}</flux:menu.item>
                                                @endforeach
                                            </flux:menu>
                                        </flux:dropdown>
                                    @else
                                        <span class="chip {{ $chips[$extra->status] }}">{{ ucfirst($extra->status) }}</span>
                                    @endif
                                </td>
                                <td class="is-num">
                                    @if($row['budget'] !== null){{ Format::money($row['budget']) }}@endif
                                    @if($extra->budget_hours !== null)<small class="block text-muted">{{ Format::number($extra->budget_hours, 1) }} h</small>@endif
                                    @if($row['budget'] === null && $extra->budget_hours === null)<span class="text-xs text-muted">{{ __('hourly') }}</span>@endif
                                </td>
                                <td class="is-num">{{ $row['billed'] ? Format::money($row['billed']) : '—' }}</td>
                                <td @class(['is-num', 'text-over' => ($row['left'] ?? 0) < 0 || ($row['hoursLeft'] ?? 0) < 0])>
                                    @if($row['left'] !== null){{ Format::money($row['left']) }}@endif
                                    @if($row['hoursLeft'] !== null)<small class="block">{{ Format::number($row['hoursLeft'], 1) }} h</small>@endif
                                    @if($row['left'] === null && $row['hoursLeft'] === null)—@endif
                                </td>
                                <td class="is-num">{{ $row['hours'] ? Format::number($row['hours'], 1) : '—' }}</td>
                                <td class="is-num">
                                    @if($row['loggedHours'] !== null)
                                        {{ Format::number($row['loggedHours'], 1) }} h
                                        <small class="block text-muted">{{ Format::compact($row['loggedValue']) }}</small>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-muted">{{ $row['lastPeriod'] ? Format::shortMonth($row['lastPeriod']) : '—' }}</td>
                            </tr>
                            @if($open === $extra->id)
                                @php $lines = $this->linesOf($extra->id); $options = $this->extrasFor($extra->job_number); @endphp
                                <tr wire:key="ex-{{ $extra->id }}-open">
                                    <td colspan="9" class="bg-[#fafbfb] px-6 py-4">
                                        <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                                            <p class="max-w-[60ch] text-sm text-ink-2">{{ $extra->note ?: __('No note.') }}</p>
                                            @if($canEdit)
                                                <button type="button" class="btn-secondary" wire:click="edit({{ $extra->id }})"><i class="ph ph-pencil-simple"></i>{{ __('Edit extra') }}</button>
                                            @endif
                                        </div>
                                        @if($lines->isEmpty())
                                            <p class="text-sm text-muted">{{ __('No hourly lines tagged to this extra yet. Tag them from Untagged.') }}</p>
                                        @else
                                            <table class="w-full text-sm">
                                                <thead class="text-left text-xs text-muted">
                                                    <tr>
                                                        <th class="py-1">{{ __('Month') }}</th>
                                                        <th class="py-1">{{ __('Who') }}</th>
                                                        <th class="py-1 text-right">{{ __('Hours') }}</th>
                                                        <th class="py-1 text-right">{{ __('Billed') }}</th>
                                                        <th class="py-1 pl-4">{{ __('Note') }}</th>
                                                        @if($canEdit)<th class="py-1">{{ __('Tagged to') }}</th>@endif
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($lines as $line)
                                                        <tr wire:key="ex-line-{{ $line->id }}" class="border-t border-[#eef0f2]">
                                                            <td class="py-1 text-muted">{{ Format::shortMonth($line->period) }}</td>
                                                            <td class="py-1">{{ $line->staff ?? '—' }}</td>
                                                            <td class="py-1 text-right tabular-nums">{{ $line->hours !== null ? Format::number($line->hours, 2) : '—' }}</td>
                                                            <td class="py-1 text-right tabular-nums">{{ Format::money(Extras::lineTotal($line)) }}</td>
                                                            <td class="py-1 pl-4 text-ink-2">{{ $line->note ?? '' }}</td>
                                                            @if($canEdit)
                                                                <td class="py-1">
                                                                    <select class="select py-0.5 text-xs" wire:change="tagLine({{ $line->id }}, $event.target.value)" aria-label="{{ __('Retag line') }}">
                                                                        @foreach($options as $option)
                                                                            <option value="{{ $option->id }}" @selected($option->id === $extra->id)>{{ $option->label() }}</option>
                                                                        @endforeach
                                                                        <option value="">{{ __('Untagged') }}</option>
                                                                    </select>
                                                                </td>
                                                            @endif
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="9" class="py-10 text-center text-muted">{{ __('No extras match.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <p class="text-xs text-muted">
        {{ __('Agreed is the amount or hours the extra was set at; an extra with neither is open-ended hourly work. Billed is the total of the hourly lines tagged to it, from month end. Logged is the time on its Mosaic phase at charge-out. Extras with a Mosaic number came in from Mosaic, where "not started" was taken as proposed.') }}
    </p>

    <x-modal wire:model="editorOpen" :title="$editingId ? __('Edit extra') : __('New extra')" max-width="640px">
        <form wire:submit="save">
            <div class="grid gap-3 sm:grid-cols-[1fr_8rem]">
                <div>
                    <label class="field-label" for="ex-job">{{ __('Project') }}</label>
                    <select id="ex-job" wire:model="form.job_number" class="field-input">
                        <option value="">{{ __('Pick a project…') }}</option>
                        @foreach($jobNames as $number => $name)
                            <option value="{{ $number }}">{{ $number }} {{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label" for="ex-code">{{ __('Number') }}</label>
                    <input id="ex-code" type="text" wire:model="form.code" placeholder="8040-05" class="field-input">
                </div>
            </div>
            <div class="mt-3">
                <label class="field-label" for="ex-title">{{ __('Title') }}</label>
                <input id="ex-title" type="text" maxlength="200" wire:model="form.title" class="field-input">
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="field-label" for="ex-status">{{ __('Status') }}</label>
                    <select id="ex-status" wire:model="form.status" class="field-input">
                        @foreach($statuses as $status)
                            <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label" for="ex-amount">{{ __('Agreed amount') }}</label>
                    <input id="ex-amount" type="text" inputmode="decimal" wire:model="form.budget_amount" placeholder="$" class="field-input">
                </div>
                <div>
                    <label class="field-label" for="ex-hours">{{ __('or hours') }}</label>
                    <input id="ex-hours" type="text" inputmode="decimal" wire:model="form.budget_hours" placeholder="h" class="field-input">
                </div>
            </div>
            <div class="mt-3">
                <label class="field-label" for="ex-note">{{ __('Note') }}</label>
                <textarea id="ex-note" rows="3" wire:model="form.note" class="field-input"></textarea>
            </div>
            @if(! $editingId && array_filter($picked))
                <p class="mt-3 text-xs text-ink-2">{{ trans_choice('The :count ticked line will be tagged to it.|The :count ticked lines will be tagged to it.', count(array_filter($picked))) }}</p>
            @endif
            @error('extra') <p class="error-note mt-3">{{ $message }}</p> @enderror

            <div class="mt-6 flex items-center justify-between gap-2">
                <div>
                    @if($editingId)
                        <button type="button" class="btn-danger text-sm" wire:click="delete" wire:confirm="{{ __('Delete this extra? Its hourly lines go back to untagged.') }}">{{ __('Delete') }}</button>
                    @endif
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn-secondary" x-on:click="$wire.editorOpen = false">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn">{{ $editingId ? __('Save changes') : __('Add extra') }}</button>
                </div>
            </div>
        </form>
    </x-modal>
</div>
