@php
    use App\Enums\Phase;
    use App\Models\ProjectRole;
    use App\Support\Format;
    use App\Support\RateBand;

    $s = $this->summary;
    $project = $this->project;
    $band = RateBand::classify($s['ratePerHour'], $s['hoursTotal']);
    $feeTotal = collect($this->fees)->sum(fn ($fee) => (float) $fee['fee']);
    $billed = (float) $s['billed'];
    $scale = max($feeTotal, $billed) ?: 1;
    $amended = collect($this->fees)->where('billedAtOtherFee', '>', 0)->count();
@endphp

<div class="space-y-8">
    <div>
        <a href="{{ route('projects.index') }}" class="btn-secondary" wire:navigate><i class="ph ph-arrow-left"></i>{{ __('Projects') }}</a>

        <div class="mt-5 flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="text-xs tracking-[0.1em] text-muted uppercase">{{ $s['jobNumber'] }}</div>
                <h1 class="page-title mt-1">{{ $s['jobName'] }}</h1>
                <div class="mt-3 flex flex-wrap gap-2">
                    @if($s['developer']) <span class="chip">{{ $s['developer'] }}</span> @endif
                    @if($s['division']) <span class="chip">{{ $s['division'] }}</span> @endif
                    @forelse($roles[ProjectRole::Lead] as $name)
                        <span class="chip">{{ __('Lead') }} <a href="{{ route('projects.index', ['view' => 'cards', 'person' => $name]) }}" class="hover:text-navy" wire:navigate>{{ $name }}</a></span>
                    @empty
                        @if($s['lead']) <span class="chip">{{ __('Lead') }} {{ $s['lead'] }}</span> @endif
                    @endforelse
                    @foreach($roles[ProjectRole::ProjectManager] as $name)
                        <span class="chip">{{ __('PM') }} <a href="{{ route('projects.index', ['view' => 'cards', 'person' => $name]) }}" class="hover:text-navy" wire:navigate>{{ $name }}</a></span>
                    @endforeach
                    <span @class(['chip', 'is-on' => $s['active']])>{{ $s['active'] ? __('Active') : __('Inactive in billing') }}</span>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-ink-2">
                    <span class="text-muted">{{ __('Assigned to') }}</span>
                    @forelse($assigned as $name)
                        <span class="chip">
                            <a href="{{ route('projects.index', ['view' => 'cards', 'person' => $name]) }}" class="hover:text-navy" wire:navigate>{{ $name }}</a>
                            @if($positions[$name] ?? null) <span class="text-muted">· {{ $positions[$name] }}</span> @endif
                            @if($canAssign)
                                <button type="button" class="hover:text-danger" wire:click="release(@js($name))" title="{{ __('Take :name off this project', ['name' => $name]) }}"><i class="ph ph-x"></i></button>
                            @endif
                        </span>
                    @empty
                        <span class="text-muted">—</span>
                    @endforelse
                    @if($canAssign && $team->diff($assigned)->isNotEmpty())
                        <flux:dropdown>
                            <button type="button" class="btn-secondary py-0.5"><i class="ph ph-plus"></i>{{ __('Assign') }}</button>
                            <flux:menu class="max-h-72 overflow-y-auto">
                                @foreach($team->diff($assigned) as $name)
                                    <flux:menu.item wire:click="assign(@js($name))">{{ $name }}</flux:menu.item>
                                @endforeach
                            </flux:menu>
                        </flux:dropdown>
                    @endif
                    @error('assignment') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
            </div>
            @if($canEdit && ! $editingDetails)
                <button type="button" class="btn-secondary" wire:click="editDetails"><i class="ph ph-pencil-simple"></i>{{ __('Details') }}</button>
            @endif
        </div>

        @if($editingDetails)
            <form wire:submit="saveDetails" class="card mt-6 grid gap-4 border-t-2 border-t-gold sm:grid-cols-2 xl:grid-cols-4">
                <div class="xl:col-span-2">
                    <label class="field-label">{{ __('Project name') }}</label>
                    <input wire:model="details.job_name" class="field-input">
                    @error('details.job_name') <p class="error-note mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="field-label">{{ __('Developer') }}</label>
                    <input wire:model="details.developer" class="field-input">
                </div>
                <div>
                    <label class="field-label">{{ __('Division') }}</label>
                    <input wire:model="details.division" class="field-input">
                </div>
                <div>
                    <label class="field-label">{{ __('Project lead') }}</label>
                    <input wire:model="details.project_lead" class="field-input">
                </div>
                <div>
                    <label class="field-label">{{ __('CA per month') }}</label>
                    <input type="number" step="0.01" min="0" wire:model="details.ca_per_month" class="field-input">
                </div>
                <div>
                    <label class="field-label">{{ __('IFC date') }}</label>
                    <input type="date" wire:model="details.ifc_date" class="field-input">
                </div>
                <div class="flex items-end justify-between gap-3">
                    <label class="flex items-center gap-2 text-sm text-ink-2">
                        <input type="checkbox" wire:model="details.active" class="accent-navy"> {{ __('Active — in the month-end worklist') }}
                    </label>
                </div>
                <div class="flex justify-end gap-2 sm:col-span-2 xl:col-span-4">
                    <button type="button" class="btn-secondary" wire:click="$set('editingDetails', false)">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn">{{ __('Save') }}</button>
                </div>
            </form>
        @endif
    </div>

    <div class="kpis grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        <x-kpi :label="__('Contract')" :value="Format::money($s['contract'])">
            {{ (float) $s['balance'] ? Format::money($s['balance']).' '.__('still to bill') : __('fully billed') }}
        </x-kpi>
        <x-kpi :label="__('Billed')" :value="Format::money($s['billed'])">
            {{ $s['billedPct'] !== null ? Format::pct($s['billedPct'], 1).' '.__('of contract') : __('no contract set') }}
        </x-kpi>
        <x-kpi :label="__('Outstanding')" :value="Format::money($s['outstanding'])">
            {{ $s['lastPaymentDate'] ? __('last payment :date', ['date' => Format::date($s['lastPaymentDate'])]) : __('no payments recorded') }}
        </x-kpi>
        <x-kpi :label="__('Realized rate')" :value="$s['ratePerHour'] ? '$'.number_format($s['ratePerHour']).'<small>/h</small>' : '—'" :band="$band">
            {{ $band['reason'] ?? __('earned per hour worked · :hours h all time', ['hours' => Format::number($s['hoursTotal'])]) }}
        </x-kpi>
        <x-kpi :label="__('Hours')" :value="Format::number($s['hoursTotal'])">
            {{ Format::number($s['monthlyHoursTotal']) }} {{ __('logged by month') }}@if($s['openingHours']) · {{ Format::number($s['openingHours']) }} {{ __('before the system') }}@endif
        </x-kpi>
        <x-kpi :label="__('Consultant fees')" :value="Format::money($s['consultantTotal'])">
            {{ __('deducted before the realized rate') }}
        </x-kpi>
    </div>

    @error('fee') <p class="error-note">{{ $message }}</p> @enderror
    @error('entry') <p class="error-note">{{ $message }}</p> @enderror
    @error('payment') <p class="error-note">{{ $message }}</p> @enderror

    <section class="card p-0">
        <div class="px-6 pt-5">
            <h2 class="section-title">{{ __('Contract') }}</h2>
            <div class="mt-4 flex h-2.5 w-full bg-[#e9ebec]">
                <span class="bg-positive" style="width: {{ min($billed, $feeTotal) / $scale * 100 }}%"></span>
                @if($billed > $feeTotal)
                    <span class="bg-over" style="width: {{ ($billed - $feeTotal) / $scale * 100 }}%"></span>
                @endif
            </div>
            <p class="mt-2 text-xs text-muted">
                <b class="font-medium text-ink-2">{{ Format::money($billed) }}</b> {{ __('billed') }}
                · {{ $feeTotal ? Format::pct($billed / $feeTotal) : '—' }} {{ __('of') }} {{ Format::money($feeTotal) }}
                · @if($billed > $feeTotal)<span class="text-over">{{ Format::money($billed - $feeTotal) }} {{ __('over contract') }}</span>@else{{ Format::money($feeTotal - $billed) }} {{ __('left to bill') }}@endif
                @if((float) $s['hourlyBilled']) · {{ Format::money($s['phaseBilled']) }} {{ __('phases') }} + {{ Format::money($s['hourlyBilled']) }} {{ __('hourly') }} @endif
            </p>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Phase') }}</th>
                        <th class="is-num">{{ __('Fee') }}</th>
                        <th class="is-num">{{ __('Billed') }}</th>
                        <th class="is-num" title="{{ __('Hours logged in Mosaic, at each person\'s charge-out rate') }}">{{ __('Spent') }}</th>
                        <th>{{ __('Complete') }}</th>
                        <th class="is-num">{{ __('Left') }}</th>
                        <th>{{ __('Note') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->fees as $fee)
                        <tr wire:key="fee-{{ $fee['phase'] }}" @class(['is-muted' => ! (float) $fee['fee'] && ! (float) $fee['billed']])>
                            <td class="font-medium">{{ $fee['phase'] }}</td>
                            <td class="is-num">
                                @if($canEdit)
                                    <input
                                        class="cell-input"
                                        value="{{ $fee['fee'] === null ? '' : number_format((float) $fee['fee'], 0, '.', ',') }}"
                                        placeholder="—"
                                        wire:change="saveFee('{{ $fee['phase'] }}', $event.target.value)"
                                        aria-label="{{ __(':phase fee', ['phase' => $fee['phase']]) }}"
                                    >
                                @else
                                    {{ Format::money($fee['fee']) }}
                                @endif
                            </td>
                            <td class="is-num">{{ (float) $fee['billed'] ? Format::money($fee['billed']) : '—' }}</td>
                            <td class="is-num">
                                @php $spent = $spentByPhase[$fee['phase']] ?? null; @endphp
                                @if($spent)
                                    <span @class(['text-over' => (float) $fee['fee'] && $spent['spent'] > (float) $fee['fee']]) title="{{ collect($spent['names'])->pluck('name')->implode(', ') }}">{{ Format::money($spent['spent']) }}</span>
                                    <small class="block text-muted">{{ Format::number($spent['hours'], 1) }} h</small>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if($fee['pctComplete'] !== null)
                                    <span class="inline-flex items-center gap-2">
                                        <span class="bar-track w-20"><span @class(['bar-fill', 'is-over' => $fee['pctComplete'] > 1]) style="width: {{ min(100, $fee['pctComplete'] * 100) }}%"></span></span>
                                        <span @class(['text-xs', 'text-over' => $fee['pctComplete'] > 1])>{{ Format::pct($fee['pctComplete']) }}</span>
                                    </span>
                                @endif
                            </td>
                            <td class="is-num">{{ (float) $fee['fee'] ? Format::money($fee['balance']) : '—' }}</td>
                            <td class="text-xs text-muted">
                                @if($fee['billedAtOtherFee'])
                                    <span class="text-warning" title="{{ trans_choice(':count earlier month was billed against a different fee|:count earlier months were billed against a different fee', $fee['billedAtOtherFee']) }}">
                                        <i class="ph ph-warning"></i> {{ __('fee amended') }}
                                    </span>
                                @endif
                                {{ $fee['note'] }}
                            </td>
                        </tr>
                    @endforeach
                    @if($otherPhases->isNotEmpty())
                        <tr class="is-muted">
                            <td colspan="7" class="pt-4 text-xs tracking-[0.12em] uppercase">{{ __('Other phases in Mosaic: no fee here, not counted against the contract') }}</td>
                        </tr>
                        @foreach($otherPhases as $other)
                            <tr wire:key="other-{{ $loop->index }}">
                                <td class="font-medium">{{ $other['name'] }}</td>
                                <td class="is-num text-muted">{{ __('no fee') }}</td>
                                <td class="is-num text-muted">—</td>
                                <td class="is-num">
                                    {{ Format::money($other['spent']) }}
                                    <small class="block text-muted">{{ Format::number($other['hours'], 1) }} h</small>
                                </td>
                                <td colspan="3"></td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
                <tfoot>
                    @if((float) $s['hourlyBilled'])
                        <tr>
                            <td>{{ __('Hourly') }}</td>
                            <td class="is-num text-muted">{{ __('no fee') }}</td>
                            <td class="is-num">{{ Format::money($s['hourlyBilled']) }}</td>
                            <td></td>
                            <td colspan="3" class="text-xs font-normal text-muted">{{ __('Time and materials — uses up the contract balance') }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td>{{ __('Contract') }}</td>
                        <td class="is-num">{{ Format::money($feeTotal) }}</td>
                        <td class="is-num">{{ Format::money($billed) }}</td>
                        <td class="is-num">
                            @php $feeSpent = collect($spentByPhase)->only(collect($this->fees)->filter(fn ($fee) => (float) $fee['fee'] > 0)->pluck('phase')->all())->sum('spent'); @endphp
                            @if($feeSpent)
                                <span @class(['text-over' => $feeSpent > $feeTotal])>{{ Format::money($feeSpent) }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td></td>
                        <td class="is-num">{{ Format::money(max(0, $feeTotal - $billed)) }}</td>
                        <td class="text-xs font-normal text-muted">{{ $amended ? trans_choice(':count phase amended since billing|:count phases amended since billing', $amended) : '' }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    @if($phases->isNotEmpty() || $workPlans->isNotEmpty())
        <section class="card p-0">
            <div class="px-6 pt-5">
                <h2 class="section-title">{{ __('Phases & hours') }}</h2>
                <p class="section-note">{{ __('How the job was set up: its phases as the contract names them, the hours budgeted and who is booked on it.') }}</p>
            </div>

            @if($phases->isNotEmpty())
                <div class="mt-4 overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Phase') }}</th>
                                <th>{{ __('Bills under') }}</th>
                                <th>{{ __('Dates') }}</th>
                                <th class="is-num">{{ __('Fee') }}</th>
                                <th class="is-num">{{ __('Hours') }}</th>
                                <th>{{ __('By person') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($phases as $phase)
                                <tr wire:key="plan-phase-{{ $phase->id }}">
                                    <td class="font-medium">{{ $phase->name }}</td>
                                    <td class="text-ink-2">{{ $phase->isHourly() ? __('Hourly') : $phase->billing_code }}</td>
                                    <td class="text-xs text-ink-2">
                                        @if($phase->start_date || $phase->end_date)
                                            {{ $phase->start_date ? Format::date($phase->start_date->format('Y-m-d')) : '…' }} – {{ $phase->end_date ? Format::date($phase->end_date->format('Y-m-d')) : '…' }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="is-num">{{ $phase->isHourly() ? '—' : Format::money($phase->fee) }}</td>
                                    <td class="is-num">{{ $phase->estimated_hours !== null ? Format::number($phase->estimated_hours, 1) : '—' }}</td>
                                    <td class="text-xs text-ink-2">
                                        {{ $phase->hourBudgets->map(fn ($budget) => $budget->member_name.' '.Format::number($budget->hours, 1))->implode(' · ') ?: '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="font-medium">{{ __('Total') }}</td>
                                <td class="is-num font-medium">{{ Format::money($phases->reject->isHourly()->sum(fn ($phase) => (float) $phase->fee)) }}</td>
                                <td class="is-num font-medium">{{ Format::number($phases->sum(fn ($phase) => (float) $phase->estimated_hours), 1) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif

            @if($workPlans->isNotEmpty())
                <div class="mt-2 overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Work plan') }}</th>
                                <th>{{ __('Phase') }}</th>
                                <th>{{ __('Dates') }}</th>
                                <th class="is-num">{{ __('h/day') }}</th>
                                <th class="is-num">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($workPlans as $plan)
                                <tr wire:key="work-plan-{{ $plan->id }}">
                                    <td class="font-medium">{{ $plan->member_name }}</td>
                                    <td class="text-ink-2">{{ $plan->phase?->name ?? __('Whole job') }}</td>
                                    <td class="text-xs text-ink-2">{{ Format::date($plan->start_date->format('Y-m-d')) }} – {{ Format::date($plan->end_date->format('Y-m-d')) }}</td>
                                    <td class="is-num">{{ Format::number($plan->daily_hours, 1) }}</td>
                                    <td class="is-num">{{ Format::number($plan->totalHours(), 1) }} h</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <div class="pb-2"></div>
        </section>
    @endif

    <section class="card p-0">
        <div class="px-6 pt-5">
            <h2 class="section-title">{{ __('Month by month') }}</h2>
            <p class="section-note">{{ __('Click a month for its phase draws, hourly lines and payments.') }}</p>
            @if(count($this->months) > 1)
                <x-chart kind="cumulative" :data="$chart" height="14rem" class="mt-4" />
            @endif
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="data-table min-w-[940px]">
                <thead>
                    <tr>
                        <th>{{ __('Month') }}</th>
                        <th class="is-num">{{ __('Phase draws') }}</th>
                        <th class="is-num">{{ __('Hourly billing') }}</th>
                        <th class="is-num">{{ __('Billed') }}</th>
                        <th class="is-num">{{ __('Hours') }}</th>
                        <th class="is-num">{{ __('$/hr') }}</th>
                        <th class="is-num">{{ __('Paid') }}</th>
                        <th class="is-num">{{ __('To date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->months as $month)
                        @php
                            $isOpen = $openMonth === $month['period'];
                            $closed = $month['status'] === 'closed';
                        @endphp
                        <tr class="is-link" wire:key="m-{{ $month['period'] }}" wire:click="toggleMonth('{{ $month['period'] }}')">
                            <td>
                                <i @class(['ph text-muted', 'ph-caret-down' => $isOpen, 'ph-caret-right' => ! $isOpen])></i>
                                {{ Format::shortMonth($month['period']) }}
                                @if($closed) <span class="chip ml-1"><i class="ph ph-lock-simple"></i>{{ __('closed') }}</span> @endif
                            </td>
                            <td class="is-num">{!! (float) $month['phaseBilled'] ? e(Format::compact($month['phaseBilled'])) : '<span class="empty">—</span>' !!}</td>
                            <td class="is-num">{!! (float) $month['hourlyBilled'] ? e(Format::compact($month['hourlyBilled'])) : '<span class="empty">—</span>' !!}</td>
                            <td class="is-num font-medium">{!! (float) $month['billed'] ? e(Format::compact($month['billed'])) : '<span class="empty">—</span>' !!}</td>
                            <td class="is-num">{{ $month['hours'] ? Format::number($month['hours'], 1) : '—' }}</td>
                            <td class="is-num">{{ $month['ratePerHour'] ? '$'.number_format($month['ratePerHour']) : '—' }}</td>
                            <td class="is-num">{!! (float) $month['paymentsTotal'] ? e(Format::compact($month['paymentsTotal'])) : '<span class="empty">—</span>' !!}</td>
                            <td class="is-num">{{ Format::compact($month['billedToDate']) }}</td>
                        </tr>

                        @if($isOpen)
                            <tr wire:key="m-{{ $month['period'] }}-detail">
                                <td colspan="8" class="bg-[#fafbfb] !whitespace-normal">
                                    <div class="grid gap-6 py-2 lg:grid-cols-2">
                                        <div>
                                            <h3 class="kpi-label mb-2">{{ __('Phase draws') }}</h3>
                                            @if($closed)
                                                <p class="notice mb-2">{{ __('This month is closed, so its draws cannot be changed.') }}</p>
                                            @endif
                                            <table class="data-table bg-white">
                                                <thead>
                                                    <tr>
                                                        <th>{{ __('Phase') }}</th>
                                                        <th class="is-num">{{ __('Fee') }}</th>
                                                        <th class="is-num">{{ __('% this month') }}</th>
                                                        <th class="is-num">{{ __('or $') }}</th>
                                                        <th class="is-num">{{ __('Bills') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($this->fees as $fee)
                                                        @php
                                                            $entry = collect($month['phases'])->firstWhere('phase', $fee['phase']);
                                                        @endphp
                                                        @continue(! (float) $fee['fee'] && ! $entry)
                                                        <tr wire:key="e-{{ $month['period'] }}-{{ $fee['phase'] }}">
                                                            <td>{{ $fee['phase'] }}</td>
                                                            <td class="is-num">{{ Format::money($entry['feeAtEntry'] ?? $fee['fee']) }}</td>
                                                            <td class="is-num">
                                                                <input
                                                                    class="cell-input w-20"
                                                                    value="{{ isset($entry['pct']) ? rtrim(rtrim(number_format((float) $entry['pct'] * 100, 2), '0'), '.') : '' }}"
                                                                    placeholder="—"
                                                                    @disabled($closed || ! $canEdit)
                                                                    wire:click.stop
                                                                    wire:change="saveEntry('{{ $month['period'] }}', '{{ $fee['phase'] }}', 'pct', $event.target.value)"
                                                                >
                                                            </td>
                                                            <td class="is-num">
                                                                <input
                                                                    class="cell-input w-24"
                                                                    value="{{ isset($entry['amount']) ? number_format((float) $entry['amount'], 2) : '' }}"
                                                                    placeholder="—"
                                                                    @disabled($closed || ! $canEdit)
                                                                    wire:click.stop
                                                                    wire:change="saveEntry('{{ $month['period'] }}', '{{ $fee['phase'] }}', 'amount', $event.target.value)"
                                                                >
                                                            </td>
                                                            <td class="is-num">{{ $entry ? Format::money($entry['billed'], 2) : '—' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="space-y-6">
                                            <div>
                                                <h3 class="kpi-label mb-2">{{ __('Hourly billing') }}</h3>
                                                @if($month['hourlyOverride'] !== null)
                                                    <p class="mb-2 text-xs text-muted">{{ __('Overridden to :amount for the month.', ['amount' => Format::money($month['hourlyOverride'], 2)]) }}</p>
                                                @endif
                                                @if($month['hourLines'])
                                                    <table class="data-table bg-white">
                                                        <thead>
                                                            <tr>
                                                                <th>{{ __('Who') }}</th>
                                                                <th class="is-num">{{ __('Hours') }}</th>
                                                                <th class="is-num">{{ __('Rate') }}</th>
                                                                <th class="is-num">{{ __('Total') }}</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($month['hourLines'] as $line)
                                                                <tr>
                                                                    <td>{{ $line['staff'] }}</td>
                                                                    <td class="is-num">{{ Format::number($line['hours'], 2) }}</td>
                                                                    <td class="is-num">{{ Format::money($line['rate']) }}</td>
                                                                    <td class="is-num">{{ Format::money($line['total'], 2) }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                @else
                                                    <p class="text-xs text-muted">{{ $month['hourlyOverride'] !== null ? __('Billed as a flat figure with no hourly lines.') : __('No hourly billing this month.') }}</p>
                                                @endif
                                            </div>

                                            @if($month['payments'])
                                                <div>
                                                    <h3 class="kpi-label mb-2">{{ __('Payments') }}</h3>
                                                    <ul class="space-y-1 text-sm">
                                                        @foreach($month['payments'] as $payment)
                                                            <li class="flex justify-between gap-4"><span>{{ Format::date($payment['paid_on']) }} <span class="text-muted">{{ $payment['note'] }}</span></span><span class="tabular-nums">{{ Format::money($payment['amount'], 2) }}</span></li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="8" class="py-10 text-center text-muted">{{ __('Nothing billed or logged yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card p-0">
        <div class="px-6 pt-5 pb-2">
            <h2 class="section-title">{{ __('Payments') }}</h2>
            <p class="section-note">{{ __(':paid received against :billed billed — :outstanding outstanding.', ['paid' => Format::money($s['paymentsTotal']), 'billed' => Format::money($s['billed']), 'outstanding' => Format::money($s['outstanding'])]) }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th class="is-num">{{ __('Amount') }}</th>
                        <th>{{ __('Note') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $payment)
                        <tr wire:key="pay-{{ $payment->id }}">
                            @if($canEdit)
                                <td><input type="date" class="cell-input w-40 text-left" value="{{ $payment->paid_on->format('Y-m-d') }}" wire:change="updatePayment({{ $payment->id }}, 'paid_on', $event.target.value)"></td>
                                <td class="is-num"><input class="cell-input" value="{{ number_format((float) $payment->amount, 2) }}" wire:change="updatePayment({{ $payment->id }}, 'amount', $event.target.value)"></td>
                                <td><input class="cell-input w-full text-left" value="{{ $payment->note }}" placeholder="—" wire:change="updatePayment({{ $payment->id }}, 'note', $event.target.value)"></td>
                                <td class="text-right">
                                    <button type="button" class="btn-danger" wire:click="deletePayment({{ $payment->id }})" wire:confirm="{{ __('Remove this payment?') }}" title="{{ __('Remove') }}"><i class="ph ph-trash"></i></button>
                                </td>
                            @else
                                <td>{{ Format::date($payment->paid_on->format('Y-m-d')) }}</td>
                                <td class="is-num">{{ Format::money($payment->amount, 2) }}</td>
                                <td>{{ $payment->note ?? '—' }}</td>
                                <td></td>
                            @endif
                        </tr>
                    @endforeach

                    @if($canEdit)
                        <tr>
                            <td><input type="date" class="field-input py-1" wire:model="newPayment.paid_on"></td>
                            <td class="is-num"><input class="field-input py-1 text-right" placeholder="$0.00" wire:model="newPayment.amount"></td>
                            <td><input class="field-input py-1" placeholder="{{ __('cheque no., invoice…') }}" wire:model="newPayment.note"></td>
                            <td class="text-right"><button type="button" class="btn py-1" wire:click="addPayment">{{ __('Add') }}</button></td>
                        </tr>
                    @endif
                </tbody>
            </table>
            @error('newPayment.*') <p class="error-note mx-6 my-3">{{ $message }}</p> @enderror
        </div>
    </section>
</div>
