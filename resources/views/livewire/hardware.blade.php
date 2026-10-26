@php
    use App\Support\Format;

    $dueChip = ['overdue' => 'is-danger', 'now' => 'is-warn', 'next' => 'is-info', 'later' => '', 'none' => ''];
    $fieldsets = [
        __('Assignment') => ['cid' => 'CID', 'user_name' => __('User'), 'name_future' => __('Name (future)'), 'dept' => __('Dept'), 'location' => __('Location')],
        __('Lifecycle') => ['purchase_date' => __('Purchase date'), 'purchase_price' => __('Purchase price'), 'replace_year' => __('Replacement year'), 'override' => __('Override'), 'upgrade' => __('Upgrade plan')],
        __('Security & network') => ['sophos' => 'Sophos', 'ip_address' => __('IP address'), 'serial' => __('Serial')],
        __('Specs') => ['model' => __('Model'), 'screen_size' => __('Screen size'), 'os_version' => __('OS version'), 'cpu' => 'CPU', 'cpu_speed' => __('CPU speed'), 'cpu_cores' => __('CPU cores'), 'memory' => __('Memory'), 'mem_type' => __('Memory type'), 'mem_banks' => __('Memory banks'), 'graphics' => __('Graphics'), 'hd1' => __('Drive 1'), 'hd2' => __('Drive 2')],
    ];
    $inputTypes = ['purchase_date' => 'date', 'purchase_price' => 'number', 'replace_year' => 'number', 'cpu_cores' => 'number'];
@endphp

