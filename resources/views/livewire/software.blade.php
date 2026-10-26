@php
    use App\Models\Software as SoftwareModel;
    use App\Support\Format;

    $t = $this->totals;
    $rows = $this->rows;
    $canEdit = auth()->user()->can('edit office');
@endphp

<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="page-title">{{ __('Software') }}</h1>
            <p class="page-subtitle">{{ __('What the firm pays for software, as billed.') }}</p>
        </div>
        @if($canEdit)
            <button type="button" class="btn" wire:click="$toggle('adding')"><i class="ph ph-plus"></i>{{ __('Add') }}</button>
        @endif
    </div>

    <div class="kpis grid-cols-1 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi :label="__('A year')" :value="$t['annualCad'] !== null ? Format::compact($t['annualCad']) : $t['byCurrency']->map(fn ($amount, $currency) => Format::compact($amount).' <small>'.$currency.'</small>')->implode('<br>')" dark>
            @if($t['annualCad'] !== null)
                {{ __('all :count live subscriptions, converted to CAD', ['count' => $t['live']]) }}
            @else
                {{ __('no rate for :currencies — totalled per currency', ['currencies' => $t['missing']->implode(', ')]) }}
            @endif
        </x-kpi>
        <x-kpi :label="__('A month')" :value="$t['annualCad'] !== null ? Format::compact($t['annualCad'] / 12) : '—'">
            {{ $t['annualCad'] !== null ? __('in CAD') : __('set the rates below') }}
        </x-kpi>
        <x-kpi :label="__('Subscriptions')" :value="$t['live']">
            {{ __('across :count divisions', ['count' => $t['divisions']]) }}@if($t['retired']) · {{ $t['retired'] }} {{ __('retired') }}@endif
        </x-kpi>
        <x-kpi :label="__('Idle seats')" :value="$t['idle']" :warn="$t['idle'] > 0">
            @if($t['idle'])
                {{ __('of :seats paid for', ['seats' => $t['seats']]) }} ·
                <b>{{ $t['idleCostCad'] !== null ? Format::compact($t['idleCostCad']) : $t['idleCostByCurrency']->map(fn ($cost, $currency) => Format::compact($cost).' '.$currency)->implode(' + ') }}</b>{{ __('/yr recoverable') }}
                @if($t['idleUnpriced']) · {{ $t['idleUnpriced'] }} {{ __('not priceable') }} @endif
            @else
                {{ __('every seat on file is assigned') }}
            @endif
        </x-kpi>
    </div>

    @if($foreign->isNotEmpty())
        <div class="card flex flex-wrap items-center gap-x-6 gap-y-3 py-4">
            <span class="kpi-label">{{ __('Rate to CAD') }}</span>
            @foreach($foreign as $currency)
                <label class="flex items-center gap-2 text-sm">
                    1 {{ $currency }} =
                    <input
                        class="field-input w-24 py-1 text-right"
                        value="{{ $pinned[$currency] ?? '' }}"
                        placeholder="{{ isset($this->rates[$currency]) ? number_format($this->rates[$currency]['rate'], 4) : __('no rate') }}"
                        @disabled(! $canEdit)
                        wire:change="pinRate('{{ $currency }}', $event.target.value)"
                    >
                    CAD
                    @isset($pinned[$currency]) <span class="chip is-warn">{{ __('pinned') }}</span> @endisset
                </label>
            @endforeach
            <span class="text-xs text-muted">
                @isset($published['error'])
                    {{ __('Rate feed unreachable — :error', ['error' => Str::limit($published['error'], 80)]) }}
                @else
                    {{ __('Live from open.er-api.com') }}@isset($published['asOf']), {{ $published['asOf'] }}@endisset. {{ __('Type a rate to pin your own; clear it to go back to the published rate.') }}
                @endisset
            </span>
            @error('fx') <p class="error-note w-full">{{ $message }}</p> @enderror
        </div>
    @endif

    @if($adding)
        <form wire:submit="save" class="card grid gap-4 border-t-2 border-t-gold sm:grid-cols-2 xl:grid-cols-4">
            <div class="xl:col-span-2">
                <label class="field-label">{{ __('Software') }}</label>
                <input wire:model="form.name" class="field-input">
                @error('form.name') <p class="error-note mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="field-label">{{ __('Division') }}</label>
                <input wire:model="form.division" class="field-input" list="sw-divisions">
                <datalist id="sw-divisions">
                    @foreach($divisions as $option) <option value="{{ $option }}"> @endforeach
                </datalist>
            </div>
            <div>
                <label class="field-label">{{ __('Price') }}</label>
                <input type="number" step="0.01" min="0" wire:model="form.price" class="field-input">
            </div>
            <div>
                <label class="field-label">{{ __('Currency') }}</label>
                <select wire:model="form.currency" class="field-input">
                    @foreach(SoftwareModel::Currencies as $currency) <option>{{ $currency }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="field-label">{{ __('Cycle') }}</label>
                <select wire:model="form.cycle" class="field-input">
                    @foreach(SoftwareModel::Cycles as $cycle) <option>{{ $cycle }}</option> @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="field-label">{{ __('Seats used') }}</label>
                    <input type="number" min="0" wire:model="form.seats_used" class="field-input">
                </div>
                <div>
                    <label class="field-label">{{ __('Owned') }}</label>
                    <input type="number" min="0" wire:model="form.seats" class="field-input">
                </div>
            </div>
            <div>
                <label class="field-label">{{ __('Description') }}</label>
                <input wire:model="form.description" class="field-input">
            </div>
            <div class="flex justify-end gap-2 sm:col-span-2 xl:col-span-4">
                <button type="button" class="btn-secondary" wire:click="$set('adding', false)">{{ __('Cancel') }}</button>
                <button type="submit" class="btn">{{ __('Save') }}</button>
            </div>
        </form>
    @endif

    <div class="card p-0">
        <div class="toolbar px-6 pt-5 pb-4">
            <div class="relative w-full max-w-sm">
                <i class="ph ph-magnifying-glass absolute top-1/2 left-3 -translate-y-1/2 text-muted"></i>
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="{{ __('Search software, division, description') }}" class="search-input">
            </div>
            <div class="flex items-center gap-3">
                <select wire:model.live="division" class="select">
                    <option value="">{{ __('All divisions') }}</option>
                    @foreach($divisions as $option) <option value="{{ $option }}">{{ $option }}</option> @endforeach
                </select>
                <span class="text-xs text-muted">{{ $rows->count() }} {{ __('shown') }}</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table min-w-[960px]">
                <thead>
                    <tr>
                        <th>{{ __('Software') }}</th>
                        <th>{{ __('Division') }}</th>
                        <th class="is-num">{{ __('Billed') }}</th>
                        <th>{{ __('Cur.') }}</th>
                        <th>{{ __('Cycle') }}</th>
                        <th>{{ __('Seats') }}</th>
                        <th class="is-num">{{ __('A year') }}</th>
                        <th class="is-num">{{ __('A year, CAD') }}</th>
                        <th>{{ __('Description') }}</th>
                        @if($canEdit) <th></th> @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        @php
                            $cad = $this->annualCad($row);
                        @endphp
                        <tr wire:key="sw-{{ $row->id }}" @class(['is-muted' => ! $row->active])>
                            <td class="font-medium">{{ $row->name }} @unless($row->active) <span class="chip">{{ __('retired') }}</span> @endunless</td>
                            <td>{{ $row->division ?? '—' }}</td>
                            <td class="is-num">{{ $row->price === null ? '—' : number_format((float) $row->price, 2) }}</td>
                            <td>{{ $row->currency }}</td>
                            <td>{{ $row->cycle }}</td>
                            <td>
                                @if($row->seats !== null && $row->seats_used !== null)
                                    {{ $row->seats_used }} {{ __('of') }} {{ $row->seats }}
                                    @if($row->idleSeats())
                                        <span class="chip is-warn" title="{{ __(':count paid for and assigned to nobody', ['count' => $row->idleSeats()]) }}@if($row->idleCost()) — {{ __('about :cost :currency/yr', ['cost' => number_format($row->idleCost()), 'currency' => $row->currency]) }}@endif">+{{ $row->idleSeats() }} {{ __('idle') }}</span>
                                    @endif
                                @elseif($row->seats !== null)
                                    {{ $row->seats }} {{ __('owned') }}
                                @else
                                    <span class="empty">—</span>
                                @endif
                            </td>
                            <td class="is-num font-medium">{{ $row->annual() === null ? '—' : number_format($row->annual(), 2) }}</td>
                            <td class="is-num" @if($cad === null) title="{{ __('No rate set for :currency', ['currency' => $row->currency]) }}" @endif>{{ $cad === null ? '—' : number_format($cad) }}</td>
                            <td class="max-w-[18rem] truncate text-muted" title="{{ $row->description }}">{{ $row->description }}</td>
                            @if($canEdit)
                                <td class="text-right">
                                    <button type="button" class="btn-secondary" wire:click="setActive({{ $row->id }}, {{ $row->active ? 'false' : 'true' }})">{{ $row->active ? __('Retire') : __('Restore') }}</button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    @php
                        $liveShown = $rows->where('active', true);
                        $cadShown = $liveShown->every(fn ($row) => $this->annualCad($row) !== null) ? $liveShown->sum(fn ($row) => $this->annualCad($row)) : null;
                    @endphp
                    <tr>
                        <td colspan="7">{{ __('Shown, live only') }}</td>
                        <td class="is-num">{{ $cadShown === null ? '—' : number_format($cadShown) }}</td>
                        <td colspan="{{ $canEdit ? 2 : 1 }}"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <p class="section-note">
        {{ __('Prices are stored as billed — the amount and the cycle it recurs on — and annualised here. Currency is never assumed: a row without a rate stays out of the CAD total. Idle seats are seats paid for and assigned to nobody; they are only priced for single-product subscriptions.') }}
    </p>
</div>
