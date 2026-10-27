@php
    use App\Billing\ProjectBurn;
    use App\Livewire\Team\PmDesk;
    use App\Models\ProjectRole;
    use App\Support\Format;

    $me = $this->me;
    $people = $this->people;
    $projects = $this->projects;
    $tags = $this->tags;
    $tasks = $this->tasks;
    $lastMonthHours = $projects->sum('hoursLastMonth');
    $jobNames = $projects->pluck('burn.jobName', 'burn.jobNumber');
    $tones = [
        'ok' => ['bg-[#eef7f1] border-[#cfe6d7]', '#2e7d4f', '#3e5c47', '#2f4a38'],
        'idle' => ['bg-[#eef7f1] border-[#cfe6d7]', '#2e7d4f', '#3e5c47', '#2f4a38'],
        'watch' => ['bg-[#fdf6dc] border-[#efe0a6]', '#d9a400', '#6b5513', '#57450f'],
        'over' => ['bg-[#fbebea] border-[#f0c9c6]', '#c62828', '#7a3a33', '#652f29'],
        'none' => ['bg-white border-line', '#00263e', '#5c6873', '#44515e'],
    ];
    $words = ['over' => [__('Over'), '#c62828'], 'watch' => [__('Watch'), '#8e6c00'], 'ok' => [__('On track'), '#2e7d4f'], 'idle' => [__('No hours yet'), '#5c6873'], 'none' => [__('No fee'), '#5c6873']];
    $phaseStates = ['complete' => __('Complete'), 'current' => __('Current'), 'not started' => __('Not started'), 'billing' => __('Billing')];
    $caps = 'text-xs font-medium tracking-[0.18em] text-muted uppercase';
@endphp

