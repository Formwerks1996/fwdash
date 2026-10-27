@php
    use App\Support\Format;

    $t = $this->totals;
    $rows = $this->rows;
    $window = $this->window;
@endphp

<div class="space-y-8">
    <div>
        <h1 class="page-title">{{ __('Staff Costs') }}</h1>
        <p class="page-subtitle">
            {{ __('What each person costs the firm an hour. Their hours at this rate are Month End\'s "Spent" line.') }}
        </p>
    </div>

    <div class="kpis grid-cols-1 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi :label="__('Spent, last 12 months')" :value="Format::compact($t['spent'])" dark>
            {{ __(':from to :to', ['from' => Format::month(Arr::first($window)), 'to' => Format::month(Arr::last($window))]) }}
        </x-kpi>
        <x-kpi :label="__('A month')" :value="Format::compact($t['monthly'])">
            {{ __('on average over those 12 months') }}
        </x-kpi>
        <x-kpi :label="__('Average hourly cost')" :value="$t['average'] ? Format::money($t['average'], 2) : '—'">
            {{ __('weighted by hours, :count people costed', ['count' => $t['costed']]) }}
        </x-kpi>
        <x-kpi :label="__('Hours with no rate')" :value="Format::number($t['uncostedHours'])" :warn="$t['uncostedHours'] > 0">
            @if($t['uncostedPeople'])
                {{ trans_choice(':count person has no rate, so their time is not in Spent|:count people have no rate, so their time is not in Spent', $t['uncostedPeople']) }}
            @else
                {{ __('everyone who logged time has a rate') }}
            @endif
        </x-kpi>
    </div>

    <div class="card p-0">
        <div class="toolbar px-6 pt-5 pb-4">
            <div class="relative w-full max-w-sm">
                <i class="ph ph-magnifying-glass absolute top-1/2 left-3 -translate-y-1/2 text-muted"></i>
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="{{ __('Search people') }}" class="search-input">
            </div>
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 text-xs text-ink-2" title="{{ __('Also list people with no hours in the last 12 months and no dashboard account') }}">
                    <input type="checkbox" wire:model.live="everyone" class="accent-navy"> {{ __('Show everyone') }}
                </label>
                <span class="text-xs text-muted">{{ $rows->count() }} {{ __('shown') }}</span>
            </div>
        </div>

        @if($canEdit)
            <p class="section-note px-6 pb-3">{{ __('Type an hourly cost and press Enter or click away to save. A change applies from :month on; earlier months keep the rate they had.', ['month' => Format::month(now()->format('Y-m'))]) }}</p>
        @endif
        @error('rate') <p class="error-note mx-6 mb-3">{{ $message }}</p> @enderror

        <div class="overflow-x-auto">
            <table class="data-table min-w-[760px]">
                <thead>
                    <tr>
                        <th>{{ __('Person') }}</th>
                        <th class="is-num">{{ __('Cost / hr') }}</th>
                        <th>{{ __('Since') }}</th>
                        <th class="is-num">{{ __('Hours, 12 mo') }}</th>
                        <th class="is-num">{{ __('Cost, 12 mo') }}</th>
                        <th>{{ __('Last logged') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $person)
                        <tr wire:key="cost-{{ $person['email'] }}" @class(['is-muted' => ! $person['active']])>
                            <td>
                                <b class="font-medium">{{ $person['name'] }}</b>
                                <div class="text-xs text-muted">{{ $person['email'] }}</div>
                            </td>
                            <td class="is-num">
                                <input
                                    type="text"
                                    inputmode="decimal"
                                    class="cell-input"
                                    placeholder="{{ $person['hours'] > 0 ? __('no rate') : '—' }}"
                                    aria-label="{{ __('Hourly cost for :name', ['name' => $person['name']]) }}"
                                    value="{{ $person['rate'] !== null ? number_format($person['rate'], 2) : '' }}"
                                    @disabled(! $canEdit)
                                    x-on:keydown.enter="$event.target.blur()"
                                    wire:change="setRate(@js($person['email']), $event.target.value)"
                                >
                                @if($person['rate'] === null && $person['hours'] > 0)
                                    <span class="chip is-warn">{{ __('not costed') }}</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $person['since'] ? Format::month($person['since']->format('Y-m')) : '—' }}</td>
                            <td class="is-num">{{ $person['hours'] ? Format::number($person['hours']) : '—' }}</td>
                            <td class="is-num font-medium">{{ $person['cost'] ? Format::compact($person['cost']) : '—' }}</td>
                            <td class="text-muted">{{ $person['lastMonth'] ? Format::month($person['lastMonth']) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">{{ __('Shown') }}</td>
                        <td class="is-num">{{ Format::number($rows->sum('hours')) }}</td>
                        <td class="is-num">{{ Format::compact($rows->sum('cost')) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <p class="section-note">
        {{ __('Hours come from Mosaic through :month and from the dashboard\'s timesheets after that. Mosaic keeps one rate per person, so a person\'s first rate also prices the months before it.', ['month' => Format::month($mosaicThrough)]) }}
    </p>
</div>
