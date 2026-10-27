@php
    use App\Support\Format;
    use App\Support\RateBand;

    $rows = $this->rows;
    $showRates = auth()->user()->can('view financials');
    $canEdit = auth()->user()->can('edit billing');
    $canEditTeam = auth()->user()->can('edit team');
    $people = $this->people;
    $cardSorts = ['lastActivity' => __('Last activity'), 'jobName' => __('Project'), 'hoursRecent' => __('Hours, 3 mo'), 'hoursTotal' => __('Hours, all time'), 'billed' => __('Billed'), 'ratePerHour' => __('$/hr')];
@endphp

<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="page-title">{{ __('Projects') }}</h1>
            <p class="page-subtitle">
                @if($picked)
                    {{ __('The projects :name is assigned to, leads or manages, or is on this week. Hours come from the uploaded workbook.', ['name' => $picked->displayName()]) }}
                @else
                    {{ $this->showsAll() ? __('Every project with a billing record. Hours come from the uploaded workbook.') : __('The projects you are assigned to, lead or manage. Hours come from the uploaded workbook.') }}
                @endif
            </p>
        </div>
        @if($canSwitchScope)
            <div class="seg ml-auto">
                <button type="button" wire:click="setScope('mine')" @class(['is-active' => ! $picked && $scope !== 'all'])>{{ __('My projects') }}</button>
                <button type="button" wire:click="setScope('all')" @class(['is-active' => ! $picked && $scope === 'all'])>{{ __('See all') }}</button>
            </div>
        @endif
        @if($canEdit)
            <a href="{{ route('projects.create') }}" class="btn" wire:navigate>
                <i class="ph ph-plus"></i>{{ __('New project') }}
            </a>
        @endif
    </div>

    @if($showRates)
    @php
        $rates = $this->rates;
        $blendedBand = RateBand::classify($rates['blended'], $rates['blendedHours']);
    @endphp
    <section class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="section-title">{{ __('Rate performance') }}</h2>
                <p class="section-note">{{ __('Billed less consultant fees, over every hour logged. Under :hours h a rate is not judged.', ['hours' => RateBand::MinHours]) }}</p>
            </div>
            <div class="seg">
                @foreach(['ongoing' => __('Ongoing'), 'complete' => __('Complete'), 'all' => __('All')] as $value => $label)
                    <button type="button" wire:click="$set('rateScope', '{{ $value }}')" @class(['is-active' => $rateScope === $value])>{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="kpis grid-cols-1 sm:grid-cols-2 xl:grid-cols-4">
            <x-kpi :label="__('Blended $/hr')" :value="$rates['blended'] ? '$'.number_format($rates['blended']).'<small>/h</small>' : '—'" dark>
                {{ $blendedBand['label'] ?? __('no measurable projects') }}
            </x-kpi>
            <x-kpi :label="__('Projects')" :value="$rates['count']">
                {{ $rates['measurable'] }} {{ __('with a usable rate') }}
            </x-kpi>
            <x-kpi :label="__('Billed')" :value="Format::compact($rates['billed'])">
                {{ __('over :hours h logged', ['hours' => Format::number($rates['hours'])]) }}
            </x-kpi>
            <x-kpi :label="__('Under $95/hr')" :value="$rates['buckets']['low']['count']" :band="RateBand::Bands['low']">
                <b>{{ $rates['measurable'] ? Format::pct($rates['buckets']['low']['count'] / $rates['measurable']) : '—' }}</b> {{ __('of the measurable') }} · {{ Format::compact($rates['buckets']['low']['billed']) }} {{ __('billed') }}
            </x-kpi>
        </div>

        <div class="card p-4">
            <div class="flex h-3 w-full overflow-hidden bg-rule">
                @foreach($rates['buckets'] as $key => $bucket)
                    @if($bucket['count'])
                        <span style="flex: {{ $bucket['count'] }}; background: {{ RateBand::Bands[$key]['fill'] ?? RateBand::Unmeasured['fill'] }}" title="{{ RateBand::Bands[$key]['label'] ?? RateBand::Unmeasured['label'] }}"></span>
                    @endif
                @endforeach
            </div>
            <ul class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-ink-2">
                @foreach($rates['buckets'] as $key => $bucket)
                    <li class="flex items-center gap-1.5">
                        <span class="size-2.5" style="background: {{ RateBand::Bands[$key]['fill'] ?? RateBand::Unmeasured['fill'] }}"></span>
                        {{ RateBand::Bands[$key]['label'] ?? RateBand::Unmeasured['label'] }}
                        <b class="font-medium">{{ $bucket['count'] }}</b>
                        <span class="text-muted">{{ Format::compact($bucket['billed']) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
    @endif

    <div class="card p-0">
        <div class="toolbar px-6 pt-5 pb-4">
            <div class="relative w-full max-w-sm">
                <i class="ph ph-magnifying-glass absolute top-1/2 left-3 -translate-y-1/2 text-muted"></i>
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="{{ __('Search project, job #, developer, lead, PM') }}" class="search-input">
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="seg">
                    @foreach($groups as $option)
                        <button type="button" wire:click="$set('group', '{{ $option }}')" @class(['is-active' => $group === $option])>{{ ucfirst($option) }}</button>
                    @endforeach
                </div>
                <select wire:model.live="division" class="select">
                    <option value="">{{ __('All divisions') }}</option>
                    @foreach($divisions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                @if($canPickPerson)
                    <select wire:model.live="person" class="select" aria-label="{{ __('Person') }}">
                        <option value="">{{ __('Anyone') }}</option>
                        @foreach($people as $name => $member)
                            <option value="{{ $name }}">{{ $member->displayName() }}</option>
                        @endforeach
                    </select>
                @endif
                @if($view === 'cards')
                    <select wire:model.live="sort" class="select" aria-label="{{ __('Sort') }}">
                        @foreach($cardSorts as $value => $label)
                            <option value="{{ $value }}">{{ __('Sort: :label', ['label' => $label]) }}</option>
                        @endforeach
                    </select>
                @endif
                <span class="text-xs text-muted">{{ $rows->count() }} {{ __('of') }} {{ $this->projects->count() }}</span>
                <div class="seg" role="group" aria-label="{{ __('View') }}">
                    <button type="button" wire:click="$set('view', 'list')" @class(['is-active' => $view !== 'cards']) title="{{ __('List view') }}"><i class="ph ph-list"></i> {{ __('List') }}</button>
                    <button type="button" wire:click="$set('view', 'cards')" @class(['is-active' => $view === 'cards']) title="{{ __('Card view') }}"><i class="ph ph-squares-four"></i> {{ __('Cards') }}</button>
                </div>
            </div>
        </div>

        @if($view === 'cards')
            <div class="grid gap-4 px-6 pb-6">
                @error('task') <p class="error-note">{{ $message }}</p> @enderror
                @if($rows->isEmpty())
                    <p class="py-10 text-center text-muted">{{ __('No projects match.') }}</p>
                @else
                    <div class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,30rem),1fr))] gap-5">
                        @foreach($rows as $row)
                            @include('livewire.projects.card', ['row' => $row])
                        @endforeach
                    </div>
                @endif
                <p class="text-xs text-muted">
                    @if($showRates)
                        {{ __("Spent: the hours logged on each phase in Mosaic, at each person's charge-out rate (hours with no rate at the :rate/h plan rate). Left: the phase fee less what was spent. Billed: the share of the phase fee invoiced so far, from Month End; the tick on each bar marks it.", ['rate' => Format::money($planRate)]) }}
                    @else
                        {{ __("Fee: the hours a phase's fee pays for. Spent: the hours logged on it in Mosaic, weighted by each person's rate. Left: the difference. Billed: the share of the phase fee invoiced so far; the tick on each bar marks it.") }}
                    @endif
                    {{ __("The card's colour is the whole project's hours against its fee:") }}
                    <b class="font-medium">{{ __('On track') }}</b>: {{ __('hours are keeping pace with billing.') }}
                    <b class="font-medium">{{ __('Watch') }}</b>: {{ __('past 80% of the fee-hours, or hours more than 15 points ahead of the % billed.') }}
                    <b class="font-medium">{{ __('Over') }}</b>: {{ __('past the hours the fee pays for.') }}
                </p>
            </div>
        @else

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <x-sort-header column="jobName" :$sort :$direction>{{ __('Project') }}</x-sort-header>
                        <x-sort-header column="developer" :$sort :$direction>{{ __('Developer') }}</x-sort-header>
                        <x-sort-header column="division" :$sort :$direction>{{ __('Div') }}</x-sort-header>
                        <x-sort-header column="leads" :$sort :$direction>{{ __('Lead') }}</x-sort-header>
                        <x-sort-header column="pms" :$sort :$direction>{{ __('PM') }}</x-sort-header>
                        <x-sort-header column="status" :$sort :$direction>{{ __('Status') }}</x-sort-header>
                        <x-sort-header column="hoursLatest" :$sort :$direction numeric>{{ __('Hrs, latest month') }}</x-sort-header>
                        <x-sort-header column="hoursRecent" :$sort :$direction numeric>{{ __('Hrs, 3 mo') }}</x-sort-header>
                        <x-sort-header column="hoursTotal" :$sort :$direction numeric>{{ __('Hrs, all time') }}</x-sort-header>
                        <x-sort-header column="billed" :$sort :$direction numeric>{{ __('Billed') }}</x-sort-header>
                        <x-sort-header column="ratePerHour" :$sort :$direction numeric>{{ __('$/hr') }}</x-sort-header>
                        <x-sort-header column="lastActivity" :$sort :$direction numeric>{{ __('Last activity') }}</x-sort-header>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr wire:key="prj-{{ $row['jobNumber'] }}">
                            <td>
                                <a href="{{ route('projects.show', $row['jobNumber']) }}" class="font-medium text-ink hover:text-navy" wire:navigate>{{ $row['jobName'] }}</a>
                                <span class="ml-1 text-xs text-muted">{{ $row['jobNumber'] }}</span>
                            </td>
                            <td class="max-w-[14rem] truncate">{{ $row['developer'] ?? '—' }}</td>
                            <td>{{ $row['division'] ?? '—' }}</td>
                            <td>{{ $row['leads'] ?: '—' }}</td>
                            <td>{{ $row['pms'] ?: '—' }}</td>
                            <td>
                                @if($canEdit)
                                    <flux:dropdown>
                                        <button type="button" @class(['status', "status-{$row['status']}", 'is-override' => $row['statusOverridden']])>{{ ucfirst($row['status']) }}</button>
                                        <flux:menu>
                                            @foreach($statuses as $status)
                                                <flux:menu.item wire:click="setStatus('{{ $row['jobNumber'] }}', '{{ $status }}')">{{ ucfirst($status) }}</flux:menu.item>
                                            @endforeach
                                            <flux:menu.separator />
                                            <div class="px-2 py-1 text-xs text-muted">{{ __('Paused is a person\'s decision; Active and Closed follow the Active flag.') }}</div>
                                        </flux:menu>
                                    </flux:dropdown>
                                @else
                                    <span @class(['status', "status-{$row['status']}"])>{{ ucfirst($row['status']) }}</span>
                                @endif
                            </td>
                            <td class="is-num">{{ Format::number($row['hoursLatest'], 1) }}</td>
                            <td class="is-num">{{ Format::number($row['hoursRecent'], 1) }}</td>
                            <td class="is-num">{{ Format::number($row['hoursTotal']) }}</td>
                            <td class="is-num">{{ Format::compact($row['billed']) }}</td>
                            <td class="is-num"><x-rate-chip :rate="$row['ratePerHour']" :hours="$row['hoursTotal']" /></td>
                            <td class="is-num text-muted">{{ Format::shortMonth($row['lastActivity']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="py-10 text-center text-muted">{{ __('No projects match.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
