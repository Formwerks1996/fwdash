@php
    use App\Billing\ProjectBurn;
    use App\Livewire\Projects\ProjectsIndex;
    use App\Support\Format;

    $burn = $this->burns[$row['jobNumber']];
    $jobTasks = collect($this->tasks[$row['jobNumber']] ?? []);
    $key = ProjectsIndex::taskKey($row['jobNumber']);
    $tones = [
        'ok' => ['bg-[#eef7f1] border-[#cfe6d7]', '#2e7d4f', '#3e5c47', '#2f4a38'],
        'idle' => ['bg-[#eef7f1] border-[#cfe6d7]', '#2e7d4f', '#3e5c47', '#2f4a38'],
        'watch' => ['bg-[#fdf6dc] border-[#efe0a6]', '#d9a400', '#6b5513', '#57450f'],
        'over' => ['bg-[#fbebea] border-[#f0c9c6]', '#c62828', '#7a3a33', '#652f29'],
        'none' => ['bg-white border-line', '#00263e', '#5c6873', '#44515e'],
    ];
    $words = ['over' => [__('Over'), '#c62828'], 'watch' => [__('Watch'), '#8e6c00'], 'ok' => [__('On track'), '#2e7d4f'], 'idle' => [__('No hours yet'), '#5c6873'], 'none' => [__('No fee'), '#5c6873']];
    $phaseStates = ['complete' => __('Complete'), 'current' => __('Current'), 'not started' => __('Not started'), 'billing' => __('Billing')];
    [$tint, $rule, $muted, $secondary] = $tones[$burn['level']];
    [$word, $wordColor] = $words[$burn['level']];
    // Staff see the phase money as hours: a fee or spend over the plan rate.
    $inDollars = auth()->user()->can('view financials');
    $amount = fn (?float $dollars) => $dollars === null ? '—' : ($inDollars ? Format::compact($dollars) : ($dollars < 0 ? '−' : '').Format::number(abs($dollars) / $planRate).' h');
    $firstNames = fn (string $names) => collect(explode(', ', $names))->filter()->map(fn ($name) => ($people[$name] ?? null)?->firstName() ?? $name)->implode(', ');
@endphp

