<?php

namespace App\Livewire\Projects;

use App\Actions\Billing\SetProjectStatusAction;
use App\Actions\Team\SavePmTaskAction;
use App\Billing\BillingEngine;
use App\Billing\ProjectBurn;
use App\Enums\Permission;
use App\Enums\Role;
use App\Livewire\Concerns\SortsRows;
use App\Models\PmTask;
use App\Models\Project;
use App\Models\ProjectRole;
use App\Models\ProjectStatus;
use App\Models\TeamMember;
use App\Support\LineupTree;
use App\Support\RateBand;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Every project the viewer may see, as a sortable list or as cards that show
 * how far through its fee each one is, with its phases and tasks.
 *
 * @property-read Collection<int, array<string, mixed>> $projects
 * @property-read Collection<int, array<string, mixed>> $rows
 * @property-read array<string, mixed> $rates
 * @property-read array<string, TeamMember> $people
 * @property-read array<string, Burn> $burns
 * @property-read array<string, array<int, PmTask>> $tasks
 *
 * @phpstan-import-type Burn from ProjectBurn
 */
#[Title('Projects')]
class ProjectsIndex extends Component
{
    use SortsRows;

    /** Status filter groups: "open" is anything not closed. */
    protected const Groups = ['active', 'paused', 'open', 'closed', 'all'];

    #[Url]
    public string $search = '';

    #[Url]
    public string $group = 'active';

    #[Url]
    public string $division = '';

    #[Url(as: 'rates')]
    public string $rateScope = 'ongoing';

    /** Managers start on their own projects and can switch to every project. */
    #[Url(as: 'show')]
    public string $scope = 'mine';

    /** "list" or "cards". */
    #[Url]
    public string $view = 'list';

    /** One person's projects, by roster name; managers and admins only. */
    #[Url]
    public string $person = '';

    public string $sort = 'lastActivity';

    public string $direction = 'desc';

    /** @var array<string, array{title?: string, assignee?: string, due_on?: string}> keyed by taskKey() */
    public array $newTask = [];

    /** @var array<int, string> */
    protected array $textColumns = ['jobName', 'developer', 'division', 'leads', 'pms', 'status'];

