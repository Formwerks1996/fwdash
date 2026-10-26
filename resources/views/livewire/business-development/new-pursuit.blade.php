<div>
    @if($open)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/30 p-4 sm:p-16" wire:click.self="$set('open', false)" x-data x-on:keydown.escape.window="$wire.set('open', false)">
            <form wire:submit="save" class="card w-full max-w-[520px] border-t-2 border-t-gold" role="dialog" aria-modal="true" aria-labelledby="new-pursuit-title">
                <div class="flex items-center justify-between">
                    <h2 id="new-pursuit-title" class="section-title text-xl">{{ __('New pursuit') }}</h2>
                    <button type="button" class="text-muted hover:text-navy" wire:click="$set('open', false)" aria-label="{{ __('Close') }}"><i class="ph ph-x text-lg"></i></button>
                </div>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="field-label" for="np-project">{{ __('Project') }}</label>
                        <input id="np-project" wire:model="project" class="field-input" placeholder="{{ __('Address or project name — can be blank for now') }}" autofocus>
                    </div>

                    <div
                        class="relative"
                        x-data="{
                            clients: @js($this->clients),
                            query: $wire.entangle('client'),
                            open: false,
                            cursor: 0,
                            get typedKey() { return (this.query || '').toLowerCase().replace(/[^\p{L}\p{N}]/gu, '') },
                            get known() { return this.clients.some(c => c.key === this.typedKey) },
                            get matches() { const q = (this.query || '').trim().toLowerCase(); return this.clients.filter(c => c.name.toLowerCase().includes(q)).slice(0, 60) },
                            choose(name) { this.query = name; this.open = false; $refs.fee.focus() },
                        }"
                        x-on:click.outside="open = false"
                    >
                        <label class="field-label" for="np-client">{{ __('Client') }}</label>
                        <div class="relative">
                            <i class="ph ph-magnifying-glass absolute top-1/2 left-3 -translate-y-1/2 text-muted"></i>
                            <input
                                id="np-client"
                                x-model="query"
                                x-on:focus="open = true"
                                x-on:input="open = true; cursor = 0"
                                x-on:keydown.arrow-down.prevent="cursor = Math.min(cursor + 1, matches.length)"
                                x-on:keydown.arrow-up.prevent="cursor = Math.max(cursor - 1, 0)"
                                x-on:keydown.enter.prevent="matches[cursor] ? choose(matches[cursor].name) : choose(query)"
                                x-on:keydown.escape.stop="open = false"
                                class="field-input pl-9"
                                placeholder="{{ __('Search clients, or type a new one') }}"
                                autocomplete="off"
                            >
                        </div>
                        <ul x-show="open && (matches.length || (query && ! known))" x-cloak class="absolute z-10 mt-1 max-h-60 w-full overflow-y-auto border border-line bg-white py-1 text-sm shadow-lg">
                            <template x-for="(client, index) in matches" :key="client.key">
                                <li>
                                    <button type="button" class="flex w-full justify-between px-3 py-1.5 text-left" :class="index === cursor && 'bg-gold-soft'" x-on:mouseenter="cursor = index" x-on:click="choose(client.name)">
                                        <span x-text="client.name"></span>
                                        <span class="text-xs text-muted" x-text="client.jobs ? client.jobs + (client.jobs === 1 ? ' job' : ' jobs') : ''"></span>
                                    </button>
                                </li>
                            </template>
                            <li x-show="query && ! known">
                                <button type="button" class="w-full px-3 py-1.5 text-left font-medium text-navy hover:bg-hover" x-on:click="choose(query.trim())">+ {{ __('Add') }} “<span x-text="query.trim()"></span>” {{ __('as a new client') }}</button>
                            </li>
                        </ul>
                        <p class="mt-1 text-xs" :class="query && ! known ? 'text-[#a96511]' : 'text-muted'" x-text="! query ? clients.length + ' {{ __('clients on file') }}' : (known ? '{{ __('On file') }}' : '{{ __('New — will be added to Developers') }}')"></p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="field-label" for="np-fee">{{ __('Fee') }}</label>
                            <input id="np-fee" x-ref="fee" type="number" min="0" step="100" wire:model="fee" class="field-input" placeholder="$">
                        </div>
                        <div>
                            <label class="field-label" for="np-owner">{{ __('Lead') }}</label>
                            <select id="np-owner" wire:model="owner" class="field-input">
                                <option value="">{{ __('Nobody yet') }}</option>
                                @foreach($owners as $option) <option value="{{ $option }}">{{ $option }}</option> @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="np-stage">{{ __('Stage') }}</label>
                            <select id="np-stage" wire:model="stage" class="field-input">
                                @foreach($stages as $option) <option value="{{ $option->value }}">{{ $option->value }}</option> @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="field-label" for="np-note">{{ __('Note') }}</label>
                        <textarea id="np-note" rows="3" wire:model="note" class="field-input" placeholder="{{ __('Who called, what they want, next step…') }}"></textarea>
                    </div>

                    @if($errors->any()) <p class="error-note">{{ $errors->first() }}</p> @endif
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn-secondary" wire:click="$set('open', false)">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn"><i class="ph ph-plus"></i>{{ __('Add pursuit') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
