<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen">
        <div class="flex min-h-screen">
            <aside class="fixed inset-y-0 left-0 hidden w-[260px] flex-col border-r border-line bg-white px-6 py-8 lg:flex">
                <a href="{{ route('home') }}" class="mb-10 block text-center" wire:navigate>
                    <img src="/images/formwerks-navy.png" alt="Formwerks" class="mx-auto max-w-[80%]">
                </a>

                <nav class="flex-1 overflow-y-auto" aria-label="{{ __('Pages') }}">
                    @include('partials.nav')
                </nav>

                @auth
                    <div class="mt-6 border-t border-line pt-4">
                        <div class="flex items-center gap-3">
                            <span class="flex size-8 items-center justify-center bg-gold-soft text-xs font-medium text-navy">
                                {{ auth()->user()->initials() }}
                            </span>
                            <div class="min-w-0 flex-1 text-xs leading-tight">
                                <div class="truncate font-medium text-ink">{{ auth()->user()->name }}</div>
                                <div class="truncate text-muted">{{ auth()->user()->primaryRole()->label() }}@if(auth()->user()->previewRole()) · {{ __('viewing as :role', ['role' => auth()->user()->previewRole()->label()]) }}@endif</div>
                            </div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="text-muted hover:text-navy" title="{{ __('Sign out') }}">
                                    <i class="ph ph-sign-out text-lg"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @endauth
            </aside>

            <div class="flex min-w-0 flex-1 flex-col lg:pl-[260px]">
                <header class="sticky top-0 z-20 flex h-14 items-center justify-between border-b border-line bg-white px-4 lg:hidden" x-data="{ open: false }">
                    <img src="/images/formwerks-navy.png" alt="Formwerks" class="h-4">
                    <button type="button" class="text-2xl text-navy" x-on:click="open = ! open" aria-label="{{ __('Menu') }}">
                        <i class="ph ph-list"></i>
                    </button>
                    <div x-show="open" x-cloak x-on:click.outside="open = false" class="absolute inset-x-0 top-14 border-b border-line bg-white p-4 shadow-lg">
                        <div class="mb-4">@include('partials.view-as')</div>
                        @include('partials.nav')
                        <form method="POST" action="{{ route('logout') }}" class="mt-4 border-t border-line pt-4">
                            @csrf
                            <button type="submit" class="nav-item w-full"><i class="ph ph-sign-out"></i>{{ __('Sign out') }}</button>
                        </form>
                    </div>
                </header>

                @auth
                    <header class="sticky top-0 z-20 hidden h-[72px] items-center justify-between gap-4 border-b border-line bg-white px-8 lg:flex print:hidden">
                        @include('partials.quick-jump')
                        <div class="flex items-center gap-4">
                            @include('partials.view-as')
                        </div>
                    </header>
                @endauth

                <main class="relative mx-auto w-full max-w-[1480px] flex-1 px-4 py-8 sm:px-8 sm:py-12">
                    {{ $slot }}

                    @auth
                        <livewire:sticky-notes :label="$title ?? null" :key="'notes-'.request()->path()" />
                    @endauth
                </main>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