<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="page-title">{{ __('Hardware') }}</h1>
            <p class="page-subtitle">{{ __('Every computer, who has it and when it is due for replacement.') }}</p>
        </div>
        @if($canEdit)
            <button type="button" class="btn" wire:click="create"><i class="ph ph-plus"></i>{{ __('Add computer') }}</button>
        @endif
    </div>

    <div class="kpis grid-cols-1 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi :label="__('Computers')" :value="$this->live->count()">
            {{ $kindCounts->map(fn ($count, $kind) => $count.' '.$kinds[$kind][0])->implode(' · ') }}
        </x-kpi>
        <x-kpi :label="__('Due for replacement')" :value="$due" :warn="$due > 0">
            {{ __('overdue or due in :year', ['year' => $thisYear]) }} · {{ $dueNext }} {{ __('more in :year', ['year' => $thisYear + 1]) }}
        </x-kpi>
        <x-kpi :label="__('Spares')" :value="$spares->count()">
            {{ $spares->pluck('cid')->filter()->implode(', ') ?: __('none on hand') }}
        </x-kpi>
        <x-kpi :label="__('Without Sophos')" :value="$unprotected" :warn="$unprotected > 0">
            {{ $unprotected ? __('in use and not marked Y') : __('every machine in use has it') }}
        </x-kpi>
    </div>

    <div class="card p-0">
        <div class="toolbar px-6 pt-5 pb-4">
            <div class="seg">
                <button type="button" wire:click="$set('kind', 'all')" @class(['is-active' => $kind === 'all'])>{{ __('All') }} {{ $this->live->count() }}</button>
                @foreach($kinds as $key => [$label])
                    @if($kindCounts[$key] ?? 0)
                        <button type="button" wire:click="$set('kind', '{{ $key }}')" @class(['is-active' => $kind === $key])>{{ $label }} {{ $kindCounts[$key] }}</button>
                    @endif
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-64">
                    <i class="ph ph-magnifying-glass absolute top-1/2 left-3 -translate-y-1/2 text-muted"></i>
                    <input type="search" wire:model.live.debounce.250ms="search" placeholder="{{ __('Search') }}" class="search-input">
                </div>
                <select wire:model.live="dept" class="select">
                    <option value="">{{ __('All depts') }}</option>
                    @foreach($depts as $option) <option value="{{ $option }}">{{ $option }}</option> @endforeach
                </select>
                <label class="flex items-center gap-2 text-xs text-ink-2">
                    <input type="checkbox" wire:model.live="showRetired" class="accent-navy"> {{ __('Show retired') }}
                </label>
                <span class="text-xs text-muted">{{ $this->groups->flatten()->count() }} {{ __('shown') }}</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>CID</th>
                        <th>{{ __('Assigned to') }}</th>
                        <th>{{ __('Dept') }}</th>
                        <th>{{ __('Model') }}</th>
                        <th>{{ __('Chip') }}</th>
                        <th>{{ __('Memory') }}</th>
                        <th>{{ __('Storage') }}</th>
                        <th>{{ __('Purchased') }}</th>
                        <th class="is-num">{{ __('Price') }}</th>
                        <th>{{ __('Replacement') }}</th>
                        <th>Sophos</th>
                        <th>{{ __('Notes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->groups as $group => $computers)
                        <tr class="is-group" wire:key="hw-group-{{ $group }}">
                            <td colspan="12"><i class="ph {{ $kinds[$group][1] }}"></i> {{ $kinds[$group][0] }} · {{ $computers->count() }}</td>
                        </tr>
                        @foreach($computers as $computer)
                            @php
                                $status = $computer->dueStatus();
                                $serial = strtoupper(trim((string) $computer->serial));
                                $repeats = $this->repeatedSerials[$serial] ?? null;
                            @endphp
                            <tr
                                wire:key="hw-{{ $computer->id }}"
                                @class(['is-muted' => $computer->retired, 'is-link' => $canEdit])
                                @if($canEdit) wire:click="edit({{ $computer->id }})" tabindex="0" wire:keydown.enter="edit({{ $computer->id }})" @endif
                            >
                                <td class="font-medium">{{ $computer->cid ?? '—' }}</td>
                                <td>
                                    {{ $computer->user_name ?? '—' }}
                                    @if($computer->isSpare()) <span class="chip">{{ __('spare') }}</span> @endif
                                    @if($computer->retired) <span class="chip">{{ __('retired') }}</span> @endif
                                    @if($computer->location) <div class="text-xs text-muted">{{ $computer->location }}</div> @endif
                                </td>
                                <td>{{ $computer->dept ?? '—' }}</td>
                                <td>{{ $computer->model ?? '—' }} @if($computer->screen_size) <span class="text-xs text-muted">{{ $computer->screen_size }}</span> @endif</td>
                                <td>{{ $computer->cpu ?? '—' }}</td>
                                <td>{{ $computer->memory ?? '—' }}</td>
                                <td>{{ $computer->hd1 ?? '—' }}</td>
                                <td>
                                    {{ $computer->purchase_date ? Format::date($computer->purchase_date->format('Y-m-d')) : '—' }}
                                    @if($computer->ageInYears() !== null) <div class="text-xs text-muted">{{ $computer->ageInYears() }} {{ __('yrs old') }}</div> @endif
                                </td>
                                <td class="is-num">{{ $computer->purchase_price === null ? '—' : '$'.number_format((float) $computer->purchase_price, 2) }}</td>
                                <td>
                                    <span class="chip {{ $dueChip[$status['key']] }}">{{ $status['label'] }}</span>
                                    @if($computer->upgrade) <div class="text-xs text-muted">{{ $computer->upgrade }}</div> @endif
                                </td>
                                <td>
                                    @if($computer->sophos === 'Y')
                                        <i class="ph ph-shield-check text-lg text-[#398351]" title="{{ __('Installed') }}"></i>
                                    @elseif($computer->sophos === 'N')
                                        <i class="ph ph-shield-slash text-lg text-[#b8561f]" title="{{ __('Not installed') }}"></i>
                                    @else
                                        <span class="empty">—</span>
                                    @endif
                                </td>
                                <td class="max-w-[16rem] truncate text-xs">
                                    @if($repeats)
                                        <span class="chip is-warn" title="{{ __('Same serial as :cids', ['cids' => implode(', ', array_diff($repeats, [$computer->cid]))]) }}">{{ __('serial repeats') }}</span>
                                    @endif
                                    {{ $computer->notes }}
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="12" class="py-10 text-center text-muted">{{ __('No computers match.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($editorOpen)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/30 p-4 sm:p-10" wire:click.self="$set('editorOpen', false)" x-on:keydown.escape.window="$wire.set('editorOpen', false)">
            <form wire:submit="save" class="card w-full max-w-[760px] border-t-2 border-t-gold">
                <h2 class="section-title text-xl">{{ $editingId ? ($form['cid'] ?: __('Computer')) : __('Add computer') }}</h2>

                @foreach($fieldsets as $legend => $fields)
                    <fieldset class="mt-6">
                        <legend class="kpi-label mb-3">{{ $legend }}</legend>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($fields as $field => $label)
                                <div>
                                    <label class="field-label" for="hw-{{ $field }}">{{ $label }}</label>
                                    @if($field === 'sophos')
                                        <select id="hw-sophos" wire:model="form.sophos" class="field-input">
                                            <option value="">{{ __('Not recorded') }}</option>
                                            <option value="Y">{{ __('Y — installed') }}</option>
                                            <option value="N">{{ __('N — not installed') }}</option>
                                        </select>
                                    @else
                                        <input id="hw-{{ $field }}" type="{{ $inputTypes[$field] ?? 'text' }}" @if($field === 'purchase_price') step="0.01" min="0" @endif wire:model="form.{{ $field }}" class="field-input">
                                    @endif
                                    @error("form.{$field}") <p class="error-note mt-1">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <div class="mt-6">
                    <label class="field-label" for="hw-notes">{{ __('Notes') }}</label>
                    <textarea id="hw-notes" rows="3" wire:model="form.notes" class="field-input"></textarea>
                </div>

                <label class="mt-4 flex items-center gap-2 text-sm text-ink-2">
                    <input type="checkbox" wire:model="form.retired" class="accent-navy"> {{ __('Retired — no longer in use, kept on record') }}
                </label>

                <div class="mt-8 flex items-center justify-between gap-2">
                    <div>
                        @if($editingId)
                            <button type="button" class="btn-danger text-sm" wire:click="delete" wire:confirm="{{ __('Delete :cid for good? To keep it on record instead, tick Retired and save.', ['cid' => $form['cid'] ?: __('this computer')]) }}">{{ __('Delete') }}</button>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn-secondary" wire:click="$set('editorOpen', false)">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn">{{ $editingId ? __('Save changes') : __('Add computer') }}</button>
                    </div>
                </div>
            </form>
        </div>
    @endif
</div>
