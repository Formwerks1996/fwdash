<?php

namespace App\Livewire\Team;

use App\Actions\Team\UpdateLineupWeekAction;
use App\Billing\ProjectBurn;
use App\Models\LineupWeek;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\ProjectRole;
use App\Models\TeamMember;
use App\Support\LineupTree;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The signed-in person's week, read from the Team Lineup: who they go to and
 * the projects they are on, each with the people it sits under. Read-only;
 * the lineup and the project page are where these are set.
 *
 * @property-read ?TeamMember $me
 * @property-read array<string, TeamMember> $people
 * @property-read LineupTree $tree
 * @property-read Collection<int, array{project: Project, thisWeek: bool, assigned: bool, managing: bool, pms: array<int, string>, partner: ?string, with: array<int, string>}> $projects
 * @property-read array{help: array<int, array{name: string, display: string, note: ?string, project: ?Project, boss: ?string}>, available: array<int, array{name: string, display: string, note: ?string, project: ?Project, boss: ?string}>} $board
 */
#[Title('My Week')]
class MyWeek extends Component
{
    #[Url]
    public string $week = '';

    public string $statusNote = '';

    public ?string $statusError = null;

    public function mount(): void
    {
        $this->week = $this->monday($this->week ?: TeamLineup::thisMonday());
    }

    #[Computed]
    public function me(): ?TeamMember
    {
        $member = auth()->user()->teamMember();

        return $member !== null && isset($this->people[$member->name]) ? $member : null;
    }

    /** @return array<string, TeamMember> */
    #[Computed]
    public function people(): array
    {
        return LineupTree::roster();
    }

    #[Computed]
    public function lineup(): ?LineupWeek
    {
        return LineupWeek::find($this->week);
    }

    #[Computed]
    public function tree(): LineupTree
    {
        return LineupTree::forWeek($this->lineup, $this->people);
    }

    /**
     * The project the lineup tags this person with this week, then the ones
     * they are assigned to and the active ones they manage.
     *
     * @return Collection<int, array{project: Project, thisWeek: bool, assigned: bool, managing: bool, pms: array<int, string>, partner: ?string, with: array<int, string>}>
     */
    #[Computed]
    public function projects(): Collection
    {
        if ($this->me === null) {
            return collect();
        }

        $name = $this->me->name;
        $tags = $this->tree->keepTags($this->lineup?->tags);
        $tagged = $tags[$name] ?? null;
        $burn = app(ProjectBurn::class);
        $managed = ProjectRole::where(['member_name' => $name, 'role' => ProjectRole::ProjectManager])
            ->pluck('job_number')
            ->filter(fn (string $job) => $burn->effectiveStatus($job) === 'active')
            ->all();
        $assigned = ProjectAssignment::where('member_name', $name)->pluck('job_number')->all();
        $jobNumbers = collect([$tagged, ...$assigned, ...$managed])->filter()->unique()->values();
        $roles = ProjectRole::byJob($jobNumbers->all());

        $pmsByJob = ProjectAssignment::whereIn('job_number', $jobNumbers)
            ->where('role', 'pm')
            ->where('member_name', '!=', $name)
            ->get()
            ->groupBy('job_number');

        return Project::whereIn('job_number', $jobNumbers)
            ->get()
            ->map(fn (Project $project) => [
                'project' => $project,
                'thisWeek' => $project->job_number === $tagged,
                'assigned' => in_array($project->job_number, [...$assigned, ...$managed], true),
                'managing' => in_array($project->job_number, $managed, true),
                'pms' => $this->pmsFor($project->job_number, $tagged, $pmsByJob, $roles),
                'partner' => ($lead = $roles[$project->job_number][ProjectRole::Lead][0] ?? $project->project_lead) ? $this->displayName($lead) : null,
                'with' => collect($tags)
                    ->filter(fn (string $job, string $person) => $job === $project->job_number && $person !== $name)
                    ->keys()
                    ->map(fn (string $person) => $this->displayName($person))
                    ->values()
                    ->all(),
            ])
            ->sortBy(fn (array $row) => [! $row['thisWeek'], $row['project']->job_number])
            ->values();
    }