<div class="grid max-w-[1180px] gap-8">
    <header class="flex flex-wrap items-end justify-between gap-x-8 gap-y-4 border-b border-gold-line pb-5">
        <div>
            <h1 class="page-title">{{ __('PM Desk') }}</h1>
            <p class="mt-1 max-w-[60ch] text-[0.92rem] text-ink-2">{{ __("One person's week: their team, their projects and how far along each one is.") }}</p>
        </div>
        @if($canSwitch)
        <label class="grid gap-1">
            <span class="text-xs font-medium tracking-[0.18em] text-muted uppercase">{{ __('Viewing') }}</span>
            <select class="min-w-[15rem] border border-line bg-white px-2 py-1.5 text-[0.92rem]" x-on:change="$wire.switchTo($event.target.value)">
                @if($me->isPartner())
                    <option value="{{ $me->name }}" selected>{{ $me->displayName() }}</option>
                @endif
                <optgroup label="{{ __('Project managers') }}">
                    @foreach($pms as $person)
                        <option value="{{ $person->name }}" @selected($person->name === $member)>{{ $person->displayName() }}</option>
                    @endforeach
                </optgroup>
                <optgroup label="{{ __('Team') }}">
                    @foreach($staff as $person)
                        <option value="{{ $person->name }}" @selected($person->name === $member)>{{ $person->displayName() }}</option>
                    @endforeach
                </optgroup>
            </select>
        </label>
        @endif
    </header>

    <section class="grid grid-cols-2 items-end gap-6 min-[900px]:grid-cols-[1.4fr_1fr_1fr_1fr]">
        <div class="col-span-2 min-w-0 min-[900px]:col-span-1">
            <span class="{{ $caps }}">{{ __('Week of :from – :to', ['from' => $monday->format('M j'), 'to' => $monday->addDays(4)->format('M j')]) }}</span>
            <h2 class="mt-1 text-[clamp(1.25rem,2vw,1.6rem)] tracking-[0.06em] break-words uppercase">{{ $me->displayName() }}</h2>
        </div>
        <div class="grid gap-0.5 border-l border-gold-line pl-4">
            <span class="{{ $caps }}">{{ __('Reports to') }}</span>
            <b class="text-[1.15rem] font-normal">{{ $boss ? $people[$boss]->displayName() : __('Not placed this week') }}</b>
            @if($chainTop) <small class="text-[0.78rem] text-muted">{{ __('under :partner', ['partner' => $people[$chainTop]->displayName()]) }}</small> @endif
        </div>
        <div class="grid gap-0.5 border-l border-gold-line pl-4">
            <span class="{{ $caps }}">{{ __('Your team') }}</span>
            <b class="text-[1.15rem] font-normal">{{ $team ? trans_choice(':count person|:count people', count($team), ['count' => count($team)]) : __('No one yet') }}</b>
            <small class="text-[0.78rem] text-muted">
                @if($team)
                    {{ collect($team)->map(fn ($name) => $people[$name]->firstName())->implode(', ') }}
                @else
                    @can('view office')
                        <a href="{{ route('lineup') }}" class="underline-offset-2 hover:underline" wire:navigate>{{ __('Set in Team Lineup') }}</a>
                    @endcan
                @endif
            </small>
        </div>
        <div class="grid gap-0.5 border-l border-gold-line pl-4">
            <span class="{{ $caps }}">{{ __('Hours last month') }}</span>
            <b class="text-[1.15rem] font-normal tabular-nums">{{ Format::number($lastMonthHours) }} h</b>
            <small class="text-[0.78rem] text-muted">{{ __('across :count projects', ['count' => $projects->count()]) }} · {{ __('as of :month', ['month' => Format::month($asOf)]) }}</small>
        </div>
    </section>

    @if($team)
        <section class="grid gap-2.5">
            <span class="{{ $caps }}">{{ __('Your team this week') }}</span>
            <div class="grid grid-cols-[repeat(auto-fill,minmax(15rem,1fr))] gap-x-6 gap-y-1.5">
                @foreach($team as $name)
                    @php $tag = $tags[$name] ?? null; @endphp
                    <div class="grid border-l border-navy py-1.5 pl-3" wire:key="pd-team-{{ $name }}">
                        <b class="font-normal">{{ $people[$name]->displayName() }}</b>
                        <span class="truncate text-[0.78rem] text-muted">{{ $tag ? $tag.' '.($jobNames[$tag] ?? '') : __('No project tagged') }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="grid gap-4">
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 border-b border-gold-line pb-2">
            <h3 class="text-xl">{{ __('Your projects') }}</h3>
            <span class="{{ $caps }}">{{ __('Led or managed by you, assigned to you, or tagged to you or your team this week') }}</span>
        </div>

        @if($canEdit)
            <div class="flex flex-wrap items-start gap-3">
                <div class="relative w-full max-w-sm">
                    <i class="ph ph-magnifying-glass absolute top-[1.05rem] left-3 -translate-y-1/2 text-muted"></i>
                    <input type="search" wire:model.live.debounce.250ms="projectSearch" class="search-input" placeholder="{{ __('Add a project: job number or name') }}" aria-label="{{ __('Add a project') }}">
                    @if($matches->isNotEmpty())
                        <div class="absolute inset-x-0 top-full z-10 mt-1 border border-line border-t-2 border-t-gold bg-white shadow-lg">
                            @foreach($matches as $project)
                                <button type="button" class="flex w-full gap-2 px-3 py-1.5 text-left text-sm hover:bg-gold-soft" wire:click="claim(@js($project->job_number))" wire:key="pd-match-{{ $project->job_number }}">
                                    <b class="font-medium text-gold-text">{{ $project->job_number }}</b> <span class="truncate">{{ $project->job_name }}</span>
                                </button>
                            @endforeach
                        </div>
                    @elseif(trim($projectSearch) !== '')
                        <p class="mt-1 text-xs text-muted">{{ __('No project on the list matches.') }}</p>
                    @endif
                </div>
                @if($untaggedClaims)
                    <button type="button" class="btn-secondary" wire:click="claimTagged" title="{{ __('Assign every project tagged to :first or their team this week', ['first' => $me->firstName()]) }}">
                        <i class="ph ph-hand-grabbing"></i>{{ trans_choice('Make the tagged project theirs|Make the :count tagged projects theirs', $untaggedClaims, ['count' => $untaggedClaims]) }}
                    </button>
                @endif
            </div>
            @error('assignment') <p class="error-note">{{ $message }}</p> @enderror
        @endif

        @if($projects->isEmpty())
            <p class="text-sm text-muted">{{ __('No project is assigned to this person yet.') }} @if($canEdit) {{ __("Use 'Add a project' to claim one.") }} @endif</p>
        @else
            <div class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,30rem),1fr))] gap-5">
                @foreach($projects as $entry)
                    @php
                        $row = [...$entry['burn'], ...$entry];
                        [$tint, $rule, $muted, $secondary] = $tones[$row['level']];
                        [$word, $wordColor] = $words[$row['level']];
                        $jobTasks = collect($tasks[$row['jobNumber']] ?? []);
                        $key = PmDesk::taskKey($row['jobNumber']);
                    @endphp
                    <article class="grid content-start gap-3 border border-t-2 p-4 {{ $tint }}" style="border-top-color: {{ $rule }}" wire:key="pd-card-{{ $row['jobNumber'] }}">
                        <div class="flex items-start justify-between gap-4">
                            <a @if($canOpenProjects) href="{{ route('projects.show', $row['jobNumber']) }}" wire:navigate @endif class="group min-w-0 text-left">
                                <b class="font-medium text-gold-text">{{ $row['jobNumber'] }}</b>
                                <span class="text-[1.1rem] decoration-gold underline-offset-[3px] group-hover:underline">{{ $row['jobName'] }}</span>
                                @if($canOpenProjects) <i class="ph ph-arrow-up-right text-[0.8rem] text-muted"></i> @endif
                                <div class="mt-0.5 text-[0.8rem]" style="color: {{ $muted }}">
                                    {{ $row['developer'] ?? '' }}@if($row['developer'] && $row['currentPhase']) · @endif
                                    @if($row['currentPhase']){{ __('now in') }} <b class="font-medium" style="color: {{ $secondary }}">{{ $row['currentPhase'] }}</b>@endif
                                </div>
                            </a>
                            <div class="grid text-right text-[0.78rem] whitespace-nowrap text-navy tabular-nums">
                                <span>{{ __(':hours h last month', ['hours' => Format::number($row['hoursLastMonth'])]) }}</span>
                                <span style="color: {{ $muted }}">{{ __(':hours h total', ['hours' => Format::number($row['hours'])]) }}</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[0.82rem]" style="color: {{ $secondary }}">
                            @if($row['myRole'])
                                <span class="chip">{{ $row['myRole'] === ProjectRole::Lead ? __('You lead this') : __('You’re the PM') }}</span>
                            @endif
                            <span><span style="color: {{ $muted }}">{{ __('Lead') }}</span> {{ $row['leads'] ? collect($row['leads'])->map(fn ($name) => ($people[$name] ?? null)?->firstName() ?? $name)->implode(', ') : '—' }}</span>
                            <span><span style="color: {{ $muted }}">{{ __('PM') }}</span> {{ $row['pms'] ? collect($row['pms'])->map(fn ($name) => ($people[$name] ?? null)?->firstName() ?? $name)->implode(', ') : '—' }}</span>
                        </div>

                        <div class="text-[0.9rem]">
                            @if($row['fee'] !== null)
                                <b class="font-medium">{{ __('Project:') }}</b>
                                {{ __(':used of its fee-hours used, :billed billed', ['used' => ProjectBurn::burnPct($row['used']), 'billed' => Format::pct($row['progress'])]) }}
                                · <b class="font-medium" style="color: {{ $wordColor }}">{{ $word }}</b>
                            @else
                                <b class="font-medium">{{ __('Project:') }}</b> {{ __('no phase fees to measure against') }}
                            @endif
                            <small class="mt-0.5 block text-xs" style="color: {{ $muted }}">{{ __('Hours as of :month; :hours h in the last 3 months', ['month' => Format::month($asOf), 'hours' => Format::number($row['hoursRecent'])]) }}</small>
                        </div>

                        @if($row['phases'])
                            <div class="grid gap-px text-[0.8rem]">
                                <div class="grid grid-cols-[4.6rem_6.6rem_minmax(3rem,1fr)_2.6rem_5.4rem] gap-2 px-1 text-xs tracking-[0.12em] uppercase" style="color: {{ $muted }}">
                                    <span>{{ __('Phase') }}</span><span>{{ __('Fee hours') }}</span><span></span><span class="text-right">{{ __('Billed') }}</span><span></span>
                                </div>
                                @foreach($row['phases'] as $phase)
                                    @php $isCurrent = $phase['state'] === 'current'; @endphp
                                    <div @class(['grid grid-cols-[4.6rem_6.6rem_minmax(3rem,1fr)_2.6rem_5.4rem] items-center gap-2 px-1 py-[0.2rem] tabular-nums', 'bg-white/60' => $isCurrent, 'opacity-55' => $phase['state'] === 'not started']) wire:key="pd-ph-{{ $row['jobNumber'] }}-{{ $phase['phase'] }}">
                                        <span @class(['truncate', 'font-medium text-gold-text' => $isCurrent])>{{ $phase['phase'] }}</span>
                                        <span>≈{{ Format::number($phase['feeHours']) }} h</span>
                                        <span class="h-1.5 bg-black/8"><i class="block h-full" style="width: {{ $phase['progress'] * 100 }}%; background: {{ $phase['state'] === 'complete' ? '#8fb9a0' : '#2e7d4f' }}"></i></span>
                                        <span class="text-right">{{ Format::pct($phase['progress']) }}</span>
                                        <span class="text-right" style="color: {{ $secondary }}">{{ $phaseStates[$phase['state']] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-[0.85rem]" style="color: {{ $muted }}">{{ __('No phase fees in the billing database for this job, so there are no budgets to track.') }}</p>
                        @endif

                        <p class="text-[0.82rem]" style="color: {{ $secondary }}">
                            @if($row['taggedBy'])
                                {{ __('Tagged this week: :names', ['names' => collect($row['taggedBy'])->map(fn ($name) => $people[$name]->firstName())->implode(', ')]) }}
                            @else
                                {{ __('No one on your team is tagged to this project this week') }}
                            @endif
                        </p>

                        <div class="grid gap-0.5 border-t border-black/10 pt-2.5">
                            <div class="mb-0.5 flex items-baseline justify-between">
                                <span class="{{ $caps }}">{{ __('Tasks') }} · {{ __(':count open', ['count' => $jobTasks->whereNull('done_at')->count()]) }}</span>
                            </div>
                            @foreach($jobTasks as $task)
                                <div class="group grid grid-cols-[auto_minmax(0,1fr)_auto_3.4rem_auto] items-center gap-2.5 px-1 py-[0.2rem] text-[0.85rem] hover:bg-white/55" wire:key="pd-task-{{ $task->id }}">
                                    <input type="checkbox" class="accent-navy" @checked($task->done_at) @disabled(! $canEdit) wire:click="toggleTask({{ $task->id }})" aria-label="{{ __('Done') }}">
                                    <span @class(['truncate', 'text-muted line-through' => $task->done_at])>{{ $task->title }}</span>
                                    <span class="text-xs" style="color: {{ $secondary }}">{{ $task->assignee ? ($people[$task->assignee] ?? null)?->firstName() ?? $task->assignee : '' }}</span>
                                    <span class="text-right text-xs tabular-nums" style="color: {{ $muted }}">{{ $task->due_on?->format('M j') }}</span>
                                    @if($canEdit)
                                        <button type="button" class="text-muted opacity-0 group-hover:opacity-100 hover:text-danger focus:opacity-100" wire:click="deleteTask({{ $task->id }})" wire:confirm="{{ __('Delete this task?') }}" aria-label="{{ __('Delete task') }}"><i class="ph ph-x"></i></button>
                                    @else
                                        <span></span>
                                    @endif
                                </div>
                            @endforeach
                            @if($canEdit)
                                <form class="mt-1 grid grid-cols-[minmax(0,1fr)_auto_auto] gap-1.5" wire:submit="addTask(@js($row['jobNumber']))">
                                    <input type="text" maxlength="200" wire:model="newTask.{{ $key }}.title" placeholder="{{ __('Add a task') }}" class="w-full border border-dashed border-line bg-transparent px-2 py-1 text-[0.85rem] focus:border-solid focus:border-gold focus:outline-none" aria-label="{{ __('New task') }}">
                                    <select wire:model="newTask.{{ $key }}.assignee" class="border border-dashed border-line bg-transparent px-1 text-xs" aria-label="{{ __('Who') }}">
                                        <option value="">{{ __('Who') }}</option>
                                        @foreach(collect([$member, ...$team, ...$row['taggedBy']])->unique() as $name)
                                            <option value="{{ $name }}">{{ $people[$name]->firstName() }}</option>
                                        @endforeach
                                    </select>
                                    <input type="date" wire:model="newTask.{{ $key }}.due_on" class="border border-dashed border-line bg-transparent px-1 text-xs" aria-label="{{ __('Due') }}">
                                </form>
                                @error('task') <p class="text-xs text-danger">{{ $message }}</p> @enderror
                            @endif
                        </div>

                        @if($canEdit && $row['assigned'])
                            <button type="button" class="justify-self-start text-xs underline-offset-2 hover:underline" style="color: {{ $muted }}" wire:click="release(@js($row['jobNumber']))">{{ __('Remove from :first’s projects', ['first' => $me->firstName()]) }}</button>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <p class="text-xs text-muted">
        {{ __("Fee hours: the hours a phase's fee pays for at the plan rate (:rate/h). Billed: the share of the phase fee invoiced so far, from Month End.", ['rate' => Format::money($rate)]) }}
        {{ __("The card's colour is the whole project's hours against its fee:") }}
        <b class="font-medium">{{ __('On track') }}</b>: {{ __('hours are keeping pace with billing.') }}
        <b class="font-medium">{{ __('Watch') }}</b>: {{ __('past 80% of the fee-hours, or hours more than 15 points ahead of the % billed.') }}
        <b class="font-medium">{{ __('Over') }}</b>: {{ __('past the hours the fee pays for.') }}
        {{ __('Hours come from the uploaded workbook, one total per project per month; phase-level hours are not tracked.') }}
    </p>
</div>