    /** @return Collection<int, non-empty-array<string, mixed>> */
    #[Computed]
    public function projects(): Collection
    {
        $engine = app(BillingEngine::class);
        $overrides = ProjectStatus::all()->keyBy('job_number');

        $hourPeriods = collect($engine->hoursByPeriod())->keys();
        $latest = $hourPeriods->last();
        $lastThree = $hourPeriods->slice(-3)->all();

        $lastBilled = collect($engine->billedByJobPeriod())
            ->reject(fn (string $amount) => (float) $amount == 0)
            ->keys()
            ->map(fn (string $key) => explode('|', $key, 2))
            ->groupBy(fn (array $key) => $key[1])
            ->map(fn (Collection $keys) => $keys->max(fn (array $key) => $key[0]));

        $visible = $this->personMember()?->jobNumbers() ?? ($this->showsAll() ? null : auth()->user()->ownJobNumbers());
        $roles = ProjectRole::byJob($visible);

        return collect($engine->allSummaries())
            ->when($visible !== null, fn (Collection $rows) => $rows->filter(fn (array $row) => in_array($row['jobNumber'], $visible, true)))
            ->map(function (array $row) use ($engine, $overrides, $latest, $lastThree, $lastBilled, $roles) {
                $override = $overrides[$row['jobNumber']] ?? null;
                $hours = $engine->hoursByPeriod($row['jobNumber']);

                return [
                    ...$row,
                    'leads' => implode(', ', $roles[$row['jobNumber']][ProjectRole::Lead] ?? []),
                    'pms' => implode(', ', $roles[$row['jobNumber']][ProjectRole::ProjectManager] ?? []),
                    'status' => $override->status ?? ($row['active'] ? 'active' : 'closed'),
                    'statusOverridden' => (bool) $override,
                    'hoursLatest' => $latest ? ($hours[$latest] ?? null) : null,
                    'hoursRecent' => array_sum(array_intersect_key($hours, array_flip($lastThree))) ?: null,
                    'hoursTotal' => $row['hoursTotal'],
                    'billed' => (float) $row['billed'],
                    'lastActivity' => collect([$lastBilled[$row['jobNumber']] ?? null, array_key_last($hours)])->filter()->max(),
                ];
            })
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function rows(): Collection
    {
        $search = Str::lower(trim($this->search));

        $rows = $this->projects
            ->filter(fn (array $row) => match ($this->group) {
                'open' => $row['status'] !== 'closed',
                'all' => true,
                default => $row['status'] === $this->group,
            })
            ->when($this->division !== '', fn (Collection $rows) => $rows->where('division', $this->division))
            ->when($search !== '', fn (Collection $rows) => $rows->filter(
                fn (array $row) => Str::contains(Str::lower(implode(' ', [$row['jobName'], $row['jobNumber'], $row['developer'], $row['lead'], $row['leads'], $row['pms']])), $search),
            ));

        return $this->sortRows($rows);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function rates(): array
    {
        $rows = $this->projects
            ->filter(fn (array $row) => match ($this->rateScope) {
                'complete' => ! $row['active'],
                'all' => true,
                default => $row['active'],
            })
            ->when($this->division !== '', fn (Collection $rows) => $rows->where('division', $this->division))
            ->map(fn (array $row) => [...$row, 'bucket' => RateBand::bucket($row['ratePerHour'], $row['hoursTotal'])]);

        $measurable = $rows->where('bucket', '!=', 'na');
        $hours = $measurable->sum('hoursTotal');
        $blended = $hours ? $measurable->sum(fn (array $row) => $row['billed'] - (float) $row['consultantTotal']) / $hours : null;

        return [
            'count' => $rows->count(),
            'measurable' => $measurable->count(),
            'blended' => $blended,
            'blendedHours' => $hours,
            'billed' => $rows->sum('billed'),
            'hours' => $rows->sum('hoursTotal'),
            'buckets' => collect(array_keys(RateBand::Bands))
                ->push('na')
                ->mapWithKeys(fn (string $key) => [$key => [
                    'count' => $rows->where('bucket', $key)->count(),
                    'billed' => $rows->where('bucket', $key)->sum('billed'),
                ]])
                ->all(),
        ];
    }

    /** @return array<string, TeamMember> */
    #[Computed]
    public function people(): array
    {
        return LineupTree::roster();
    }

    /** @return array<string, Burn> fee burn for the cards on show */
    #[Computed]
    public function burns(): array
    {
        $burn = app(ProjectBurn::class);

        return $this->rows->mapWithKeys(fn (array $row) => [$row['jobNumber'] => $burn->for($row['jobNumber'])])->all();
    }

    /** @return array<string, array<int, PmTask>> open first, then by due date */
    #[Computed]
    public function tasks(): array
    {
        return PmTask::whereIn('job_number', $this->rows->pluck('jobNumber'))
            ->orderByRaw('done_at is not null')
            ->orderByRaw('due_on is null')
            ->orderBy('due_on')
            ->orderBy('id')
            ->get()
            ->groupBy('job_number')
            ->map(fn (Collection $tasks) => $tasks->all())
            ->all();
    }

    /** Admins always see every project; managers may switch to all; staff only ever see their own. */
    public function showsAll(): bool
    {
        return match (auth()->user()->effectiveRole()) {
            Role::Admin => true,
            Role::Manager => $this->scope === 'all',
            Role::Staff => false,
        };
    }

    public function canPickPerson(): bool
    {
        return auth()->user()->effectiveRole() !== Role::Staff;
    }

    /** The person picked, when the viewer may pick one and the name is on the roster. */
    public function personMember(): ?TeamMember
    {
        return $this->person !== '' && $this->canPickPerson() ? ($this->people[$this->person] ?? null) : null;
    }

    public function setScope(string $scope): void
    {
        $this->scope = $scope === 'all' ? 'all' : 'mine';
        $this->person = '';

        unset($this->projects, $this->rows, $this->rates);
    }

    /** The card view's sort picker: text runs A to Z, figures largest first. */
    public function updatedSort(): void
    {
        $this->direction = in_array($this->sort, $this->textColumns, true) ? 'asc' : 'desc';
    }

    public function updatedPerson(): void
    {
        unset($this->projects, $this->rows, $this->rates);
    }

    public function setStatus(string $jobNumber, string $status, SetProjectStatusAction $setStatus): void
    {
        $this->authorize(Permission::EditBilling->value);

        abort_unless(in_array($status, ProjectStatus::Statuses, true), 422);

        $setStatus->execute(Project::findOrFail($jobNumber), $status);

        unset($this->projects);
    }

    public function addTask(string $jobNumber, SavePmTaskAction $saveTask): void
    {
        $this->authorize(Permission::EditTeam->value);

        $key = self::taskKey($jobNumber);
        $draft = $this->newTask[$key] ?? [];

        $this->resetValidation();
        $saveTask->add($jobNumber, (string) ($draft['title'] ?? ''), ($draft['assignee'] ?? '') ?: null, ($draft['due_on'] ?? '') ?: null);

        unset($this->newTask[$key], $this->tasks);
    }

    public function toggleTask(int $id, SavePmTaskAction $saveTask): void
    {
        $this->authorize(Permission::EditTeam->value);

        $saveTask->toggle(PmTask::findOrFail($id));

        unset($this->tasks);
    }

    public function deleteTask(int $id, SavePmTaskAction $saveTask): void
    {
        $this->authorize(Permission::EditTeam->value);

        $saveTask->delete(PmTask::findOrFail($id));

        unset($this->tasks);
    }

    public static function taskKey(string $jobNumber): string
    {
        return 'job'.preg_replace('/[^A-Za-z0-9]/', '', $jobNumber);
    }

    public function render(): View
    {
        $burn = app(ProjectBurn::class);

        return view('livewire.projects.index', [
            'canSwitchScope' => auth()->user()->effectiveRole() === Role::Manager,
            'canPickPerson' => $this->canPickPerson(),
            'picked' => $this->personMember(),
            'asOf' => $burn->asOf(),
            'planRate' => $burn->planRate(),
            'groups' => self::Groups,
            'divisions' => $this->projects->pluck('division')->filter()->unique()->sort()->values(),
            'statuses' => ProjectStatus::Statuses,
        ]);
    }
}