    /**
     * The community board: everyone flagged on the lineup this week as
     * needing help or as available to help, in roster order.
     *
     * @return array{help: array<int, array{name: string, display: string, note: ?string, project: ?Project, boss: ?string}>, available: array<int, array{name: string, display: string, note: ?string, project: ?Project, boss: ?string}>}
     */
    #[Computed]
    public function board(): array
    {
        $badges = $this->tree->keepBadges($this->lineup?->badges);
        $tags = $this->tree->keepTags($this->lineup?->tags);
        $projects = Project::whereIn('job_number', array_values($tags))->get()->keyBy('job_number');
        $board = ['help' => [], 'available' => []];

        foreach ($this->people as $name => $member) {
            if (! isset($badges[$name])) {
                continue;
            }

            $boss = $this->tree->parentOf($name);
            $board[$badges[$name]['type']][] = [
                'name' => $name,
                'display' => $member->displayName(),
                'note' => $badges[$name]['note'] ?? null,
                'project' => isset($tags[$name]) ? $projects->get($tags[$name]) : null,
                'boss' => $boss ? $this->displayName($boss) : null,
            ];
        }

        return $board;
    }

    /** Puts the signed-in person on the board as needing help or available, or takes them off it (null). */
    public function setMyStatus(?string $type, UpdateLineupWeekAction $lineup): void
    {
        if ($this->me === null || $this->me->isPartner()) {
            return;
        }

        try {
            $lineup->setBadge($this->week, $this->me->name, $type, $type ? $this->statusNote : null);
        } catch (ValidationException $exception) {
            $this->statusError = $exception->getMessage();

            return;
        }

        $this->statusError = null;
        $this->statusNote = '';

        unset($this->lineup, $this->tree, $this->board);
    }

    public function previousWeek(): void
    {
        $this->goToWeek(CarbonImmutable::parse($this->week)->subWeek()->toDateString());
    }

    public function nextWeek(): void
    {
        $this->goToWeek(CarbonImmutable::parse($this->week)->addWeek()->toDateString());
    }

    public function goToWeek(string $week): void
    {
        $this->week = $this->monday($week);

        unset($this->lineup, $this->tree, $this->projects, $this->board);
    }

    /**
     * The job's project manager and its PMs, other than this
     * person; for the project tagged this week with none set, the person this
     * one goes to in the lineup.
     *
     * @param  Collection<string, Collection<int, ProjectAssignment>>  $pmsByJob
     * @param  array<string, array{lead: array<int, string>, pm: array<int, string>}>  $roles
     * @return array<int, string>
     */
    protected function pmsFor(string $jobNumber, ?string $tagged, Collection $pmsByJob, array $roles): array
    {
        $pms = collect($roles[$jobNumber][ProjectRole::ProjectManager] ?? [])
            ->reject(fn (string $person) => $person === $this->me->name)
            ->merge(($pmsByJob[$jobNumber] ?? collect())->pluck('member_name'))
            ->unique()
            ->values();

        if ($pms->isEmpty() && $jobNumber === $tagged && ($boss = $this->tree->parentOf($this->me->name)) !== null) {
            $pms = collect([$boss]);
        }

        return $pms->map(fn (string $person) => $this->displayName($person))->all();
    }

    protected function displayName(string $name): string
    {
        return isset($this->people[$name]) ? $this->people[$name]->displayName() : $name;
    }

    protected function monday(string $date): string
    {
        return CarbonImmutable::parse($date)->startOfWeek()->toDateString();
    }

    public function render(): View
    {
        $me = $this->me;
        $boss = $me ? $this->tree->parentOf($me->name) : null;
        $top = $me ? $this->tree->rootOf($me->name) : null;

        return view('livewire.team.my-week', [
            'monday' => CarbonImmutable::parse($this->week),
            'isThisWeek' => $this->week === TeamLineup::thisMonday(),
            'boss' => $boss ? $this->displayName($boss) : null,
            'partner' => $top && $top !== $boss && $top !== $me?->name && $this->tree->isPartner($top) ? $this->displayName($top) : null,
        ]);
    }
}
