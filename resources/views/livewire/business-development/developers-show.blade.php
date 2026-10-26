@php
    use App\Support\Format;

    $d = $this->developer;
    $jobs = collect($d['summaries'])->sortByDesc(fn ($job) => (float) $job['contract']);
@endphp

<div class="space-y-8">
    <div>
        <a href="{{ route('developers.index') }}" class="btn-secondary" wire:navigate><i class="ph ph-arrow-left"></i>{{ __('All developers') }}</a>

        <h1 class="page-title mt-5">{{ $d['name'] }}</h1>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            @if(in_array('billing', $d['sources'], true))
                <span class="chip" style="background: #eef2f5; color: #456079" title="{{ __('Named on at least one billing job') }}">{{ __('Billing') }}</span>
            @endif
            @if(in_array('added', $d['sources'], true))
                <span class="chip is-warn" title="{{ __('Added on this page') }}">{{ __('Added') }}</span>
            @endif
            <span class="text-sm text-muted">{{ $d['jobCount'] ? trans_choice(':count job signed|:count jobs signed', $d['jobCount']) : __('No signed jobs yet') }}</span>
        </div>

        @if($d['aka'])
            <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-ink-2">
                {{ __('Also billed and logged as') }}
                @foreach($d['aka'] as $aka)
                    <span class="chip">
                        {{ $aka['name'] }}
                        @if($canEdit)
                            <button type="button" class="hover:text-danger" title="{{ __('Separate this back into its own developer') }}" wire:click="separate('{{ $aka['key'] }}')"><i class="ph ph-x"></i></button>
                        @endif
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="kpis grid-cols-1 md:grid-cols-3">
        @if($showMoney)
            <x-kpi :label="__('Largest contract')" :value="Format::compact($d['contractMax'])">{{ __('biggest single job with them') }}</x-kpi>
            <x-kpi :label="__('Total contract')" :value="Format::compact($d['contractTotal'])">
                {{ Format::compact($d['billed']) }} {{ __('billed') }}@if($d['outstanding']) · {{ Format::compact($d['outstanding']) }} {{ __('outstanding') }}@endif
            </x-kpi>
        @endif
        <x-kpi :label="__('Hours logged')" :value="Format::number($d['hours'])">{{ __('across all logged hours') }}</x-kpi>
    </div>

    <div class="card grid gap-4 sm:grid-cols-3">
        <div>
            <label class="field-label" for="dev-city">{{ __('City') }}</label>
            <input id="dev-city" class="field-input" value="{{ $d['city'] }}" placeholder="—" @disabled(! $canEdit) wire:change="saveField('city', $event.target.value)">
        </div>
        <div class="sm:col-span-2">
            <label class="field-label" for="dev-note">{{ __('Note') }}</label>
            <input id="dev-note" class="field-input" value="{{ $d['note'] }}" placeholder="{{ __('Anything worth remembering') }}" @disabled(! $canEdit) wire:change="saveField('note', $event.target.value)">
        </div>
        @error('name') <p class="error-note sm:col-span-3">{{ $message }}</p> @enderror
    </div>

    <div class="card p-0">
        <div class="px-6 pt-5 pb-3">
            <h2 class="section-title">{{ __('Jobs') }}</h2>
            <p class="section-note">{{ __('Signed work, from the billing database. Click through for the contract and the month-by-month billing.') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Job') }}</th>
                        <th>{{ __('Project') }}</th>
                        <th>{{ __('Division') }}</th>
                        <th>{{ __('Lead') }}</th>
                        @if($showMoney)
                            <th class="is-num">{{ __('Contract') }}</th>
                            <th class="is-num">{{ __('Billed') }}</th>
                            <th class="is-num">{{ __('Outstanding') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($jobs as $job)
                        <tr wire:key="dj-{{ $job['jobNumber'] }}">
                            <td class="text-muted">{{ $job['jobNumber'] }}</td>
                            <td>
                                @if($showMoney)
                                    <a href="{{ route('projects.show', $job['jobNumber']) }}" class="font-medium hover:text-navy" wire:navigate>{{ $job['jobName'] }}</a>
                                @else
                                    {{ $job['jobName'] }}
                                @endif
                                @unless($job['active']) <span class="chip ml-1">{{ __('inactive') }}</span> @endunless
                            </td>
                            <td>{{ $job['division'] ?? '—' }}</td>
                            <td>{{ $job['lead'] ?? '—' }}</td>
                            @if($showMoney)
                                <td class="is-num">{{ Format::money($job['contract']) }}</td>
                                <td class="is-num">{{ Format::money($job['billed']) }}</td>
                                <td class="is-num">{{ Format::money($job['outstanding']) }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-10 text-center text-muted">{{ __('No signed jobs with :name yet.', ['name' => $d['name']]) }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
