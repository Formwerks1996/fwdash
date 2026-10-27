@php
    $me = $this->me;
    $projects = $this->projects;
    $caps = 'text-xs font-medium tracking-[0.18em] text-muted uppercase';
@endphp

<div class="grid max-w-[1180px] gap-8">
    <header class="flex flex-wrap items-end justify-between gap-x-8 gap-y-4 border-b border-gold-line pb-5">
        <div>
            <h1 class="page-title">{{ __('My Week') }}</h1>
            <p class="mt-1 max-w-[60ch] text-[0.92rem] text-ink-2">{{ __('Who you go to this week and the projects you are on, from the Team Lineup.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" class="btn-secondary" wire:click="previousWeek" aria-label="{{ __('Previous week') }}"><i class="ph ph-caret-left"></i></button>
            <span class="min-w-[11rem] text-center text-[0.92rem]">
                {{ __(':from – :to', ['from' => $monday->format('M j'), 'to' => $monday->addDays(4)->format('M j')]) }}
            </span>
            <button type="button" class="btn-secondary" wire:click="nextWeek" aria-label="{{ __('Next week') }}"><i class="ph ph-caret-right"></i></button>
            @unless($isThisWeek)
                <button type="button" class="btn-secondary" wire:click="goToWeek('{{ \App\Livewire\Team\TeamLineup::thisMonday() }}')">{{ __('This week') }}</button>
            @endunless
        </div>
    </header>

    @if($me === null)
        <div class="card text-sm text-muted">
            {{ __('Your sign-in is not linked to anyone on the team roster yet. Ask an admin to add your email to your entry in the Team Lineup roster.') }}
        </div>
    @else
        <section class="grid max-w-xl grid-cols-2 items-end gap-6">
            <div class="grid gap-0.5 border-l border-gold-line pl-4">
                <span class="{{ $caps }}">{{ __('You go to') }}</span>
                <b class="text-[1.15rem] font-normal">{{ $boss ?? __('Not placed this week') }}</b>
                @if($partner) <small class="text-[0.78rem] text-muted">{{ __('under :partner', ['partner' => $partner]) }}</small> @endif
            </div>
            <div class="grid gap-0.5 border-l border-gold-line pl-4">
                <span class="{{ $caps }}">{{ __('Ongoing projects') }}</span>
                <b class="text-[1.15rem] font-normal tabular-nums">{{ $projects->where('assigned', true)->count() }}</b>
            </div>
        </section>

        @foreach([
            [__('This week'), __('Your project on the Team Lineup this week'), $projects->where('thisWeek', true), __('Nothing on the lineup for you this week.')],
            [__('Ongoing'), __('Every project you are assigned to'), $projects->where('assigned', true), __('No projects are assigned to you yet.')],
        ] as [$heading, $note, $rows, $empty])
            <section class="grid gap-4">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 border-b border-gold-line pb-2">
                    <h3 class="text-xl">{{ $heading }}</h3>
                    <span class="{{ $caps }}">{{ $note }}</span>
                </div>

                @forelse($rows as $row)
                    <a href="{{ route('projects.show', $row['project']->job_number) }}" wire:navigate class="card grid gap-4 transition-colors hover:border-[#c9ced3] sm:grid-cols-[1.6fr_1fr_1fr]">
                        <div class="min-w-0">
                            <span class="text-xs text-muted tabular-nums">{{ $row['project']->job_number }}</span>
                            <div class="mt-1 truncate text-[1.05rem] text-ink">{{ $row['project']->job_name }}</div>
                            @if($row['project']->developer)
                                <div class="truncate text-sm text-muted">{{ $row['project']->developer }}</div>
                            @endif
                        </div>
                        <div class="grid content-start gap-0.5">
                            <span class="{{ $caps }}">{{ __('PM') }}</span>
                            <span class="text-sm text-ink">{{ $row['pms'] ? implode(', ', $row['pms']) : '—' }}</span>
                            @if($row['with'])
                                <small class="text-[0.78rem] text-muted">{{ __('with :people', ['people' => implode(', ', $row['with'])]) }}</small>
                            @endif
                        </div>
                        <div class="grid content-start gap-0.5">
                            <span class="{{ $caps }}">{{ __('Partner') }}</span>
                            <span class="text-sm text-ink">{{ $row['partner'] ?? '—' }}</span>
                        </div>
                    </a>
                @empty
                    <p class="text-sm text-muted">{{ $empty }}</p>
                @endforelse
            </section>
        @endforeach
    @endif
</div>
