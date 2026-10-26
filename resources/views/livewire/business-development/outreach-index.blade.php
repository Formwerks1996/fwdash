@php
    use App\Livewire\BusinessDevelopment\OutreachIndex;

    $all = $this->prospects;
    $rows = $this->rows;
    $chip = fn (bool $on) => $on ? 'chip is-on cursor-pointer' : 'chip cursor-pointer';
@endphp

<div class="space-y-8">
    <div>
        <h1 class="page-title">{{ __('Outreach List') }}</h1>
        <p class="page-subtitle">{{ __('BC developers to approach, from the prospects research') }}@if($built) · {{ __('built :date', ['date' => $built]) }}@endif. {{ __('Click one for its page.') }}</p>
    </div>

    @if($all->isEmpty())
        <div class="note">{{ __('No outreach research loaded yet. An admin loads it with `php artisan outreach:import outreach.json`.') }}</div>
    @else
        <div class="kpis grid-cols-1 sm:grid-cols-2 xl:grid-cols-4">
            <x-kpi :label="__('Prospects')" :value="$all->count()" dark>{{ __('BC developers not yet working with us') }}</x-kpi>
            <x-kpi :label="__('Tier A + B')" :value="$all->where('score', '>=', 4)->count()">{{ __('fit 4–5: repeat, phased, a style we do') }}</x-kpi>
            <x-kpi :label="__('Named contact')" :value="$all->where('has_contact', true)->count()">
                {{ $all->where('has_linkedin', true)->count() }} {{ __('with LinkedIn') }} · {{ $all->filter(fn ($p) => filled($p->person['email'] ?? null))->count() }} {{ __('with their own email') }}
            </x-kpi>
            <x-kpi :label="__('Full profiles')" :value="$all->filter(fn ($p) => in_array('In deck', $p->tags, true))->count()">
                {{ __('in the regional decks') }} · {{ $projectCount }} {{ __('projects mapped') }}@if($mapUrl) · <a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="underline">{{ __('open the map') }}</a>@endif
            </x-kpi>
        </div>

        <div class="card p-0">
            <div class="space-y-3 px-6 pt-5 pb-4">
                <div class="toolbar">
                    <div class="relative w-full max-w-sm">
                        <i class="ph ph-magnifying-glass absolute top-1/2 left-3 -translate-y-1/2 text-muted"></i>
                        <input type="search" wire:model.live.debounce.250ms="search" placeholder="{{ __('Search developer, city, project, contact…') }}" class="search-input">
                    </div>
                    <select wire:model.live="region" class="select">
                        <option value="">{{ __('All regions') }}</option>
                        @foreach($regions as $option) <option value="{{ $option }}">{{ $option }}</option> @endforeach
                    </select>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="kpi-label w-16">{{ __('Fit') }}</span>
                    @foreach(OutreachIndex::Tiers as $tier => $label)
                        <button type="button" class="{{ $chip(in_array($tier, $tiers, true)) }}" wire:click="toggle('tiers', '{{ $tier }}')">{{ $label }}</button>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="kpi-label w-16">{{ __('Activity') }}</span>
                    @foreach(OutreachIndex::Activity as $status => [$color, $tip])
                        <button type="button" class="{{ $chip(in_array($status, $activity, true)) }}" title="{{ $tip }}" wire:click="toggle('activity', '{{ $status }}')">
                            <span class="size-2 rounded-full" style="background: {{ $color }}"></span>{{ $status }} · {{ $all->filter(fn ($p) => OutreachIndex::status($p) === $status)->count() }}
                        </button>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="kpi-label w-16">{{ __('Builds') }}</span>
                    @foreach($typeColors as $type => $color)
                        <button type="button" class="{{ $chip(in_array($type, $types, true)) }}" wire:click="toggle('types', @js($type))"><span class="size-2" style="background: {{ $color }}"></span>{{ $type }}</button>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="kpi-label w-16">{{ __('Has') }}</span>
                        @foreach(OutreachIndex::Has as $key => [$label, $tip])
                            <button type="button" class="{{ $chip(in_array($key, $has, true)) }}" @if($tip) title="{{ $tip }}" @endif wire:click="toggle('has', '{{ $key }}')">{{ $label }}</button>
                        @endforeach
                    </div>
                    <span class="text-muted">
                        {{ $rows->count() }} {{ __('of') }} {{ $all->count() }}
                        @unless($isDefault) · <button type="button" class="underline" wire:click="resetFilters">{{ __('Reset filters') }}</button> @endunless
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <x-sort-header column="name" :$sort :$direction>{{ __('Developer') }}</x-sort-header>
                            <x-sort-header column="fit" :$sort :$direction>{{ __('Fit') }}</x-sort-header>
                            <x-sort-header column="region" :$sort :$direction>{{ __('Region') }}</x-sort-header>
                            <x-sort-header column="activity" :$sort :$direction>{{ __('Last built') }}</x-sort-header>
                            <th>{{ __('Builds') }}</th>
                            <x-sort-header column="contact" :$sort :$direction>{{ __('Best contact') }}</x-sort-header>
                            <x-sort-header column="projects" :$sort :$direction numeric>{{ __('Projects') }}</x-sort-header>
                            <th>{{ __('Tags') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $prospect)
                            <tr class="is-link" wire:key="or-{{ $prospect->key }}" tabindex="0" onclick="Livewire.navigate('{{ route('outreach.show', $prospect->key) }}')" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); this.click() }">
                                <td>
                                    <div class="flex items-center gap-3">
                                        @if($prospect->img)
                                            <img src="{{ route('outreach.images', $prospect->img) }}" alt="" loading="lazy" class="h-[34px] w-[46px] object-cover">
                                        @else
                                            <span class="flex h-[34px] w-[46px] items-center justify-center bg-rule text-muted"><i class="ph ph-buildings"></i></span>
                                        @endif
                                        <div>
                                            <div class="font-medium">{{ $prospect->name }}</div>
                                            <div class="text-xs text-muted">{{ $prospect->hq }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td><x-outreach.tier :$prospect /></td>
                                <td>{{ $prospect->region }}</td>
                                <td>
                                    <x-outreach.activity-pill :$prospect />
                                    @if($prospect->activity['lastYear'] ?? null)
                                        <div class="text-xs text-muted">{{ $prospect->activity['lastYear'] }} · {{ Str::limit($prospect->activity['project'] ?? '', 34) }}</div>
                                    @endif
                                </td>
                                <td class="!whitespace-normal">
                                    @foreach($prospect->types as $type)
                                        <span class="mr-1 inline-block border-l-2 bg-rule px-1.5 text-xs" style="border-color: {{ $typeColors[$type] ?? '#555b63' }}">{{ $type }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    @if($prospect->person)
                                        <div class="flex items-center gap-1.5">
                                            <b class="font-medium">{{ $prospect->person['name'] }}</b>
                                            @if($prospect->person['linkedin'] ?? null) <i class="ph ph-linkedin-logo text-[#2a6489]"></i> @endif
                                            @if($prospect->person['email'] ?? null) <i class="ph-fill ph-envelope"></i> @elseif($prospect->email) <i class="ph ph-envelope text-muted"></i> @endif
                                            @if($prospect->phone) <i class="ph ph-phone text-muted"></i> @endif
                                        </div>
                                        <div class="text-xs text-muted">{{ $prospect->person['title'] ?? '' }}</div>
                                    @else
                                        <span class="text-muted">{{ $prospect->email || $prospect->phone ? __('Office only') : '—' }}</span>
                                    @endif
                                </td>
                                <td class="is-num">{{ $prospect->projects->count() ?: (count($prospect->recent) ?: '—') }}</td>
                                <td>
                                    @if(in_array('Check first', $prospect->tags, true)) <span class="chip is-warn">{{ __('Possible client') }}</span> @endif
                                    @if(in_array('Warm lead', $prospect->tags, true)) <span class="chip is-on">{{ __('Worked with us') }}</span> @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-10 text-center text-muted">{{ __('Nothing matches those filters.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
