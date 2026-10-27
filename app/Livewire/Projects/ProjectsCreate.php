<?php

namespace App\Livewire\Projects;

use App\Actions\Billing\CreateProjectAction;
use App\Billing\DeveloperIndex;
use App\Enums\Permission;
use App\Enums\Phase;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\ProjectPhase;
use App\Models\ProjectPhaseFee;
use App\Models\ProjectRole;
use App\Models\ProjectWorkPlan;
use App\Models\TeamMember;
use App\Support\ProjectTemplates;
use App\Support\RateSchedules;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The New project wizard: basics, phases and fees, rates, team, hours, then a
 * review before anything is saved. Covers what Formwerks filled in when it set
 * a job up in Mosaic.
 *
 * Budgets and work plans point at phases by their position in $phases, so
 * removing or moving a phase re-points them (remapPhases).
 *
 * @property-read Collection<int, TeamMember> $members
 * @property-read array<string, mixed> $totals
 */
class ProjectsCreate extends Component
{
    public const Steps = [
        1 => 'Basics',
        2 => 'Phases & fees',
        3 => 'Rates',
        4 => 'Team',
        5 => 'Hours',
        6 => 'Review',
    ];

    public int $step = 1;

    public int $reached = 1;

    public string $copyFrom = '';

    /** @var array{job_number: string, job_name: string, developer: string, division: string, status: string, ca_per_month: string, ifc_date: string, notes: string, lead: string, pm: string} */
    public array $basics = [
        'job_number' => '',
        'job_name' => '',
        'developer' => '',
        'division' => 'MF',
        'status' => 'active',
        'ca_per_month' => '',
        'ifc_date' => '',
        'notes' => '',
        'lead' => '',
        'pm' => '',
    ];

    /** The number the wizard filled in, so a division change only replaces its own suggestion. */
    public string $suggestedJobNumber = '';

    public string $template = 'multi-family';

    public string $totalFee = '';

    /** @var array<int, array{name: string, code: string, share: int|float, fee: string, hours: string, start: string, end: string}> */
    public array $phases = [];

    public string $schedule = 'standard';

    /** @var array<string, string> */
    public array $rates = [];

    /** @var array<int, array{member: string, position: string}> */
    public array $team = [];

    /** @var array<int, array<string, string>> hours by phase position, then member */
    public array $budgets = [];

    /** @var array<int, array{member: string, phase: string, start: string, end: string, daily: string}> */
    public array $plans = [];

    public function mount(): void
    {
        $this->authorize(Permission::EditBilling->value);

        $this->rates = RateSchedules::standard();
        $this->applyTemplate();
        $this->basics['job_number'] = $this->suggestedJobNumber = $this->suggestJobNumber($this->basics['division']);
    }

