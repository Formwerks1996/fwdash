<?php

namespace App\Livewire\Projects;

use App\Actions\Billing\SaveProjectAction;
use App\Actions\Billing\SetProjectStatusAction;
use App\Billing\BillingEngine;
use App\Enums\Permission;
use App\Livewire\Concerns\SortsRows;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Support\RateBand;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read Collection<int, array<string, mixed>> $projects
 * @property-read Collection<int, array<string, mixed>> $rows
 * @property-read array<string, mixed> $rates
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

    public string $sort = 'lastActivity';

    public string $direction = 'desc';

    public bool $adding = false;

    /** @var array<string, mixed> */
    public array $form = [
        'job_number' => '',
        'job_name' => '',
        'developer' => '',
        'division' => '',
        'project_lead' => '',
        'ca_per_month' => '',
        'active' => true,
    ];

    /** @var array<int, string> */
    protected array $textColumns = ['jobName', 'developer', 'division', 'status'];

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

        $visible = auth()->user()->visibleJobNumbers();

        return collect($engine->allSummaries())
            ->when($visible !== null, fn (Collection $rows) => $rows->filter(fn (array $row) => in_array($row['jobNumber'], $visible, true)))
            ->map(function (array $row) use ($engine, $overrides, $latest, $lastThree, $lastBilled) {
                $override = $overrides[$row['jobNumber']] ?? null;
                $hours = $engine->hoursByPeriod($row['jobNumber']);

                return [
                    ...$row,
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
                fn (array $row) => Str::contains(Str::lower(implode(' ', [$row['jobName'], $row['jobNumber'], $row['developer'], $row['lead']])), $search),
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

    public function setStatus(string $jobNumber, string $status, SetProjectStatusAction $setStatus): void
    {
        $this->authorize(Permission::EditBilling->value);

        abort_unless(in_array($status, ProjectStatus::Statuses, true), 422);

        $setStatus->execute(Project::findOrFail($jobNumber), $status);

        unset($this->projects);
    }

    public function addProject(SaveProjectAction $saveProject): void
    {
        $this->authorize(Permission::EditBilling->value);

        $validated = $this->validate([
            'form.job_number' => ['required', 'string', 'max:40'],
            'form.job_name' => ['required', 'string', 'max:255'],
            'form.developer' => ['nullable', 'string', 'max:255'],
            'form.division' => ['nullable', 'string', 'max:20'],
            'form.project_lead' => ['nullable', 'string', 'max:255'],
            'form.ca_per_month' => ['nullable', 'numeric', 'min:0'],
            'form.active' => ['boolean'],
        ], attributes: [
            'form.job_number' => __('job number'),
            'form.job_name' => __('project name'),
        ])['form'];

        $project = $saveProject->execute($validated);

        $this->redirectRoute('projects.show', $project->job_number, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.projects.index', [
            'groups' => self::Groups,
            'divisions' => $this->projects->pluck('division')->filter()->unique()->sort()->values(),
            'statuses' => ProjectStatus::Statuses,
        ]);
    }
}
