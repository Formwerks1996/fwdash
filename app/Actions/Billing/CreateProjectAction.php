<?php

namespace App\Actions\Billing;

use App\Actions\Team\AssignProjectAction;
use App\Actions\Team\SetProjectRoleAction;
use App\Enums\Phase;
use App\Models\Project;
use App\Models\ProjectHourBudget;
use App\Models\ProjectPhase;
use App\Models\ProjectRole;
use App\Models\ProjectWorkPlan;
use App\Models\TeamMember;
use App\Support\AuditLogger;
use App\Support\Money;
use App\Support\ProjectTemplates;
use App\Support\RateSchedules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets a new job up in one go, the way the New project wizard collects it:
 * the project row, its phases (whose fees, summed per billing phase, become
 * the fee ladder Month End bills against), its charge-out rates, its lead, PM
 * and team, the hours budgeted per phase and person, and its work plans.
 * Either all of it is saved or none of it is.
 *
 * @phpstan-type Setup array{
 *     job_number: string, job_name: string, developer?: ?string, division?: ?string,
 *     status?: string, ca_per_month?: ?string, ifc_date?: ?string, notes?: ?string,
 *     lead?: ?string, pm?: ?string,
 *     phases: array<int, array{name: string, code: string, fee?: ?string, hours?: ?string, start?: ?string, end?: ?string}>,
 *     rates?: array<string, ?string>,
 *     team?: array<int, array{member: string, position?: ?string}>,
 *     budgets?: array<int, array<string, ?string>>,
 *     plans?: array<int, array{member: string, phase?: int|string|null, start: string, end: string, daily: string}>,
 * }
 */
class CreateProjectAction
{
    public function __construct(
        protected AuditLogger $audit,
        protected SaveProjectAction $saveProject,
        protected SetPhaseFeeAction $setPhaseFee,
        protected SetProjectStatusAction $setStatus,
        protected SetProjectRoleAction $setRole,
        protected AssignProjectAction $assign,
    ) {}

    /**
     * Phases, budgets and plans refer to each other by the phase's position in
     * the phases list.
     *
     * @param  Setup  $setup
     */
    public function execute(array $setup): Project
    {
        $this->check($setup);

        return DB::transaction(function () use ($setup) {
            $project = $this->saveProject->execute([
                'job_number' => $setup['job_number'],
                'job_name' => $setup['job_name'],
                'developer' => $setup['developer'] ?? null,
                'division' => $setup['division'] ?? null,
                'project_lead' => $setup['lead'] ?? null,
                'ca_per_month' => $setup['ca_per_month'] ?? null,
                'ifc_date' => $setup['ifc_date'] ?? null,
                'notes' => $setup['notes'] ?? null,
                'active' => ($setup['status'] ?? 'active') !== 'closed',
            ]);

            $jobNumber = $project->job_number;

            if (($setup['status'] ?? 'active') === 'paused') {
                $this->setStatus->execute($project, 'paused');
            }

            $phases = $this->createPhases($project, $setup['phases']);
            $this->setRates($jobNumber, $setup['rates'] ?? []);
            $this->setTeam($jobNumber, $setup);
            $this->createBudgets($jobNumber, $phases, $setup['budgets'] ?? []);
            $this->createPlans($jobNumber, $phases, $setup['plans'] ?? []);

            return $project;
        });
    }

    /**
     * @param  array<int, array{name: string, code: string, fee?: ?string, hours?: ?string, start?: ?string, end?: ?string}>  $rows
     * @return array<int, ProjectPhase> by the row's position
     */
    protected function createPhases(Project $project, array $rows): array
    {
        $phases = [];
        $feesByCode = [];

        foreach (array_values($rows) as $seq => $row) {
            $hourly = $row['code'] === Phase::Hours;
            $fee = $hourly ? null : $this->blankToNull($row['fee'] ?? null);

            $phases[$seq] = ProjectPhase::create([
                'job_number' => $project->job_number,
                'seq' => $seq,
                'name' => trim($row['name']),
                'billing_code' => $row['code'],
                'fee' => $fee,
                'estimated_hours' => $this->blankToNull($row['hours'] ?? null),
                'start_date' => $this->blankToNull($row['start'] ?? null),
                'end_date' => $this->blankToNull($row['end'] ?? null),
            ]);

            if (! $hourly) {
                $feesByCode[$row['code']] = Money::add($feesByCode[$row['code']] ?? '0', $fee ?? '0');
            }
        }

        foreach ($feesByCode as $code => $fee) {
            if (Money::compare($fee, '0') > 0) {
                $this->setPhaseFee->execute($project, $code, $fee);
            }
        }

        $this->audit->log('insert', 'project_phases', $project->job_number, after: [
            'phases' => array_map(fn (ProjectPhase $phase) => $phase->only(['name', 'billing_code', 'fee', 'estimated_hours', 'start_date', 'end_date']), $phases),
        ]);

        return $phases;
    }

    /** @param array<string, ?string> $rates */
    protected function setRates(string $jobNumber, array $rates): void
    {
        $standard = RateSchedules::standard();
        $overrides = collect($rates)
            ->only(RateSchedules::Titles)
            ->map(fn (?string $rate) => $this->blankToNull($rate))
            ->filter(fn (?string $rate, string $title) => $rate !== null && (float) $rate !== (float) ($standard[$title] ?? 0));

        if ($overrides->isEmpty()) {
            return;
        }

        DB::table('project_rate_overrides')->insert(
            $overrides->map(fn (string $rate, string $title) => ['job_number' => $jobNumber, 'rate_title' => $title, 'rate' => $rate])->values()->all(),
        );

        $this->audit->log('insert', 'project_rate_overrides', $jobNumber, after: $overrides->all());
    }