    /** @return Collection<int, TeamMember> partners first, then PMs, then everyone else */
    #[Computed]
    public function members(): Collection
    {
        return TeamMember::active()
            ->get()
            ->sortBy(fn (TeamMember $member) => [array_search($member->tier, TeamMember::Tiers, true), $member->name])
            ->values();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function totals(): array
    {
        $phases = collect($this->phases);
        $fixed = $phases->where('code', '!=', Phase::Hours);
        $fee = $fixed->sum(fn (array $phase) => (float) $phase['fee']);
        $hours = $phases->sum(fn (array $phase) => (float) $phase['hours']);
        $budgeted = collect($this->budgets)->flatten()->sum(fn ($hours) => (float) $hours);
        $planned = collect($this->plans)->sum(fn (array $plan) => $this->planHours($plan));

        return [
            'fee' => $fee,
            'byCode' => $fixed->groupBy('code')->map(fn (Collection $rows) => $rows->sum(fn (array $phase) => (float) $phase['fee']))->filter()->all(),
            'hours' => $hours,
            'budgeted' => $budgeted,
            'planned' => $planned,
            'blendedRate' => $this->blendedRate(),
            'feePerHour' => $hours > 0 ? $fee / $hours : null,
            'shareTotal' => $phases->sum('share'),
        ];
    }

    public function updatedBasicsDivision(string $division): void
    {
        $this->template = ProjectTemplates::forDivision($division);
        $this->suggestJobNumberIfUntouched($division);

        if (! collect($this->phases)->contains(fn (array $phase) => $phase['fee'] !== '' || $phase['hours'] !== '')) {
            $this->applyTemplate();
        }
    }

    public function updatedSchedule(string $schedule): void
    {
        if ($schedule !== 'custom') {
            $this->rates = RateSchedules::rates($schedule);
        }
    }

    public function updatedRates(): void
    {
        $this->schedule = collect(RateSchedules::options())->keys()
            ->first(fn (string $key) => $key !== 'custom' && $this->sameRates(RateSchedules::rates($key)), 'custom');
    }

    public function applyTemplate(): void
    {
        $this->phases = array_map(fn (array $phase) => [
            'name' => $phase['name'],
            'code' => $phase['code'],
            'share' => $phase['share'],
            'fee' => '',
            'hours' => '',
            'start' => '',
            'end' => '',
        ], ProjectTemplates::phases($this->template));

        $this->budgets = [];
        $this->plans = array_map(fn (array $plan) => [...$plan, 'phase' => ''], $this->plans);

        if ($this->totalFee !== '') {
            $this->splitFee();
        }
    }

    /** Spreads the total fee over the fixed-fee phases by their usual share, to the dollar. */
    public function splitFee(): void
    {
        $this->validateOnly('totalFee', ['totalFee' => ['required', 'numeric', 'min:0']], attributes: ['totalFee' => __('total fee')]);

        $shares = collect($this->phases)->filter(fn (array $phase) => $phase['code'] !== Phase::Hours && $phase['share'] > 0);

        if ($shares->isEmpty()) {
            $shares = collect($this->phases)->filter(fn (array $phase) => $phase['code'] !== Phase::Hours)->map(fn (array $phase) => [...$phase, 'share' => 1]);
        }

        $total = (float) $this->totalFee;
        $shareTotal = $shares->sum('share');
        $left = $total;
        $last = $shares->keys()->last();

        foreach ($shares as $index => $phase) {
            $fee = $index === $last ? $left : round($total * $phase['share'] / $shareTotal);
            $left -= $fee;
            $this->phases[$index]['fee'] = (string) $fee;
        }
    }

    public function addPhase(): void
    {
        $this->phases[] = ['name' => '', 'code' => Phase::Misc->value, 'share' => 0, 'fee' => '', 'hours' => '', 'start' => '', 'end' => ''];
    }

    public function removePhase(int $index): void
    {
        $map = [];

        foreach (array_keys($this->phases) as $old) {
            $map[$old] = $old === $index ? null : ($old > $index ? $old - 1 : $old);
        }

        unset($this->phases[$index]);
        $this->phases = array_values($this->phases);
        $this->remapPhases($map);
    }

    public function movePhase(int $index, int $by): void
    {
        $to = $index + $by;

        if (! isset($this->phases[$index], $this->phases[$to])) {
            return;
        }

        [$this->phases[$index], $this->phases[$to]] = [$this->phases[$to], $this->phases[$index]];

        $map = array_combine(array_keys($this->phases), array_keys($this->phases));
        $map[$index] = $to;
        $map[$to] = $index;
        $this->remapPhases($map);
    }

    public function addMember(string $member = ''): void
    {
        $this->team[] = ['member' => $member, 'position' => $member ? $this->defaultPosition($member) : ''];
    }

    public function removeMember(int $index): void
    {
        $member = $this->team[$index]['member'] ?? null;

        unset($this->team[$index]);
        $this->team = array_values($this->team);

        foreach ($this->budgets as $phase => $byMember) {
            unset($this->budgets[$phase][$member]);
        }
    }

    public function updatedTeam(mixed $value, string $key): void
    {
        [$index, $field] = explode('.', $key, 2);
        $index = (int) $index;

        if ($field === 'member' && is_string($value) && $value !== '' && isset($this->team[$index]) && $this->team[$index]['position'] === '') {
            $this->team[$index]['position'] = $this->defaultPosition($value);
        }
    }

    /** Estimates each phase's hours from its fee at the team's blended rate, to the half hour. */
    public function hoursFromFees(): void
    {
        $rate = $this->blendedRate();

        if (! $rate) {
            return;
        }

        foreach ($this->phases as $index => $phase) {
            if ($phase['code'] !== Phase::Hours && (float) $phase['fee'] > 0) {
                $this->phases[$index]['hours'] = (string) (round((float) $phase['fee'] / $rate * 2) / 2);
            }
        }
    }

    /** Shares each phase's estimated hours evenly across the team. */
    public function splitHoursAcrossTeam(): void
    {
        $members = $this->teamNames();

        if ($members === []) {
            return;
        }

        $this->budgets = [];

        foreach ($this->phases as $index => $phase) {
            $hours = (float) $phase['hours'];

            if ($hours <= 0) {
                continue;
            }

            foreach ($members as $member) {
                $this->budgets[$index][$member] = (string) (round($hours / count($members) * 2) / 2);
            }
        }
    }

    public function addPlan(): void
    {
        $phase = collect($this->phases)->search(fn (array $phase) => $phase['start'] !== '');

        $this->plans[] = [
            'member' => $this->teamNames()[0] ?? '',
            'phase' => $phase === false ? '' : (string) $phase,
            'start' => $phase === false ? now()->toDateString() : $this->phases[$phase]['start'],
            'end' => $phase === false ? now()->addWeeks(4)->toDateString() : ($this->phases[$phase]['end'] ?: $this->phases[$phase]['start']),
            'daily' => '4',
        ];
    }

    public function removePlan(int $index): void
    {
        unset($this->plans[$index]);
        $this->plans = array_values($this->plans);
    }

    /** Fills every step from an existing job: its division, client, phases, fees, rates and team. */
    public function copy(): void
    {
        $project = Project::find($this->copyFrom);

        if (! $project) {
            $this->addError('copyFrom', __('Pick a job to copy.'));

            return;
        }

        $this->basics['developer'] = (string) $project->developer;
        $this->basics['division'] = (string) $project->division;
        $this->basics['ca_per_month'] = $project->ca_per_month === null ? '' : (string) (float) $project->ca_per_month;
        $this->suggestJobNumberIfUntouched($project->division);
        $this->template = ProjectTemplates::forDivision($project->division);

        $phases = $project->phases()->get();

        $this->phases = $phases->isNotEmpty()
            ? $phases->map(fn (ProjectPhase $phase) => [
                'name' => $phase->name,
                'code' => $phase->billing_code,
                'share' => 0,
                'fee' => $phase->fee === null ? '' : (string) (float) $phase->fee,
                'hours' => $phase->estimated_hours === null ? '' : (string) (float) $phase->estimated_hours,
                'start' => '',
                'end' => '',
            ])->all()
            : ProjectPhaseFee::where('job_number', $project->job_number)
                ->get()
                ->sortBy(fn (ProjectPhaseFee $fee) => Phase::seqOf($fee->phase_code))
                ->map(fn (ProjectPhaseFee $fee) => [
                    'name' => ProjectTemplates::nameFor($fee->phase_code),
                    'code' => $fee->phase_code,
                    'share' => 0,
                    'fee' => $fee->fee === null ? '' : (string) (float) $fee->fee,
                    'hours' => '',
                    'start' => '',
                    'end' => '',
                ])
                ->push(['name' => 'Hourly', 'code' => Phase::Hours, 'share' => 0, 'fee' => '', 'hours' => '', 'start' => '', 'end' => ''])
                ->values()
                ->all();

        $overrides = DB::table('project_rate_overrides')->where('job_number', $project->job_number)->pluck('rate', 'rate_title');
        $this->rates = collect(RateSchedules::standard())
            ->map(fn (string $rate, string $title) => isset($overrides[$title]) ? (string) (float) $overrides[$title] : $rate)
            ->all();
        $this->updatedRates();

        $roles = ProjectRole::byJob([$project->job_number])[$project->job_number] ?? [ProjectRole::Lead => [], ProjectRole::ProjectManager => []];
        $names = $this->members->pluck('name');
        $lead = $roles[ProjectRole::Lead][0] ?? '';
        $pm = $roles[ProjectRole::ProjectManager][0] ?? '';
        $this->basics['lead'] = $names->contains($lead) ? $lead : '';
        $this->basics['pm'] = $names->contains($pm) ? $pm : '';

        $this->team = ProjectAssignment::where('job_number', $project->job_number)
            ->whereIn('member_name', $names)
            ->where('member_name', '!=', $this->basics['pm'])
            ->orderBy('member_name')
            ->get()
            ->map(fn (ProjectAssignment $assignment) => ['member' => $assignment->member_name, 'position' => $assignment->position ?? $this->defaultPosition($assignment->member_name)])
            ->values()
            ->all();

        $this->budgets = [];
        $this->plans = [];
        $this->totalFee = '';
    }

    public function goTo(int $step): void
    {
        if ($step < 1 || $step > count(self::Steps)) {
            return;
        }

        if ($step > $this->step) {
            foreach (range($this->step, $step - 1) as $passing) {
                $this->validateStep($passing);
            }
        }

        $this->step = $step;
        $this->reached = max($this->reached, $step);
    }

    public function next(): void
    {
        $this->goTo($this->step + 1);
    }

    public function back(): void
    {
        $this->goTo($this->step - 1);
    }

    public function create(CreateProjectAction $createProject): void
    {
        $this->authorize(Permission::EditBilling->value);

        foreach (array_keys(self::Steps) as $step) {
            try {
                $this->validateStep($step);
            } catch (ValidationException $exception) {
                $this->step = $step;

                throw $exception;
            }
        }

        $project = $createProject->execute([
            ...$this->basics,
            'phases' => $this->phases,
            'rates' => $this->rates,
            'team' => array_values(array_filter($this->team, fn (array $row) => $row['member'] !== '')),
            'budgets' => $this->budgets,
            'plans' => $this->plans,
        ]);

        $this->redirectRoute('projects.show', $project->job_number, navigate: true);
    }

    /** @param array{member: string, phase: string, start: string, end: string, daily: string} $plan */
    public function planHours(array $plan): float
    {
        if ($plan['start'] === '' || $plan['end'] === '' || ! is_numeric($plan['daily'])) {
            return 0.0;
        }

        return ProjectWorkPlan::hoursBetween($plan['start'], $plan['end'], (float) $plan['daily']);
    }

    public function render(): View
    {
        $names = $this->members->pluck('name');

        return view('livewire.projects.create', [
            'steps' => self::Steps,
            'templates' => ProjectTemplates::options(),
            'billingCodes' => ProjectTemplates::billingCodes(),
            'schedules' => RateSchedules::options(),
            'standardRates' => RateSchedules::standard(),
            'positions' => ProjectAssignment::Positions,
            'statuses' => ['active' => __('Active'), 'paused' => __('On hold')],
            'divisions' => collect(Project::query()->whereNotNull('division')->distinct()->pluck('division'))
                ->merge(array_keys(ProjectTemplates::ByDivision))
                ->filter()
                ->unique()
                ->sort()
                ->values(),
            'clients' => collect(app(DeveloperIndex::class)->all())->pluck('name')->merge(Project::query()->whereNotNull('developer')->distinct()->pluck('developer'))->unique()->sort()->values(),
            'jobs' => Project::query()->get(['job_number', 'job_name'])->sortByDesc(fn (Project $project) => (int) Str::after($project->job_number, '#'))->values(),
            'teamNames' => $this->teamNames(),
            'available' => $names->diff(collect($this->team)->pluck('member'))->values(),
            'rateTitles' => $this->rateTitles(),
        ])->title(__('New project'));
    }

    /** @return array<int, string> the PM and everyone on the team, once each */
    protected function teamNames(): array
    {
        return collect([$this->basics['pm']])
            ->merge(collect($this->team)->pluck('member'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** The average charge-out of the people on the job at this job's rates, or of the four titles with nobody on it yet. */
    protected function blendedRate(): ?float
    {
        $titles = $this->rateTitles();
        $rates = collect($this->teamNames())
            ->map(fn (string $name) => (float) ($this->rates[$titles[$name] ?? 'Intermediate'] ?? 0))
            ->filter();

        if ($rates->isEmpty()) {
            $rates = collect($this->rates)->map(fn ($rate) => (float) $rate)->filter();
        }

        return $rates->isEmpty() ? null : $rates->avg();
    }

    /** @return array<string, string> each person's billing title, from the workbook's staff list */
    protected function rateTitles(): array
    {
        return DB::table('staff')->whereNotNull('rate_title')->pluck('rate_title', 'name')
            ->map(fn (string $title) => in_array($title, RateSchedules::Titles, true) ? $title : 'Intermediate')
            ->all();
    }

    protected function defaultPosition(string $member): string
    {
        $person = $this->members->firstWhere('name', $member);

        return match (true) {
            $person?->isPartner() => 'Principal',
            $person?->isPm() => 'Project Manager',
            $person?->grp === 'tech' => 'Technologist',
            default => '',
        };
    }

    /** @param array<string, string> $rates */
    protected function sameRates(array $rates): bool
    {
        return collect(RateSchedules::Titles)->every(fn (string $title) => (float) ($rates[$title] ?? 0) === (float) ($this->rates[$title] ?? 0));
    }

    /** @param array<int, ?int> $map old phase position => new position, or null when it was removed */
    protected function remapPhases(array $map): void
    {
        $budgets = [];

        foreach ($this->budgets as $old => $byMember) {
            if (($map[$old] ?? null) !== null) {
                $budgets[$map[$old]] = $byMember;
            }
        }

        $this->budgets = $budgets;

        foreach ($this->plans as $index => $plan) {
            if ($plan['phase'] !== '') {
                $new = $map[(int) $plan['phase']] ?? null;
                $this->plans[$index]['phase'] = $new === null ? '' : (string) $new;
            }
        }
    }

    /** Swaps in the division's next number, unless someone typed their own. */
    protected function suggestJobNumberIfUntouched(?string $division): void
    {
        if (in_array($this->basics['job_number'], ['', $this->suggestedJobNumber], true)) {
            $this->basics['job_number'] = $this->suggestedJobNumber = $this->suggestJobNumber($division);
        }
    }

    protected function suggestJobNumber(?string $division): string
    {
        $taken = Project::query()->pluck('job_number')->map(fn (string $number) => (int) Str::after($number, '#'));
        $inDivision = Project::query()->where('division', $division)->pluck('job_number')->map(fn (string $number) => (int) Str::after($number, '#'));
        $next = ($inDivision->max() ?: $taken->max() ?: 0) + 1;

        while ($taken->contains($next)) {
            $next++;
        }

        return "#{$next}";
    }

    protected function validateStep(int $step): void
    {
        $members = $this->members->pluck('name')->all();

        match ($step) {
            1 => $this->validate([
                'basics.job_number' => ['required', 'string', 'max:40', function (string $attribute, string $value, Closure $fail) {
                    if (Project::whereKey(Project::normalizeJobNumber($value))->exists()) {
                        $fail(__('Job :number already exists.', ['number' => Project::normalizeJobNumber($value)]));
                    }
                }],
                'basics.job_name' => ['required', 'string', 'max:255', Rule::unique('projects', 'job_name')],
                'basics.developer' => ['nullable', 'string', 'max:255'],
                'basics.division' => ['nullable', 'string', 'max:20'],
                'basics.status' => ['required', Rule::in(['active', 'paused'])],
                'basics.ca_per_month' => ['nullable', 'numeric', 'min:0'],
                'basics.ifc_date' => ['nullable', 'date'],
                'basics.notes' => ['nullable', 'string', 'max:2000'],
            ], attributes: [
                'basics.job_number' => __('job number'),
                'basics.job_name' => __('project name'),
                'basics.ca_per_month' => __('CA per month'),
            ]),
            2 => $this->validate([
                'phases' => ['required', 'array', 'min:1'],
                'phases.*.name' => ['required', 'string', 'max:255', 'distinct'],
                'phases.*.code' => ['required', Rule::in(ProjectTemplates::billingCodes())],
                'phases.*.fee' => ['nullable', 'numeric', 'min:0'],
                'phases.*.start' => ['nullable', 'date'],
                'phases.*.end' => ['nullable', 'date', 'after_or_equal:phases.*.start'],
            ], messages: [
                'phases.required' => __('A project needs at least one phase.'),
                'phases.*.name.required' => __('Name the phase.'),
                'phases.*.name.distinct' => __('Two phases have this name.'),
                'phases.*.end.after_or_equal' => __('Ends before it starts.'),
            ]),
            3 => $this->validate([
                'rates.*' => ['nullable', 'numeric', 'min:0'],
            ]),
            4 => $this->validate([
                'basics.lead' => ['nullable', Rule::in($members)],
                'basics.pm' => ['nullable', Rule::in($members)],
                'team.*.member' => ['required', 'distinct', Rule::in($members)],
                'team.*.position' => ['nullable', 'string', 'max:60'],
            ], messages: [
                'team.*.member.required' => __('Pick someone, or remove the row.'),
                'team.*.member.distinct' => __('Already on the team.'),
            ]),
            5 => $this->validate([
                'phases.*.hours' => ['nullable', 'numeric', 'min:0'],
                'budgets.*.*' => ['nullable', 'numeric', 'min:0'],
                'plans.*.member' => ['required', Rule::in($members)],
                'plans.*.phase' => ['nullable', Rule::in(array_map('strval', array_keys($this->phases)))],
                'plans.*.start' => ['required', 'date'],
                'plans.*.end' => ['required', 'date', 'after_or_equal:plans.*.start'],
                'plans.*.daily' => ['required', 'numeric', 'gt:0', 'max:24'],
            ], messages: [
                'plans.*.end.after_or_equal' => __('Ends before it starts.'),
            ]),
            default => null,
        };
    }
}
