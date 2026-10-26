@php
    use App\Support\Format;

    $ownerColors = [['#dbe7f0', '#2e5675'], ['#e6e2f2', '#54468a'], ['#dceae0', '#2c5c41'], ['#f2e5da', '#7a5433']];
@endphp

<div
    class="space-y-8"
    x-data="{ developers: @js($this->developers) }"
    x-on:pursuit-added.window="$nextTick(() => document.querySelector(`[data-developer-picker='${$event.detail.id}']`)?.click())"
>
    <div>
        <h1 class="page-title">{{ __('Developer Pipeline') }}</h1>
        <p class="page-subtitle">{{ __('Pursuits and opportunities, kept by hand.') }}</p>
    </div>

    <div class="kpis grid-cols-1 md:grid-cols-3">
        <x-kpi :label="__('In pursuit')" :value="Format::money($openFees)" dark>
            {{ trans_choice(':count open pursuit — contacted and in discussion|:count open pursuits — contacted and in discussion', $openCount) }}
        </x-kpi>
        <x-kpi :label="__('Won')" :value="Format::money($wonFees)">
            {{ __(':won of :settled settled', ['won' => $wonCount, 'settled' => $settled]) }}@if($settled) — {{ round($wonCount / $settled * 100) }}% {{ __('of those decided') }}@endif
        </x-kpi>
        <x-kpi :label="__('Tracked')" :value="$this->pursuits->count()">
            {{ __('every pursuit on this page, all states') }}
        </x-kpi>
    </div>

    <div class="card p-0">
        <div class="px-6 pt-5 pb-3">
            <h2 class="section-title">{{ __('Pursuits') }}</h2>
            <p class="section-note">
                {{ __('Everything here is typed on this page — there is no other record of it. A cell saves when you leave it, so a row can be added empty and filled in later. Developer, stage and owner open a picker; a developer who is not on the list yet can be created from the bottom of it.') }}
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Developer') }}</th>
                        <th>{{ __('Project') }}</th>
                        <th class="is-num">{{ __('Fee') }}</th>
                        <th>{{ __('Stage') }}</th>
                        <th>{{ __('Owner') }}</th>
                        <th>{{ __('Note') }}</th>
                        <th class="w-9"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->pursuits as $pursuit)
                        @php
                            [$stageBg, $stageText] = $pursuit->stage->colors();
                            $ownerIndex = array_search($pursuit->owner, $owners, true);
                            $currentKey = $aliases[$developerKey($pursuit->developer)] ?? $developerKey($pursuit->developer);
                        @endphp
                        <tr wire:key="pursuit-{{ $pursuit->id }}">
                            <td>
                                <div class="relative" x-data="{ open: false, query: '', cursor: 0,
                                    get matches() { const q = this.query.trim().toLowerCase(); return developers.filter(d => d.name.toLowerCase().includes(q)).slice(0, 60) },
                                    get typedKey() { return this.query.toLowerCase().replace(/[^\p{L}\p{N}]/gu, '') },
                                    get canCreate() { return this.typedKey !== '' && ! developers.some(d => d.key === this.typedKey) },
                                    choose(name) { this.open = false; $wire.updateField({{ $pursuit->id }}, 'developer', name) },
                                    create() { this.open = false; $wire.createDeveloper({{ $pursuit->id }}, this.query.trim()) },
                                    pick() { const m = this.matches; m[this.cursor] ? this.choose(m[this.cursor].name) : (this.canCreate && this.create()) }
                                }" x-on:keydown.escape="open = false" x-on:click.outside="open = false">
                                    <button
                                        type="button"
                                        data-developer-picker="{{ $pursuit->id }}"
                                        class="flex items-center gap-2 text-left disabled:cursor-default"
                                        @disabled(! $canEdit)
                                        x-on:click="open = ! open; query = ''; cursor = 0; $nextTick(() => $refs.search?.focus())"
                                    >
                                        <span class="flex size-6 items-center justify-center bg-[#ecedee] text-xs text-muted">{{ mb_strtoupper(mb_substr($pursuit->developer ?: '?', 0, 1)) }}</span>
                                        <span @class(['font-medium', 'text-muted font-normal' => $pursuit->developer === ''])>{{ $pursuit->developer ?: __('Empty') }}</span>
                                    </button>

                                    <div x-show="open" x-cloak class="absolute top-full left-0 z-30 mt-1 w-72 border border-line border-t-2 border-t-gold bg-white shadow-lg">
                                        <input
                                            x-ref="search"
                                            x-model="query"
                                            x-on:input="cursor = 0"
                                            x-on:keydown.arrow-down.prevent="cursor = Math.min(cursor + 1, matches.length - 1)"
                                            x-on:keydown.arrow-up.prevent="cursor = Math.max(cursor - 1, 0)"
                                            x-on:keydown.enter.prevent="pick()"
                                            class="w-full border-b border-line px-3 py-2 text-sm focus:outline-none"
                                            placeholder="{{ __('Find a developer, or type a new name') }}"
                                        >
                                        <ul class="max-h-64 overflow-y-auto py-1 text-sm">
                                            <template x-for="(developer, index) in matches" :key="developer.key">
                                                <li>
                                                    <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left" :class="index === cursor && 'bg-gold-soft'" x-on:mouseenter="cursor = index" x-on:click="choose(developer.name)">
                                                        <span class="w-3 text-gold-text" x-text="developer.key === @js($currentKey) ? '✓' : ''"></span>
                                                        <span x-text="developer.name"></span>
                                                    </button>
                                                </li>
                                            </template>
                                            @if($pursuit->developer !== '')
                                                <li><button type="button" class="w-full px-3 py-1.5 text-left text-muted hover:bg-hover" x-on:click="choose('')">{{ __('Clear') }}</button></li>
                                            @endif
                                            <li x-show="canCreate">
                                                <button type="button" class="w-full px-3 py-1.5 text-left font-medium text-navy hover:bg-hover" x-on:click="create()">+ {{ __('Create') }} “<span x-text="query.trim()"></span>”</button>
                                            </li>
                                            <li x-show="! matches.length && ! canCreate" class="px-3 py-1.5 text-muted">{{ __('No match') }}</li>
                                        </ul>
                                        <p class="border-t border-line px-3 py-1.5 text-xs text-muted"><span x-text="developers.length"></span> {{ __('on file — billing and added by hand') }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <input class="cell-input w-56 text-left" value="{{ $pursuit->project }}" placeholder="{{ __('Project') }}" @disabled(! $canEdit) wire:change="updateField({{ $pursuit->id }}, 'project', $event.target.value)">
                            </td>
                            <td class="is-num">
                                <input
                                    class="cell-input"
                                    value="{{ $pursuit->fee === null ? '' : Format::money($pursuit->fee) }}"
                                    placeholder="—"
                                    @disabled(! $canEdit)
                                    x-on:focus="$el.value = $el.value.replace(/[$,]/g, ''); $el.select()"
                                    wire:change="updateField({{ $pursuit->id }}, 'fee', $event.target.value)"
                                >
                            </td>
                            <td>
                                @if($canEdit)
                                    <flux:dropdown>
                                        <button type="button" class="chip" style="background: {{ $stageBg }}; color: {{ $stageText }}">{{ $pursuit->stage->value }}</button>
                                        <flux:menu>
                                            @foreach($stages as $stage)
                                                <flux:menu.item wire:click="updateField({{ $pursuit->id }}, 'stage', '{{ $stage->value }}')">{{ $stage === $pursuit->stage ? '✓ ' : '' }}{{ $stage->value }}</flux:menu.item>
                                            @endforeach
                                        </flux:menu>
                                    </flux:dropdown>
                                @else
                                    <span class="chip" style="background: {{ $stageBg }}; color: {{ $stageText }}">{{ $pursuit->stage->value }}</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $face = $ownerIndex === false ? ['#ecedee', '#5c6873'] : $ownerColors[$ownerIndex % 4];
                                @endphp
                                @if($canEdit)
                                    <flux:dropdown>
                                        <button type="button" class="flex items-center gap-2">
                                            <span class="flex size-6 items-center justify-center text-xs" style="background: {{ $face[0] }}; color: {{ $face[1] }}">{{ mb_substr($pursuit->owner ?? '?', 0, 1) }}</span>
                                            <span @class(['text-muted' => ! $pursuit->owner])>{{ $pursuit->owner ?? __('Empty') }}</span>
                                        </button>
                                        <flux:menu>
                                            @foreach($owners as $owner)
                                                <flux:menu.item wire:click="updateField({{ $pursuit->id }}, 'owner', '{{ $owner }}')">{{ $owner === $pursuit->owner ? '✓ ' : '' }}{{ $owner }}</flux:menu.item>
                                            @endforeach
                                            <flux:menu.item wire:click="updateField({{ $pursuit->id }}, 'owner', null)">{{ __('Nobody yet') }}</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                @else
                                    {{ $pursuit->owner ?? '—' }}
                                @endif
                            </td>
                            <td>
                                <input class="cell-input w-64 text-left" value="{{ $pursuit->note }}" placeholder="—" @disabled(! $canEdit) wire:change="updateField({{ $pursuit->id }}, 'note', $event.target.value)">
                            </td>
                            <td>
                                @if($canEdit)
                                    @php
                                        $label = collect([$pursuit->developer, $pursuit->project])->filter()->implode(' — ') ?: __('this empty pursuit');
                                    @endphp
                                    <button type="button" class="btn-danger" title="{{ __('Remove this pursuit') }}" wire:click="delete({{ $pursuit->id }})" wire:confirm="{{ __('Delete :label?', ['label' => $label]) }}"><i class="ph ph-trash"></i></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-muted">{{ __('No pursuits yet — add the first one below.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($canEdit)
            <div class="flex flex-wrap items-center gap-4 border-t border-line px-6 py-4 text-sm">
                <button type="button" class="btn-secondary" wire:click="add"><i class="ph ph-plus"></i>{{ __('Add a pursuit') }}</button>
                <span wire:loading class="text-muted">{{ __('saving…') }}</span>
                @error('fee') <span class="text-[#a83f24]">{{ $message }}</span> @enderror
                @error('owner') <span class="text-[#a83f24]">{{ $message }}</span> @enderror
                @if($addedDeveloper)
                    <span class="text-positive">✓ {{ $addedDeveloper }} {{ __('added —') }} <a class="underline" href="{{ route('developers.show', \App\Support\DeveloperKey::make($addedDeveloper)) }}" wire:navigate>{{ __('open their profile') }}</a></span>
                @endif
            </div>
        @endif
    </div>
</div>
