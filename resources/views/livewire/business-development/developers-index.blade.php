@php
    use App\Support\Format;

    $jobs = fn (array $row) => $row['jobCount'] ? trans_choice(':count job|:count jobs', $row['jobCount']) : __('nothing yet');
    $lastSeen = fn (?string $date) => $date ? \Carbon\CarbonImmutable::parse($date)->format('j M y') : '—';
@endphp

<div class="space-y-8">
    <div>
        <h1 class="page-title">{{ __('Developers') }}</h1>
        <p class="page-subtitle">{{ __('Who the firm works for, and how much of it.') }}</p>
    </div>

    <div class="kpis grid-cols-1 md:grid-cols-3">
        <x-kpi :label="__('Developers')" :value="$this->developers->count()" dark>
            {{ __(':count with a signed job — the rest were added by hand', ['count' => $withJobs]) }}
        </x-kpi>
        @if($showMoney)
            <x-kpi :label="__('Under contract')" :value="Format::compact($underContract)">
                {{ __('every fee across every job, all time') }}
            </x-kpi>
            <x-kpi :label="__('Largest single contract')" :value="Format::compact($largest['contractMax'] ?? null)">
                {{ $largest['name'] ?? '—' }}
            </x-kpi>
        @endif
    </div>

    @if($duplicates)
        <div class="notice">
            <p class="font-medium"><i class="ph ph-warning"></i> {{ trans_choice(':count name may be the same firm twice|:count names may be the same firm twice', count($duplicates)) }}</p>
            <p class="mt-1">{{ __('The billing sheet and the Developers list are typed separately, so a developer can appear under two spellings and its work splits across both rows. Nothing is merged automatically — only you can tell a variant from a different firm. Pick the name to keep and both sides fold into it; neither source is retyped, and the profile can separate them again.') }}</p>
            <ul class="mt-2 space-y-1">
                @foreach($duplicates as [$a, $b])
                    <li>
                        <b>{{ $a['name'] }}</b> ({{ $jobs($a) }}) {{ __('and') }} <b>{{ $b['name'] }}</b> ({{ $jobs($b) }})
                        @if($canEdit)
                            · {{ __('one firm, keep') }}
                            <button type="button" class="btn-secondary py-0.5" wire:click="merge('{{ $a['key'] }}', '{{ $b['key'] }}')" wire:confirm="{{ __('Treat ":drop" as ":keep" from now on? Nothing is retyped in the billing sheet — both keep their own spelling. You can separate them again on the developer’s page.', ['drop' => $b['name'], 'keep' => $a['name']]) }}">{{ $a['name'] }}</button>
                            <button type="button" class="btn-secondary py-0.5" wire:click="merge('{{ $b['key'] }}', '{{ $a['key'] }}')" wire:confirm="{{ __('Treat ":drop" as ":keep" from now on? Nothing is retyped in the billing sheet — both keep their own spelling. You can separate them again on the developer’s page.', ['drop' => $a['name'], 'keep' => $b['name']]) }}">{{ $b['name'] }}</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card p-0">
        <div class="px-6 pt-5">
            <h2 class="section-title">{{ __('Developers & clients') }}</h2>
            <p class="section-note"><b>{{ __('Jobs') }}</b> {{ __('is signed work in the billing database. Click a developer for the work behind the numbers.') }}</p>
        </div>
        <div class="toolbar px-6 pt-4 pb-4">
            <div class="relative w-full max-w-sm">
                <i class="ph ph-magnifying-glass absolute top-1/2 left-3 -translate-y-1/2 text-muted"></i>
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="{{ __('Search developer or city…') }}" class="search-input">
            </div>
            @if($canEdit)
                @if($adding)
                    <form wire:submit="add" class="flex items-center gap-2">
                        <input wire:model="newName" class="field-input py-1.5" placeholder="{{ __('Developer name') }}" autofocus>
                        <button type="submit" class="btn">{{ __('Add') }}</button>
                        <button type="button" class="btn-secondary" wire:click="$set('adding', false)">{{ __('Cancel') }}</button>
                    </form>
                @else
                    <button type="button" class="btn-secondary" wire:click="$set('adding', true)"><i class="ph ph-plus"></i>{{ __('Add a developer') }}</button>
                @endif
            @endif
            @error('name') <p class="error-note w-full">{{ $message }}</p> @enderror
            @error('merge') <p class="error-note w-full">{{ $message }}</p> @enderror
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Developer') }}</th>
                        <th class="is-num" title="{{ __('Signed jobs in the billing database.') }}">{{ __('Jobs') }}</th>
                        @if($showMoney)
                            <th class="is-num" title="{{ __('The biggest single contract ever signed with them.') }}">{{ __('Largest contract') }}</th>
                            <th class="is-num" title="{{ __('Every fee across every job with them.') }}">{{ __('Total contract') }}</th>
                            <th class="is-num" title="{{ __('Everything invoiced to them, all time.') }}">{{ __('Billed') }}</th>
                        @endif
                        <th class="is-num" title="{{ __('Most recent payment or hours entry.') }}">{{ __('Last seen') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr class="is-link" wire:key="dev-{{ $row['key'] }}" tabindex="0" onclick="Livewire.navigate('{{ route('developers.show', $row['key']) }}')" onkeydown="if (event.key === 'Enter') this.click()">
                            <td>
                                <span class="font-medium">{{ $row['name'] }}</span>
                                @if($row['city']) <div class="text-xs text-muted">{{ $row['city'] }}</div> @endif
                            </td>
                            <td class="is-num">
                                {{ $row['jobCount'] ?: '—' }}
                                @if($row['liveCount']) <span class="ml-1 text-xs text-positive">{{ $row['liveCount'] }} {{ __('live') }}</span> @endif
                            </td>
                            @if($showMoney)
                                <td class="is-num">{{ Format::compact($row['contractMax']) }}</td>
                                <td class="is-num">{{ Format::compact($row['contractTotal']) }}</td>
                                <td class="is-num">{{ Format::compact($row['billed']) }}</td>
                            @endif
                            <td class="is-num text-muted">{{ $lastSeen($row['lastActivity']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-10 text-center text-muted">{{ __('Nothing matches that search.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
