@props(['title', 'maxWidth' => '640px'])

@php
    $property = $attributes->wire('model')->value();
@endphp

{{-- Bound to a Livewire boolean with wire:model; the backdrop fades and the panel eases up into place. --}}
<div
    x-data="{ show: $wire.entangle('{{ $property }}') }"
    x-show="show"
    x-cloak
    x-on:keydown.escape.window="show = false"
    x-effect="document.body.classList.toggle('overflow-hidden', show); show && $nextTick(() => $el.querySelector('input:not([type=hidden]), select, textarea')?.focus())"
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
>
    <div
        x-show="show"
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-navy/35 backdrop-blur-[2px]"
        x-on:click="show = false"
    ></div>

    <div class="pointer-events-none relative flex min-h-full items-start justify-center p-4 sm:p-12">
        <div
            x-show="show"
            x-transition:enter="transition duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
            x-transition:enter-start="translate-y-4 scale-[0.97] opacity-0"
            x-transition:enter-end="translate-y-0 scale-100 opacity-100"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-start="translate-y-0 scale-100 opacity-100"
            x-transition:leave-end="translate-y-2 scale-[0.98] opacity-0"
            class="card pointer-events-auto w-full border-t-2 border-t-gold shadow-[0_24px_48px_-12px_rgba(0,38,62,0.25)]"
            style="max-width: {{ $maxWidth }}"
        >
            <div class="flex items-center justify-between gap-4">
                <h2 class="section-title text-xl">{{ $title }}</h2>
                <button type="button" class="text-muted transition-colors hover:text-navy" x-on:click="show = false" aria-label="{{ __('Close') }}">
                    <i class="ph ph-x text-lg"></i>
                </button>
            </div>

            <div class="mt-5">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
