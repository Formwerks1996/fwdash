@php
    $me = $this->me;
    $projects = $this->projects;
    $canOpenProjects = auth()->user()->can('view projects');
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
            [__('Ongoing'), __('Every project you are assigned to or manage'), $projects->where('assigned', true), __('No projects are assigned to you yet.')],
        ] as [$heading, $note, $rows, $empty])
            <section class="grid gap-4">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 border-b border-gold-line pb-2">
                    <h3 class="text-xl">{{ $heading }}</h3>
                    <span class="{{ $caps }}">{{ $note }}</span>
                </div>

                @forelse($rows as $row)
                    <a href="{{ $canOpenProjects ? route('projects.show', $row['project']->job_number) : route('projects.index', ['view' => 'cards']) }}" wire:navigate class="card grid gap-4 transition-colors hover:border-[#c9ced3] sm:grid-cols-[1.6fr_1fr_1fr]">
                        <div class="min-w-0">
                            <span class="text-xs text-muted tabular-nums">{{ $row['project']->job_number }}</span>
                            <div class="mt-1 truncate text-[1.05rem] text-ink">{{ $row['project']->job_name }}</div>
                            @if($row['project']->developer)
                                <div class="truncate text-sm text-muted">{{ $row['project']->developer }}</div>
                            @endif
                        </div>
                        <div class="grid content-start gap-0.5">
                            <span class="{{ $caps }}">{{ __('PM') }}</span>
                            <span class="text-sm text-ink">{{ collect($row['managing'] ? [__('You')] : [])->merge($row['pms'])->implode(', ') ?: '—' }}</span>
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

    @php
        $board = $this->board;
        $mine = $me ? collect($board)->map(fn (array $rows) => collect($rows)->firstWhere('name', $me->name))->filter()->keys()->first() : null;
    @endphp
    <section class="grid gap-4">
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 border-b border-gold-line pb-2">
            <h3 class="text-xl">{{ __('Community board') }}</h3>
            <span class="{{ $caps }}">{{ __('Who could use a hand, and who has time to give one') }}</span>
        </div>

        @if($me && ! $me->isPartner())
            <div class="card flex flex-wrap items-center gap-3 py-3">
                @if($mine)
                    <span class="text-sm text-ink-2">
                        {{ $mine === 'help' ? __('You are asking for help this week.') : __('You are listed as available this week.') }}
                    </span>
                    <button type="button" class="btn-secondary" wire:click="setMyStatus(null)">{{ __('Take me off the board') }}</button>
                @else
                    <input type="text" wire:model="statusNote" maxlength="80" class="field-input min-w-0 flex-1 sm:max-w-sm" placeholder="{{ __('Note, e.g. DP resubmission Thu') }}" aria-label="{{ __('Note for the board') }}">
                    <button type="button" class="btn-secondary" style="background: #f6c945; border-color: #e0b42f; color: #3d2f00" wire:click="setMyStatus('help')"><i class="ph ph-hand-waving"></i>{{ __('I need help') }}</button>
                    <button type="button" class="btn-secondary" style="background: #2e7d4f; border-color: #2e7d4f; color: #fff" wire:click="setMyStatus('available')"><i class="ph ph-hand-heart"></i>{{ __("I'm available") }}</button>
                @endif
                @if($statusError) <p class="error-note w-full">{{ $statusError }}</p> @endif
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach([
                'help' => [__('Needs help'), '#f6c945', '#fdf8e6', __('No one has asked for help this week.')],
                'available' => [__('Available'), '#2e7d4f', '#eef6f1', __('No one has marked themselves available yet.')],
            ] as $type => [$heading, $dot, $tint, $empty])
                <div class="card grid content-start gap-3" style="background: {{ $tint }}; border-top: 3px solid {{ $dot }}" wire:key="board-{{ $type }}">
                    <span class="{{ $caps }}">{{ $heading }} · {{ count($board[$type]) }}</span>
                    @forelse($board[$type] as $row)
                        <div class="flex items-baseline gap-2.5" wire:key="board-{{ $type }}-{{ $row['name'] }}">
                            <span class="inline-block shrink-0 rounded-full" style="width: .55rem; height: .55rem; background: {{ $dot }}"></span>
                            <div class="min-w-0">
                                <b class="font-medium text-ink">{{ $row['display'] }}</b>
                                @if($row['note']) <span class="text-sm text-ink-2">· {{ $row['note'] }}</span> @endif
                                @if($row['project'] || $row['boss'])
                                    <div class="truncate text-[0.78rem] text-muted">
                                        @if($row['project']){{ $row['project']->job_number }} {{ $row['project']->job_name }}@endif{{ $row['project'] && $row['boss'] ? ' · ' : '' }}@if($row['boss']){{ __('with :name', ['name' => $row['boss']]) }}@endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-muted">{{ $empty }}</p>
                    @endforelse
                </div>
            @endforeach
        </div>
    </section>
</div>
