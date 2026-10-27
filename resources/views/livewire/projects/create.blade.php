@php
    use App\Enums\Phase;
    use App\Models\ProjectWorkPlan;
    use App\Support\Format;

    $totals = $this->totals;
    $members = $this->members;
@endphp

<div class="space-y-8">
    <div>
        <a href="{{ route('projects.index') }}" class="btn-secondary" wire:navigate><i class="ph ph-arrow-left"></i>{{ __('Projects') }}</a>

        <div class="mt-5 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="page-title">{{ __('New project') }}</h1>
                <p class="page-subtitle">{{ __('Everything a job was set up with in Mosaic: phases and fees, rates, the team and the hours. Nothing is saved until you create it on the last step.') }}</p>
            </div>
        </div>

        <ol class="mt-6 grid grid-cols-3 gap-px border border-line bg-line text-sm sm:grid-cols-6">
            @foreach($steps as $number => $label)
                <li>
                    <button
                        type="button"
                        wire:click="goTo({{ $number }})"
                        @disabled($number > max($reached, $step + 1))
                        @class([
                            'flex h-full w-full items-center gap-2 px-3 py-2.5 text-left disabled:cursor-not-allowed',
                            'bg-navy text-white' => $number === $step,
                            'bg-white text-ink hover:bg-hover' => $number !== $step && $number <= $reached,
                            'bg-white text-muted' => $number !== $step && $number > $reached,
                        ])
                        @if($number === $step) aria-current="step" @endif
                    >
                        <span @class([
                            'flex size-5 shrink-0 items-center justify-center rounded-full border text-xs',
                            'border-white/60' => $number === $step,
                            'border-gold bg-gold text-white' => $number !== $step && $number < $reached,
                            'border-line' => $number !== $step && $number >= $reached,
                        ])>
                            @if($number !== $step && $number < $reached)<i class="ph ph-check"></i>@else{{ $number }}@endif
                        </span>
                        <span class="truncate">{{ __($label) }}</span>
                    </button>
                </li>
            @endforeach
        </ol>
    </div>

    <div class="grid items-start gap-6 2xl:grid-cols-[minmax(0,1fr)_260px]">
        <form wire:submit="{{ $step === count($steps) ? 'create' : 'next' }}" class="card space-y-6">
            {{-- 1 · Basics --}}
            @if($step === 1)
                <div class="flex flex-wrap items-end gap-3 border-b border-line pb-5">
                    <div class="min-w-64 flex-1">
                        <label class="field-label" for="copy-from">{{ __('Start from an existing job (optional)') }}</label>
                        <select id="copy-from" wire:model="copyFrom" class="field-input">
                            <option value="">{{ __('— a blank job —') }}</option>
                            @foreach($jobs as $job)
                                <option value="{{ $job->job_number }}">{{ $job->job_number }} · {{ $job->job_name }}</option>
                            @endforeach
                        </select>
                        @error('copyFrom') <p class="error-note mt-1">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" class="btn-secondary" wire:click="copy"><i class="ph ph-copy"></i>{{ __('Copy its set-up') }}</button>
                    <p class="w-full text-xs text-muted">{{ __('Copies the division, client, phases and fees, rates and team. Dates, hours and work plans start blank.') }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="field-label" for="job_number">{{ __('Job number') }}</label>
                        <input id="job_number" wire:model.blur="basics.job_number" class="field-input" placeholder="#8120">
                        @error('basics.job_number') <p class="error-note mt-1">{{ $message }}</p> @else <p class="mt-1 text-xs text-muted">{{ __('Next free number in the division') }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label" for="job_name">{{ __('Project name') }}</label>
                        <input id="job_name" wire:model.blur="basics.job_name" class="field-input" placeholder="{{ __('Usually the address, e.g. 1710 Macdonald St') }}" autofocus>
                        @error('basics.job_name') <p class="error-note mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label" for="developer">{{ __('Client / developer') }}</label>
                        <input id="developer" wire:model.blur="basics.developer" class="field-input" list="client-options" autocomplete="off">
                        <datalist id="client-options">
                            @foreach($clients as $client)
                                <option value="{{ $client }}">
                            @endforeach
                        </datalist>
                    </div>
                    <div>
                        <label class="field-label" for="division">{{ __('Division') }}</label>
                        <select id="division" wire:model.live="basics.division" class="field-input">
                            <option value="">—</option>
                            @foreach($divisions as $division)
                                <option value="{{ $division }}">{{ $division }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <span class="field-label">{{ __('Status') }}</span>
                        <div class="seg">
                            @foreach($statuses as $value => $label)
                                <button type="button" wire:click="$set('basics.status', '{{ $value }}')" @class(['is-active' => $basics['status'] === $value])>{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label class="field-label" for="ca_per_month">{{ __('CA per month') }}</label>
                        <input id="ca_per_month" type="number" step="0.01" min="0" wire:model.blur="basics.ca_per_month" class="field-input" placeholder="0.00">
                        @error('basics.ca_per_month') <p class="error-note mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label" for="ifc_date">{{ __('Target IFC date') }}</label>
                        <input id="ifc_date" type="date" wire:model.blur="basics.ifc_date" class="field-input">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="field-label" for="notes">{{ __('Notes') }}</label>
                        <textarea id="notes" wire:model.blur="basics.notes" rows="2" class="field-input" placeholder="{{ __('Scope, contract terms, anything the team should know') }}"></textarea>
                    </div>
                </div>
            @endif

            {{-- 2 · Phases & fees --}}
            @if($step === 2)
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-56">
                        <label class="field-label" for="template">{{ __('Phase template') }}</label>
                        <select id="template" wire:model="template" class="field-input">
                            @foreach($templates as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="button" class="btn-secondary" wire:click="applyTemplate" wire:confirm="{{ __('Replace the phases below with this template?') }}">{{ __('Use template') }}</button>

                    <div class="ml-auto flex items-end gap-2">
                        <div class="w-40">
                            <label class="field-label" for="total-fee">{{ __('Total fixed fee') }}</label>
                            <input id="total-fee" type="number" min="0" step="1" wire:model="totalFee" class="field-input" placeholder="0">
                        </div>
                        <button type="button" class="btn-secondary" wire:click="splitFee">{{ __('Split by usual shares') }}</button>
                    </div>
                    @error('totalFee') <p class="error-note w-full text-right">{{ $message }}</p> @enderror
                </div>

                <div class="-mx-6 overflow-x-auto">
                    <table class="data-table min-w-[980px]">
                        <thead>
                            <tr>
                                <th class="w-14"></th>
                                <th class="min-w-72">{{ __('Phase') }}</th>
                                <th class="w-36">{{ __('Bills under') }}</th>
                                <th class="is-num w-16">{{ __('Usual') }}</th>
                                <th class="is-num w-32">{{ __('Fee') }}</th>
                                <th class="w-36">{{ __('Start') }}</th>
                                <th class="w-36">{{ __('End') }}</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($phases as $index => $phase)
                                <tr wire:key="phase-{{ $index }}-{{ md5($phase['name']) }}">
                                    <td class="whitespace-nowrap text-muted">
                                        <button type="button" class="hover:text-navy disabled:opacity-30" wire:click="movePhase({{ $index }}, -1)" @disabled($index === 0) aria-label="{{ __('Move up') }}"><i class="ph ph-arrow-up"></i></button>
                                        <button type="button" class="hover:text-navy disabled:opacity-30" wire:click="movePhase({{ $index }}, 1)" @disabled($index === count($phases) - 1) aria-label="{{ __('Move down') }}"><i class="ph ph-arrow-down"></i></button>
                                    </td>
                                    <td>
                                        <input wire:model.blur="phases.{{ $index }}.name" class="cell-input w-full text-left" placeholder="{{ __('Phase name') }}" aria-label="{{ __('Phase name') }}">
                                        @error("phases.$index.name") <p class="error-note">{{ $message }}</p> @enderror
                                    </td>
                                    <td>
                                        <select wire:model.live="phases.{{ $index }}.code" class="cell-input w-full text-left" aria-label="{{ __('Bills under') }}">
                                            @foreach($billingCodes as $code)
                                                <option value="{{ $code }}">{{ $code === Phase::Hours ? __('Hourly') : $code }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="is-num text-xs text-muted">{{ $phase['share'] ? $phase['share'].'%' : '' }}</td>
                                    <td class="is-num">
                                        @if($phase['code'] === Phase::Hours)
                                            <span class="text-xs text-muted">{{ __('by the hour') }}</span>
                                        @else
                                            <input type="number" min="0" step="1" wire:model.blur="phases.{{ $index }}.fee" class="cell-input" placeholder="—" aria-label="{{ __(':phase fee', ['phase' => $phase['name']]) }}">
                                            @error("phases.$index.fee") <p class="error-note">{{ $message }}</p> @enderror
                                        @endif
                                    </td>
                                    <td><input type="date" wire:model.blur="phases.{{ $index }}.start" class="cell-input w-full text-left" aria-label="{{ __('Start') }}"></td>
                                    <td>
                                        <input type="date" wire:model.blur="phases.{{ $index }}.end" class="cell-input w-full text-left" aria-label="{{ __('End') }}">
                                        @error("phases.$index.end") <p class="error-note">{{ $message }}</p> @enderror
                                    </td>
                                    <td>
                                        <button type="button" class="text-muted hover:text-danger" wire:click="removePhase({{ $index }})" aria-label="{{ __('Remove phase') }}"><i class="ph ph-trash"></i></button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td></td>
                                <td colspan="3">
                                    <button type="button" class="btn-secondary py-0.5" wire:click="addPhase"><i class="ph ph-plus"></i>{{ __('Add phase') }}</button>
                                </td>
                                <td class="is-num font-medium">{{ Format::money($totals['fee']) }}</td>
                                <td colspan="3" class="text-xs text-muted">{{ __('fixed fee') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @error('phases') <p class="error-note">{{ $message }}</p> @enderror

                <p class="text-xs text-muted">
                    {{ __('Each phase is billed at Month End under one of the fixed billing phases. Phases that share one are added together.') }}
                    @if($totals['byCode'])
                        <span class="mt-2 flex flex-wrap gap-1.5">
                            @foreach($totals['byCode'] as $code => $fee)
                                <span class="chip">{{ $code }} {{ Format::money($fee) }}</span>
                            @endforeach
                        </span>
                    @endif
                </p>
            @endif

            {{-- 3 · Rates --}}
            @if($step === 3)
                <div class="max-w-xl space-y-5">
                    <div>
                        <label class="field-label" for="schedule">{{ __('Charge-out schedule') }}</label>
                        <select id="schedule" wire:model.live="schedule" class="field-input">
                            @foreach($schedules as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-muted">{{ __('Junior – Intermediate – Senior – Principal. Hourly work on this job is billed at these rates.') }}</p>
                    </div>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Title') }}</th>
                                <th class="is-num">{{ __('Standard') }}</th>
                                <th class="is-num w-36">{{ __('This job') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rates as $title => $rate)
                                <tr wire:key="rate-{{ $title }}">
                                    <td class="font-medium">{{ $title }}</td>
                                    <td class="is-num text-muted">{{ $standardRates[$title] !== '' ? '$'.$standardRates[$title] : '—' }}</td>
                                    <td class="is-num">
                                        <input type="number" min="0" step="1" wire:model.blur="rates.{{ $title }}" class="cell-input" aria-label="{{ __(':title rate', ['title' => $title]) }}">
                                        @error("rates.$title") <p class="error-note">{{ $message }}</p> @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="text-xs text-muted">{{ __('Only rates that differ from the standard are saved, as overrides for this job.') }}</p>
                </div>
            @endif

            {{-- 4 · Team --}}
            @if($step === 4)
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="field-label" for="lead">{{ __('Lead (partner)') }}</label>
                        <select id="lead" wire:model="basics.lead" class="field-input">
                            <option value="">—</option>
                            @foreach($members as $member)
                                <option value="{{ $member->name }}">{{ $member->displayName() }}@if($member->isPartner()) · {{ __('partner') }}@endif</option>
                            @endforeach
                        </select>
                        @error('basics.lead') <p class="error-note mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label" for="pm">{{ __('Project manager') }}</label>
                        <select id="pm" wire:model.live="basics.pm" class="field-input">
                            <option value="">—</option>
                            @foreach($members as $member)
                                <option value="{{ $member->name }}">{{ $member->displayName() }}@if($member->isPm()) · {{ __('PM') }}@endif</option>
                            @endforeach
                        </select>
                        @error('basics.pm') <p class="error-note mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <h2 class="section-title">{{ __('Team') }}</h2>
                    <p class="section-note">{{ __('Who works on the job and in what role. They see it under My projects; the PM is added for you.') }}</p>

                    <table class="data-table mt-3">
                        <thead>
                            <tr>
                                <th>{{ __('Person') }}</th>
                                <th>{{ __('Role on the job') }}</th>
                                <th class="is-num">{{ __('Bills at') }}</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($basics['pm'])
                                <tr class="is-muted">
                                    <td class="font-medium">{{ $basics['pm'] }}</td>
                                    <td>{{ __('Project Manager') }}</td>
                                    <td class="is-num">{{ isset($rateTitles[$basics['pm']]) ? $rateTitles[$basics['pm']].' $'.($rates[$rateTitles[$basics['pm']]] ?? '—') : '—' }}</td>
                                    <td></td>
                                </tr>
                            @endif
                            @foreach($team as $index => $row)
                                <tr wire:key="member-{{ $index }}-{{ $row['member'] }}">
                                    <td>
                                        <select wire:model.live="team.{{ $index }}.member" class="cell-input w-full text-left" aria-label="{{ __('Person') }}">
                                            <option value="">{{ __('Pick someone') }}</option>
                                            @foreach($members as $member)
                                                @if($member->name === $row['member'] || $available->contains($member->name))
                                                    <option value="{{ $member->name }}">{{ $member->displayName() }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        @error("team.$index.member") <p class="error-note">{{ $message }}</p> @enderror
                                    </td>
                                    <td>
                                        <input wire:model.blur="team.{{ $index }}.position" class="cell-input w-full text-left" list="position-options" placeholder="{{ __('e.g. Technologist') }}" aria-label="{{ __('Role on the job') }}">
                                    </td>
                                    <td class="is-num text-ink-2">{{ isset($rateTitles[$row['member']]) ? $rateTitles[$row['member']].' $'.($rates[$rateTitles[$row['member']]] ?? '—') : '—' }}</td>
                                    <td>
                                        <button type="button" class="text-muted hover:text-danger" wire:click="removeMember({{ $index }})" aria-label="{{ __('Remove from team') }}"><i class="ph ph-x"></i></button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <datalist id="position-options">
                        @foreach($positions as $position)
                            <option value="{{ $position }}">
                        @endforeach
                    </datalist>

                    @if($available->isNotEmpty())
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span class="text-sm text-muted">{{ __('Add') }}</span>
                            @foreach($members->whereIn('name', $available)->reject(fn ($member) => $member->name === $basics['pm']) as $member)
                                <button type="button" class="chip hover:border-navy hover:text-navy" wire:click="addMember(@js($member->name))"><i class="ph ph-plus"></i>{{ $member->name }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            {{-- 5 · Hours --}}
            @if($step === 5)
                <div>
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="section-title">{{ __('Hours per phase') }}</h2>
                            <p class="section-note">{{ __('The budget the team works to. The fee divided by the hours is what the job earns per hour.') }}</p>
                        </div>
                        <button type="button" class="btn-secondary" wire:click="hoursFromFees" @disabled(! $totals['blendedRate'])>
                            <i class="ph ph-calculator"></i>{{ __('Estimate from fees at :rate/h', ['rate' => $totals['blendedRate'] ? '$'.number_format($totals['blendedRate']) : '—']) }}
                        </button>
                    </div>

                    <div class="-mx-6 mt-3 overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Phase') }}</th>
                                    <th class="is-num">{{ __('Fee') }}</th>
                                    <th class="is-num w-32">{{ __('Hours') }}</th>
                                    <th class="is-num">{{ __('$/h') }}</th>
                                    @foreach($teamNames as $name)
                                        <th class="is-num w-24">{{ $name }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($phases as $index => $phase)
                                    @php
                                        $budgeted = collect($budgets[$index] ?? [])->sum(fn ($hours) => (float) $hours);
                                    @endphp
                                    <tr wire:key="hours-{{ $index }}">
                                        <td class="font-medium">{{ $phase['name'] }}</td>
                                        <td class="is-num text-ink-2">{{ $phase['code'] === Phase::Hours ? __('hourly') : ((float) $phase['fee'] ? Format::money($phase['fee']) : '—') }}</td>
                                        <td class="is-num">
                                            <input type="number" min="0" step="0.5" wire:model.blur="phases.{{ $index }}.hours" class="cell-input" placeholder="—" aria-label="{{ __(':phase hours', ['phase' => $phase['name']]) }}">
                                            @if($budgeted && (float) $phase['hours'] && abs($budgeted - (float) $phase['hours']) >= 0.5)
                                                <p class="text-xs text-warning">{{ Format::number($budgeted, 1) }} {{ __('split') }}</p>
                                            @endif
                                        </td>
                                        <td class="is-num text-muted">{{ (float) $phase['fee'] && (float) $phase['hours'] ? '$'.number_format((float) $phase['fee'] / (float) $phase['hours']) : '—' }}</td>
                                        @foreach($teamNames as $name)
                                            <td class="is-num">
                                                <input type="number" min="0" step="0.5" wire:model.blur="budgets.{{ $index }}.{{ $name }}" class="cell-input" placeholder="—" aria-label="{{ __(':name hours on :phase', ['name' => $name, 'phase' => $phase['name']]) }}">
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td class="font-medium">{{ __('Total') }}</td>
                                    <td class="is-num">{{ Format::money($totals['fee']) }}</td>
                                    <td class="is-num font-medium">{{ Format::number($totals['hours'], 1) }}</td>
                                    <td class="is-num">{{ $totals['feePerHour'] ? '$'.number_format($totals['feePerHour']) : '—' }}</td>
                                    @foreach($teamNames as $name)
                                        <td class="is-num">{{ Format::number(collect($budgets)->sum(fn ($byMember) => (float) ($byMember[$name] ?? 0)), 1) }}</td>
                                    @endforeach
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @if($teamNames)
                        <button type="button" class="btn-secondary mt-3" wire:click="splitHoursAcrossTeam" wire:confirm="{{ __('Replace the per-person hours with an even split?') }}"><i class="ph ph-users-three"></i>{{ __('Split hours evenly across the team') }}</button>
                    @else
                        <p class="mt-3 text-xs text-muted">{{ __('Add a PM or team on the Team step to budget hours per person.') }}</p>
                    @endif
                    @error('budgets.*.*') <p class="error-note mt-2">{{ $message }}</p> @enderror
                </div>

                <div class="border-t border-line pt-6">
                    <h2 class="section-title">{{ __('Work plans') }}</h2>
                    <p class="section-note">{{ __('Book people onto phases for a stretch of weekdays. A full day is :hours h.', ['hours' => ProjectWorkPlan::FullDay]) }}</p>

                    @if($plans)
                        <div class="-mx-6 mt-3 overflow-x-auto">
                            <table class="data-table min-w-[820px]">
                                <thead>
                                    <tr>
                                        <th>{{ __('Person') }}</th>
                                        <th>{{ __('Phase') }}</th>
                                        <th class="w-36">{{ __('From') }}</th>
                                        <th class="w-36">{{ __('To') }}</th>
                                        <th class="is-num w-24">{{ __('h/day') }}</th>
                                        <th class="is-num">{{ __('Total') }}</th>
                                        <th class="w-10"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($plans as $index => $plan)
                                        <tr wire:key="plan-{{ $index }}">
                                            <td>
                                                <select wire:model.blur="plans.{{ $index }}.member" class="cell-input w-full text-left" aria-label="{{ __('Person') }}">
                                                    <option value="">{{ __('Pick someone') }}</option>
                                                    @foreach($members as $member)
                                                        <option value="{{ $member->name }}">{{ $member->name }}@if(! in_array($member->name, $teamNames, true)) · {{ __('not on team') }}@endif</option>
                                                    @endforeach
                                                </select>
                                                @error("plans.$index.member") <p class="error-note">{{ $message }}</p> @enderror
                                            </td>
                                            <td>
                                                <select wire:model.blur="plans.{{ $index }}.phase" class="cell-input w-full text-left" aria-label="{{ __('Phase') }}">
                                                    <option value="">{{ __('Whole job') }}</option>
                                                    @foreach($phases as $phaseIndex => $phase)
                                                        <option value="{{ $phaseIndex }}">{{ $phase['name'] }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <input type="date" wire:model.blur="plans.{{ $index }}.start" class="cell-input w-full text-left" aria-label="{{ __('From') }}">
                                                @error("plans.$index.start") <p class="error-note">{{ $message }}</p> @enderror
                                            </td>
                                            <td>
                                                <input type="date" wire:model.blur="plans.{{ $index }}.end" class="cell-input w-full text-left" aria-label="{{ __('To') }}">
                                                @error("plans.$index.end") <p class="error-note">{{ $message }}</p> @enderror
                                            </td>
                                            <td class="is-num">
                                                <input type="number" min="0.5" max="24" step="0.5" wire:model.blur="plans.{{ $index }}.daily" class="cell-input" aria-label="{{ __('Hours a day') }}">
                                                @error("plans.$index.daily") <p class="error-note">{{ $message }}</p> @enderror
                                                @if((float) $plan['daily'] > ProjectWorkPlan::FullDay)
                                                    <p class="text-xs text-warning">{{ __('over a full day') }}</p>
                                                @endif
                                            </td>
                                            <td class="is-num text-ink-2">{{ Format::number($this->planHours($plan), 1) }} h</td>
                                            <td>
                                                <button type="button" class="text-muted hover:text-danger" wire:click="removePlan({{ $index }})" aria-label="{{ __('Remove work plan') }}"><i class="ph ph-trash"></i></button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <button type="button" class="btn-secondary mt-3" wire:click="addPlan"><i class="ph ph-plus"></i>{{ __('Add work plan') }}</button>
                </div>
            @endif

            {{-- 6 · Review --}}
            @if($step === 6)
                <div class="grid gap-6 2xl:grid-cols-2">
                    <div>
                        <div class="text-xs tracking-[0.1em] text-muted uppercase">{{ \App\Models\Project::normalizeJobNumber($basics['job_number']) }}</div>
                        <h2 class="section-title mt-1 text-xl">{{ $basics['job_name'] }}</h2>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @if($basics['developer']) <span class="chip">{{ $basics['developer'] }}</span> @endif
                            @if($basics['division']) <span class="chip">{{ $basics['division'] }}</span> @endif
                            <span @class(['chip', 'is-on' => $basics['status'] === 'active', 'is-warn' => $basics['status'] === 'paused'])>{{ $statuses[$basics['status']] }}</span>
                            @if($basics['lead']) <span class="chip">{{ __('Lead') }} {{ $basics['lead'] }}</span> @endif
                            @if($basics['pm']) <span class="chip">{{ __('PM') }} {{ $basics['pm'] }}</span> @endif
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            <dt class="text-muted">{{ __('CA per month') }}</dt><dd>{{ $basics['ca_per_month'] !== '' ? Format::money($basics['ca_per_month'], 2) : '—' }}</dd>
                            <dt class="text-muted">{{ __('Target IFC') }}</dt><dd>{{ $basics['ifc_date'] ? Format::date($basics['ifc_date']) : '—' }}</dd>
                            <dt class="text-muted">{{ __('Rates') }}</dt><dd>{{ implode('-', array_map(fn ($rate) => $rate === '' ? '—' : $rate, $rates)) }}@if($schedule === 'standard') <span class="text-muted">{{ __('standard') }}</span>@endif</dd>
                            <dt class="text-muted">{{ __('Team') }}</dt><dd>{{ $teamNames ? implode(', ', $teamNames) : '—' }}</dd>
                            <dt class="text-muted">{{ __('Work plans') }}</dt><dd>{{ count($plans) }} · {{ Format::number($totals['planned'], 1) }} h</dd>
                        </dl>
                        @if($basics['notes'])
                            <p class="mt-4 text-sm whitespace-pre-line text-ink-2">{{ $basics['notes'] }}</p>
                        @endif
                    </div>

                    <div class="min-w-0 overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Phase') }}</th>
                                    <th>{{ __('Bills under') }}</th>
                                    <th class="is-num">{{ __('Fee') }}</th>
                                    <th class="is-num">{{ __('Hours') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($phases as $phase)
                                    <tr>
                                        <td>
                                            {{ $phase['name'] }}
                                            @if($phase['start'] || $phase['end'])
                                                <span class="block text-xs text-muted">{{ $phase['start'] ? Format::date($phase['start']) : '…' }} – {{ $phase['end'] ? Format::date($phase['end']) : '…' }}</span>
                                            @endif
                                        </td>
                                        <td class="text-ink-2">{{ $phase['code'] === Phase::Hours ? __('Hourly') : $phase['code'] }}</td>
                                        <td class="is-num">{{ $phase['code'] === Phase::Hours ? '—' : Format::money((float) $phase['fee']) }}</td>
                                        <td class="is-num">{{ (float) $phase['hours'] ? Format::number($phase['hours'], 1) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" class="font-medium">{{ __('Total') }}</td>
                                    <td class="is-num font-medium">{{ Format::money($totals['fee']) }}</td>
                                    <td class="is-num font-medium">{{ Format::number($totals['hours'], 1) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                @if($errors->any())
                    <p class="error-note">{{ $errors->first() }}</p>
                @endif
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5">
                @if($step > 1)
                    <button type="button" class="btn-secondary" wire:click="back"><i class="ph ph-arrow-left"></i>{{ __('Back') }}</button>
                @else
                    <a href="{{ route('projects.index') }}" class="btn-secondary" wire:navigate>{{ __('Cancel') }}</a>
                @endif

                @if($step < count($steps))
                    <button type="submit" class="btn" wire:loading.attr="disabled">{{ __('Next: :step', ['step' => __($steps[$step + 1])]) }}<i class="ph ph-arrow-right"></i></button>
                @else
                    <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="create"><i class="ph ph-check"></i>{{ __('Create project') }}</button>
                @endif
            </div>
        </form>

        <aside class="card order-first text-sm 2xl:sticky 2xl:top-6 2xl:order-none">
            <div class="flex flex-wrap items-center justify-between gap-x-8 gap-y-3 2xl:block 2xl:space-y-4">
                <div>
                    <div class="text-xs text-muted">{{ \App\Models\Project::normalizeJobNumber($basics['job_number'] ?: '—') }}</div>
                    <div class="font-medium">{{ $basics['job_name'] ?: __('Untitled job') }}</div>
                </div>
                <dl class="grid grid-cols-3 gap-x-6 gap-y-2 sm:grid-cols-6 2xl:grid-cols-2">
                    @foreach([
                        __('Phases') => count($phases),
                        __('Fixed fee') => Format::money($totals['fee']),
                        __('Hours') => $totals['hours'] ? Format::number($totals['hours'], 1) : '—',
                        __('Fee per hour') => $totals['feePerHour'] ? '$'.number_format($totals['feePerHour']) : '—',
                        __('Team') => count($teamNames),
                        __('Planned') => $totals['planned'] ? Format::number($totals['planned'], 1).' h' : '—',
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs text-muted">{{ $label }}</dt>
                            <dd class="font-medium">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </aside>
    </div>
</div>