<article class="grid content-start gap-3 border border-t-2 p-4 {{ $tint }}" style="border-top-color: {{ $rule }}" wire:key="prj-card-{{ $row['jobNumber'] }}">
    <div class="flex items-start justify-between gap-4">
        <a href="{{ route('projects.show', $row['jobNumber']) }}" wire:navigate class="group min-w-0 text-left">
            <b class="font-medium text-gold-text">{{ $row['jobNumber'] }}</b>
            <span class="text-[1.1rem] decoration-gold underline-offset-[3px] group-hover:underline">{{ $row['jobName'] }}</span>
            <i class="ph ph-arrow-up-right text-[0.8rem] text-muted"></i>
            <div class="mt-0.5 text-[0.8rem]" style="color: {{ $muted }}">
                {{ $row['developer'] ?? '' }}@if($row['developer'] && $burn['currentPhase']) · @endif
                @if($burn['currentPhase']){{ __('now in') }} <b class="font-medium" style="color: {{ $secondary }}">{{ $burn['currentPhase'] }}</b>@endif
            </div>
        </a>
        <div class="grid text-right text-[0.78rem] whitespace-nowrap text-navy tabular-nums">
            <span>{{ __(':hours h last month', ['hours' => Format::number($row['hoursLatest'] ?? 0)]) }}</span>
            <span style="color: {{ $muted }}">{{ __(':hours h total', ['hours' => Format::number($burn['hours'])]) }}</span>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[0.82rem]" style="color: {{ $secondary }}">
        <span @class(['status', "status-{$row['status']}"])>{{ ucfirst($row['status']) }}</span>
        <span><span style="color: {{ $muted }}">{{ __('Lead') }}</span> {{ $firstNames($row['leads']) ?: '—' }}</span>
        <span><span style="color: {{ $muted }}">{{ __('PM') }}</span> {{ $firstNames($row['pms']) ?: '—' }}</span>
    </div>

    <div class="text-[0.9rem]">
        @if($burn['fee'] !== null && $burn['spent'] !== null)
            @php $left = $burn['fee'] - $burn['spent']; @endphp
            <b class="font-medium">{{ __('Project:') }}</b>
            @if($inDollars)
                {{ __(':spent spent on its fee phases at charge-out, of a :fee fee; :billed billed', ['spent' => $amount($burn['spent']), 'fee' => $amount($burn['fee']), 'billed' => $amount($burn['billedFee'])]) }}
            @else
                {{ __(':spent of the :fee its fee pays for, :billed billed', ['spent' => $amount($burn['spent']), 'fee' => $amount($burn['fee']), 'billed' => Format::pct($burn['progress'])]) }}
            @endif
            · <b class="font-medium" style="color: {{ $left < 0 ? '#c62828' : '#2e7d4f' }}">{{ $left < 0 ? __(':amount over', ['amount' => $amount(-$left)]) : __(':amount left', ['amount' => $amount($left)]) }}</b>
        @elseif($burn['fee'] !== null)
            <b class="font-medium">{{ __('Project:') }}</b>
            {{ __(':used of its fee-hours used, :billed billed', ['used' => ProjectBurn::burnPct($burn['used']), 'billed' => Format::pct($burn['progress'])]) }}
            · <b class="font-medium" style="color: {{ $wordColor }}">{{ $word }}</b>
        @else
            <b class="font-medium">{{ __('Project:') }}</b> {{ __('no phase fees to measure against') }}
        @endif
        <small class="mt-0.5 block text-xs" style="color: {{ $muted }}">{{ __('Hours as of :month; :hours h in the last 3 months', ['month' => Format::month($asOf), 'hours' => Format::number($row['hoursRecent'] ?? 0)]) }}</small>
    </div>

    @if($burn['phases'])
        @php $hasSpent = $burn['spent'] !== null; @endphp
        <div class="grid gap-px text-[0.8rem]">
            <div class="grid grid-cols-[4.2rem_3.6rem_7.2rem_minmax(2rem,1fr)_2.8rem_4.4rem] gap-2 px-1 text-xs tracking-[0.12em] uppercase" style="color: {{ $muted }}">
                <span>{{ __('Phase') }}</span><span class="text-right">{{ __('Fee') }}</span><span class="text-right">{{ __('Spent') }}</span><span></span><span class="text-right">{{ __('Billed') }}</span><span class="text-right">{{ __('Left') }}</span>
            </div>
            @php
                $barColourFor = fn (?float $share, float $progress) => match (true) {
                    $share === null => '#c9ced3',
                    $share >= 1 => '#c62828',
                    $share >= ProjectBurn::WatchAt || $share - $progress > ProjectBurn::AheadBy => '#d9a400',
                    default => '#2e7d4f',
                };
                $totalShare = $hasSpent ? $burn['spent'] / $burn['fee'] : null;
                $totalLeft = $hasSpent ? $burn['fee'] - $burn['spent'] : null;
            @endphp
            <div class="mb-1 grid grid-cols-[4.2rem_3.6rem_7.2rem_minmax(2rem,1fr)_2.8rem_4.4rem] items-center gap-2 border-b border-black/10 px-1 pt-[0.2rem] pb-1.5 font-medium tabular-nums" wire:key="prj-ph-{{ $row['jobNumber'] }}-total">
                <span>{{ __('Total') }}</span>
                <span class="text-right">{{ $amount($burn['fee']) }}</span>
                <span class="text-right">{{ $hasSpent ? $amount($burn['spent']) : '—' }} @if($inDollars)<small class="font-normal" style="color: {{ $muted }}">{{ Format::number($burn['spentHours']) }} h</small>@endif</span>
                <span class="relative h-2.5 bg-black/8">
                    <i class="block h-full" style="width: {{ min(1, $totalShare ?? 0) * 100 }}%; background: {{ $barColourFor($totalShare, (float) $burn['progress']) }}"></i>
                    <i class="absolute top-[-2px] h-[calc(100%+4px)] w-px bg-navy/60" style="left: {{ $burn['progress'] * 100 }}%" title="{{ __('Billed') }}"></i>
                </span>
                <span class="text-right">{{ Format::pct($burn['progress']) }}</span>
                <span class="text-right" style="color: {{ $totalLeft === null ? $muted : ($totalLeft < 0 ? '#c62828' : $secondary) }}">{{ $amount($totalLeft) }}</span>
            </div>
            @foreach($burn['phases'] as $phase)
                @php
                    $isCurrent = $phase['state'] === 'current';
                    $share = $hasSpent ? $phase['spent'] / $phase['fee'] : null;
                    $phaseLeft = $hasSpent ? $phase['fee'] - $phase['spent'] : null;
                    $barColour = $barColourFor($share, $phase['progress']);
                @endphp
                <div @class(['grid grid-cols-[4.2rem_3.6rem_7.2rem_minmax(2rem,1fr)_2.8rem_4.4rem] items-center gap-2 px-1 py-[0.2rem] tabular-nums', 'bg-white/60' => $isCurrent, 'opacity-55' => $phase['state'] === 'not started' && ! $phase['spentHours']]) wire:key="prj-ph-{{ $row['jobNumber'] }}-{{ $phase['phase'] }}" title="{{ $phaseStates[$phase['state']] }}">
                    <span @class(['truncate', 'font-medium text-gold-text' => $isCurrent])>{{ $phase['phase'] }}</span>
                    <span class="text-right">{{ $amount($phase['fee']) }}</span>
                    <span class="text-right">{{ $hasSpent ? $amount($phase['spent']) : '—' }} @if($inDollars)<small style="color: {{ $muted }}">{{ Format::number($phase['spentHours']) }} h</small>@endif</span>
                    <span class="relative h-1.5 bg-black/8">
                        <i class="block h-full" style="width: {{ min(1, $share ?? 0) * 100 }}%; background: {{ $barColour }}"></i>
                        <i class="absolute top-[-2px] h-[calc(100%+4px)] w-px bg-navy/60" style="left: {{ $phase['progress'] * 100 }}%" title="{{ __('Billed') }}"></i>
                    </span>
                    <span class="text-right">{{ Format::pct($phase['progress']) }}</span>
                    <span class="text-right font-medium" style="color: {{ $phaseLeft === null ? $muted : ($phaseLeft < 0 ? '#c62828' : $secondary) }}">{{ $amount($phaseLeft) }}</span>
                </div>
            @endforeach
            @if($burn['otherPhases'])
                <div class="mt-1.5 border-t border-black/10 px-1 pt-1.5 text-xs tracking-[0.12em] uppercase" style="color: {{ $muted }}">{{ __('Other phases · no fee, not counted above') }}</div>
                @foreach($burn['otherPhases'] as $other)
                    <div class="grid grid-cols-[4.2rem_3.6rem_7.2rem_minmax(2rem,1fr)_2.8rem_4.4rem] items-center gap-2 px-1 py-[0.2rem] tabular-nums" style="color: {{ $secondary }}" wire:key="prj-other-{{ $row['jobNumber'] }}-{{ $loop->index }}">
                        <span class="col-span-2 leading-tight">{{ $other['name'] }}</span>
                        <span class="text-right">{{ $amount($other['spent']) }} @if($inDollars)<small style="color: {{ $muted }}">{{ Format::number($other['hours']) }} h</small>@endif</span>
                    </div>
                @endforeach
            @endif
        </div>
    @else
        <p class="text-[0.85rem]" style="color: {{ $muted }}">{{ __('No phase fees in the billing database for this job, so there are no budgets to track.') }}</p>
    @endif

    <div class="grid gap-0.5 border-t border-black/10 pt-2.5">
        <span class="mb-0.5 text-xs font-medium tracking-[0.18em] text-muted uppercase">{{ __('Tasks') }} · {{ __(':count open', ['count' => $jobTasks->whereNull('done_at')->count()]) }}</span>
        @foreach($jobTasks as $task)
            <div class="group grid grid-cols-[auto_minmax(0,1fr)_auto_3.4rem_auto] items-center gap-2.5 px-1 py-[0.2rem] text-[0.85rem] hover:bg-white/55" wire:key="prj-task-{{ $task->id }}">
                <input type="checkbox" class="accent-navy" @checked($task->done_at) @disabled(! $canEditTeam) wire:click="toggleTask({{ $task->id }})" aria-label="{{ __('Done') }}">
                <span @class(['truncate', 'text-muted line-through' => $task->done_at])>{{ $task->title }}</span>
                <span class="text-xs" style="color: {{ $secondary }}">{{ $task->assignee ? ($people[$task->assignee] ?? null)?->firstName() ?? $task->assignee : '' }}</span>
                <span class="text-right text-xs tabular-nums" style="color: {{ $muted }}">{{ $task->due_on?->format('M j') }}</span>
                @if($canEditTeam)
                    <button type="button" class="text-muted opacity-0 group-hover:opacity-100 hover:text-danger focus:opacity-100" wire:click="deleteTask({{ $task->id }})" wire:confirm="{{ __('Delete this task?') }}" aria-label="{{ __('Delete task') }}"><i class="ph ph-x"></i></button>
                @else
                    <span></span>
                @endif
            </div>
        @endforeach
        @if($canEditTeam)
            <form class="mt-1 grid grid-cols-[minmax(0,1fr)_auto_auto] gap-1.5" wire:submit="addTask(@js($row['jobNumber']))">
                <input type="text" maxlength="200" wire:model="newTask.{{ $key }}.title" placeholder="{{ __('Add a task') }}" class="w-full border border-dashed border-line bg-transparent px-2 py-1 text-[0.85rem] focus:border-solid focus:border-gold focus:outline-none" aria-label="{{ __('New task') }}">
                <select wire:model="newTask.{{ $key }}.assignee" class="border border-dashed border-line bg-transparent px-1 text-xs" aria-label="{{ __('Who') }}">
                    <option value="">{{ __('Who') }}</option>
                    @foreach($people as $name => $member)
                        <option value="{{ $name }}">{{ $member->displayName() }}</option>
                    @endforeach
                </select>
                <input type="date" wire:model="newTask.{{ $key }}.due_on" class="border border-dashed border-line bg-transparent px-1 text-xs" aria-label="{{ __('Due') }}">
            </form>
        @endif
    </div>
</article>
