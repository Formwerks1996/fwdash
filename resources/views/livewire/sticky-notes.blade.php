@php
    $ago = function ($time) {
        $minutes = (int) $time->diffInMinutes(now());

        return match (true) {
            $minutes < 1 => __('just now'),
            $minutes < 60 => __(':n min ago', ['n' => $minutes]),
            $minutes < 1440 => __(':n h ago', ['n' => intdiv($minutes, 60)]),
            $minutes < 8640 => __(':n d ago', ['n' => intdiv($minutes, 1440)]),
            default => $time->format('j M'),
        };
    };
@endphp

<div
    wire:poll.30s.visible
    class="print:hidden"
    x-data="{
        draft: null,
        startDraft() {
            const layer = $refs.layer.getBoundingClientRect();
            this.draft = { x: 1, y: Math.max(0, window.scrollY - (layer.top + window.scrollY) + 20 + ({{ $this->notes->count() }} % 6) * 28) };
            $nextTick(() => $refs.draft.focus());
        },
        finishDraft(text) {
            const draft = this.draft;
            this.draft = null;

            if (text.trim() !== '') {
                $wire.create(text, draft.x, draft.y);
            }
        },
    }"
>
    {{-- The layer scrolls with the page; x is a share of the usable width so a note lands in the same place on any screen. --}}
    <div x-ref="layer" class="pointer-events-none absolute inset-0 z-30">
        @foreach($this->notes as $note)
            <div
                wire:key="note-{{ $note->id }}-{{ $note->updated_at?->timestamp }}"
                class="pointer-events-auto absolute w-max max-w-[280px] min-w-24 rounded-xl bg-[#fff7b0] shadow-lg"
                style="left: calc((100% - 190px) * {{ (float) $note->pos_x }}); top: {{ $note->pos_y }}px"
                x-data="{
                    dragging: false,
                    start(event) {
                        const note = this.$el;
                        const offsetX = event.clientX - note.getBoundingClientRect().left;
                        const offsetY = event.clientY - note.getBoundingClientRect().top;
                        this.dragging = true;

                        const move = (e) => {
                            const box = note.parentElement.getBoundingClientRect();
                            note.style.left = (e.clientX - box.left - offsetX) + 'px';
                            note.style.top = (e.clientY - box.top - offsetY) + 'px';
                        };

                        const stop = (e) => {
                            window.removeEventListener('pointermove', move);
                            window.removeEventListener('pointerup', stop);
                            this.dragging = false;
                            const box = note.parentElement.getBoundingClientRect();
                            const x = (e.clientX - box.left - offsetX) / Math.max(box.width - 190, 1);
                            const y = Math.round(e.clientY - box.top - offsetY);
                            $wire.move('{{ $note->id }}', Math.min(Math.max(x, 0), 1), Math.max(y, 0));
                        };

                        window.addEventListener('pointermove', move);
                        window.addEventListener('pointerup', stop);
                    },
                }"
                :class="dragging && 'scale-[1.03]'"
            >
                <div class="flex h-[22px] cursor-grab items-center justify-between rounded-t-xl bg-[#ffe970] px-2 text-xs text-[#7a6a1a]" x-on:pointerdown.prevent="start($event)">
                    <i class="ph ph-dots-six"></i>
                    <span class="truncate px-1">{{ $note->author }}</span>
                    <button type="button" class="hover:text-danger" x-on:pointerdown.stop wire:click="delete('{{ $note->id }}')" title="{{ __('Delete') }}"><i class="ph ph-x"></i></button>
                </div>
                <textarea
                    wire:ignore
                    rows="{{ min(12, max(2, substr_count($note->body, "\n") + 1 + intdiv(mb_strlen($note->body), 34))) }}"
                    class="block w-[240px] resize-none bg-transparent px-3 py-2 text-sm text-[#3d3510] focus:outline-none"
                    x-on:blur="$el.value.trim() !== @js($note->body) && $el.value.trim() !== '' && $wire.edit('{{ $note->id }}', $el.value)"
                >{{ $note->body }}</textarea>
            </div>
        @endforeach

        <template x-if="draft">
            <div class="pointer-events-auto absolute rounded-xl bg-[#fff7b0] shadow-lg" :style="`left: calc((100% - 190px) * ${draft.x}); top: ${draft.y}px`">
                <div class="h-[22px] rounded-t-xl bg-[#ffe970]"></div>
                <textarea x-ref="draft" rows="3" class="block w-[240px] resize-none bg-transparent px-3 py-2 text-sm focus:outline-none" placeholder="{{ __('Type a note…') }}" x-on:blur="setTimeout(() => finishDraft($el.value), 150)" x-on:keydown.escape="$el.value = ''; $el.blur()"></textarea>
            </div>
        </template>
    </div>

    <div class="fixed right-5 bottom-5 z-40 flex flex-col items-end gap-2">
        @if($lastDeleted)
            <div class="flex items-center gap-3 bg-dark px-4 py-2 text-sm text-white shadow-lg" x-data x-init="setTimeout(() => $wire.set('lastDeleted', null), 7000)">
                {{ __('Note deleted') }}
                <button type="button" class="font-medium text-gold-soft underline" wire:click="restore">{{ __('Undo') }}</button>
            </div>
        @endif
        @error('note') <p class="bg-white px-3 py-2 text-sm text-danger shadow">{{ $message }}</p> @enderror
        <div class="flex gap-2">
            <button type="button" class="flex items-center gap-1.5 rounded-full bg-white px-3 py-2 text-sm text-ink-2 shadow-md hover:text-navy" wire:click="$set('drawer', true)" title="{{ __('Notes') }}">
                <i class="ph ph-notebook text-lg"></i>
                @if($this->allNotes->count()) <span class="text-xs">{{ $this->allNotes->count() }}</span> @endif
            </button>
            <button type="button" class="flex items-center gap-1.5 rounded-full bg-[#ffe66d] px-4 py-2 text-sm font-medium text-[#5c4d0a] shadow-md hover:bg-[#ffe14d]" x-on:click="startDraft()">
                <i class="ph ph-note-pencil text-lg"></i>{{ __('Add note') }}
            </button>
        </div>
    </div>

    @if($drawer)
        <div class="fixed inset-0 z-50 bg-navy/20" wire:click="$set('drawer', false)" x-data x-on:keydown.escape.window="$wire.set('drawer', false)"></div>
        <aside class="fixed inset-y-0 right-0 z-50 flex w-full max-w-[420px] flex-col bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h2 class="section-title text-lg">{{ __('Notes') }} · {{ $this->allNotes->count() }}</h2>
                <button type="button" class="text-muted hover:text-navy" wire:click="$set('drawer', false)" aria-label="{{ __('Close') }}"><i class="ph ph-x text-lg"></i></button>
            </div>
            <div class="flex-1 space-y-3 overflow-y-auto p-5">
                @forelse($this->allNotes as $note)
                    <div wire:key="drawer-{{ $note->id }}" class="rounded-lg bg-[#fff7b0] p-3 text-sm">
                        @if($url = $note->url())
                            <a href="{{ $url }}" class="text-xs text-[#7a6a1a] underline" wire:navigate>↪ {{ $note->place_label ?: $note->place_target }}</a>
                        @endif
                        <p class="mt-1 whitespace-pre-wrap text-[#3d3510]">{{ $note->body }}</p>
                        <div class="mt-2 flex justify-between text-xs text-[#7a6a1a]">
                            <span>{{ $note->author }} · {{ $ago($note->updated_at) }}</span>
                            <button type="button" class="underline" wire:click="delete('{{ $note->id }}')">{{ __('Delete') }}</button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-muted">{{ __('No notes yet. Use Add note on any page.') }}</p>
                @endforelse
            </div>
        </aside>
    @endif
</div>
