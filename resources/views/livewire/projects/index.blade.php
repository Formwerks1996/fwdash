@php
    use App\Support\Format;
    use App\Support\RateBand;

    $rates = $this->rates;
    $rows = $this->rows;
    $blendedBand = RateBand::classify($rates['blended'], $rates['blendedHours']);
    $canEdit = auth()->user()->can('edit billing');
@endphp

<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="page-title">{{ __('Projects') }}</h1>
            <p class="page-subtitle">
                {{ $this->showsAll() ? __('Every project with a billing record. Hours come from the uploaded workbook.') : __('The projects you are assigned to, lead or manage. Hours come from the uploaded workbook.') }}
            </p>
        </div>
        @if($canSwitchScope)
            <div class="seg ml-auto">
                <button type="button" wire:click="setScope('mine')" @class(['is-active' => $scope !== 'all'])>{{ __('My projects') }}</button>
                <button type="button" wire:click="setScope('all')" @class(['is-active' => $scope === 'all'])>{{ __('See all') }}</button>
            </div>
        @endif
        @if($canEdit)
            <a href="{{ route('projects.create') }}" class="btn" wire:navigate>
                <i class="ph ph-plus"></i>{{ __('New project') }}
            </a>
        @endif
    </div>

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
                <span class="text-xs text-muted">{{ $rows->count() }} {{ __('of') }} {{ $this->projects->count() }}</span>
            </div>
        </div>

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
                            <td colspan="12"class="py-10 text-center text-muted">{{ __('No projects match.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