    /** @param Setup $setup */
    protected function setTeam(string $jobNumber, array $setup): void
    {
        $lead = $setup['lead'] ?? null;
        $pm = $setup['pm'] ?? null;

        if ($lead) {
            $this->setRole->execute($jobNumber, ProjectRole::Lead, $lead);
        }

        if ($pm) {
            $this->setRole->execute($jobNumber, ProjectRole::ProjectManager, $pm);
        }

        $team = collect($setup['team'] ?? [])->keyBy('member');

        if ($pm && ! $team->has($pm)) {
            $team->prepend(['member' => $pm, 'position' => 'Project Manager'], $pm);
        }

        foreach ($team as $member => $row) {
            $this->assign->execute($jobNumber, $member, $member === $pm ? 'pm' : 'member', $this->blankToNull($row['position'] ?? null));
        }
    }

    /**
     * @param  array<int, ProjectPhase>  $phases
     * @param  array<int, array<string, ?string>>  $budgets
     */
    protected function createBudgets(string $jobNumber, array $phases, array $budgets): void
    {
        $created = [];

        foreach ($budgets as $index => $byMember) {
            foreach ($byMember as $member => $hours) {
                if ($this->blankToNull($hours) === null || (float) $hours <= 0) {
                    continue;
                }

                $created[] = ProjectHourBudget::create([
                    'project_phase_id' => $phases[$index]->id,
                    'member_name' => $member,
                    'hours' => $hours,
                ])->only(['project_phase_id', 'member_name', 'hours']);
            }
        }

        if ($created) {
            $this->audit->log('insert', 'project_hour_budgets', $jobNumber, after: ['budgets' => $created]);
        }
    }

    /**
     * @param  array<int, ProjectPhase>  $phases
     * @param  array<int, array{member: string, phase?: int|string|null, start: string, end: string, daily: string}>  $plans
     */
    protected function createPlans(string $jobNumber, array $phases, array $plans): void
    {
        $created = [];

        foreach ($plans as $plan) {
            $phase = $this->blankToNull((string) ($plan['phase'] ?? ''));

            $created[] = ProjectWorkPlan::create([
                'job_number' => $jobNumber,
                'project_phase_id' => $phase === null ? null : $phases[(int) $phase]->id,
                'member_name' => $plan['member'],
                'start_date' => $plan['start'],
                'end_date' => $plan['end'],
                'daily_hours' => $plan['daily'],
            ])->only(['project_phase_id', 'member_name', 'start_date', 'end_date', 'daily_hours']);
        }

        if ($created) {
            $this->audit->log('insert', 'project_work_plans', $jobNumber, after: ['plans' => $created]);
        }
    }

    /**
     * What the form validates, checked again here so the action cannot be
     * handed a set-up that breaks billing or names people who are not on the
     * team.
     *
     * @param  Setup  $setup
     */
    protected function check(array $setup): void
    {
        $errors = [];
        $phases = array_values($setup['phases']);
        $members = TeamMember::active()->pluck('name')->all();

        if ($phases === []) {
            $errors['phases'] = __('A project needs at least one phase.');
        }

        foreach ($phases as $index => $phase) {
            if (trim($phase['name']) === '') {
                $errors["phases.{$index}.name"] = __('Give the phase a name.');
            }

            if (! in_array($phase['code'], ProjectTemplates::billingCodes(), true)) {
                $errors["phases.{$index}.code"] = __('Pick the phase it bills under.');
            }

            if (($phase['start'] ?? null) && ($phase['end'] ?? null) && $phase['end'] < $phase['start']) {
                $errors["phases.{$index}.end"] = __('Ends before it starts.');
            }
        }

        $people = array_filter([
            'lead' => $setup['lead'] ?? null,
            'pm' => $setup['pm'] ?? null,
            ...collect($setup['team'] ?? [])->mapWithKeys(fn (array $row, int $index) => ["team.{$index}.member" => $row['member']])->all(),
            ...collect($setup['plans'] ?? [])->mapWithKeys(fn (array $row, int $index) => ["plans.{$index}.member" => $row['member']])->all(),
        ]);

        foreach ($people as $field => $member) {
            if (! in_array($member, $members, true)) {
                $errors[$field] = __('unknown team member :name', ['name' => $member]);
            }
        }

        foreach ($setup['budgets'] ?? [] as $index => $byMember) {
            if (! isset($phases[$index])) {
                $errors['budgets'] = __('An hours budget names a phase the project does not have.');
            }

            foreach (array_keys($byMember) as $member) {
                if (! in_array($member, $members, true)) {
                    $errors['budgets'] = __('unknown team member :name', ['name' => $member]);
                }
            }
        }

        foreach ($setup['plans'] ?? [] as $index => $plan) {
            $phase = $plan['phase'] ?? null;

            if ($phase !== null && $phase !== '' && ! isset($phases[(int) $phase])) {
                $errors["plans.{$index}.phase"] = __('Pick one of the project’s phases.');
            }

            if ($plan['end'] < $plan['start']) {
                $errors["plans.{$index}.end"] = __('Ends before it starts.');
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function blankToNull(?string $value): ?string
    {
        $value = trim(str_replace([',', '$'], '', (string) $value));

        return $value === '' ? null : $value;
    }
}
