<?php

namespace App\Livewire\Team;

use App\Models\LineupWeek;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\TeamMember;
use App\Support\LineupTree;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The signed-in person's week, read from the Team Lineup: who they go to and
 * the projects they are on, each with the people it sits under. Read-only;
 * the lineup and the PM Desk are where these are set.
 *
 * @property-read ?TeamMember $me
 * @property-read array<string, TeamMember> $people
 * @property-read LineupTree $tree
 * @property-read Collection<int, array{project: Project, thisWeek: bool, assigned: bool, pms: array<int, string>, partner: ?string, with: array<int, string>}> $projects
 */
#[Title('My Week')]
class MyWeek extends Component
{
    #[Url]
    public string $week = '';

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
     * they are assigned to on the PM Desk.
     *
     * @return Collection<int, array{project: Project, thisWeek: bool, assigned: bool, pms: array<int, string>, partner: ?string, with: array<int, string>}>
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
        $assigned = ProjectAssignment::where('member_name', $name)->pluck('job_number')->all();
        $jobNumbers = collect([$tagged, ...$assigned])->filter()->unique()->values();

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
                'assigned' => in_array($project->job_number, $assigned, true),
                'pms' => $this->pmsFor($project->job_number, $tagged, $pmsByJob),
                'partner' => $project->project_lead ? $this->displayName($project->project_lead) : null,
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

        unset($this->lineup, $this->tree, $this->projects);
    }

    /**
     * PMs on the PM Desk for the job; for the project tagged this week with
     * none set, the person this one goes to in the lineup.
     *
     * @param  Collection<string, Collection<int, ProjectAssignment>>  $pmsByJob
     * @return array<int, string>
     */
    protected function pmsFor(string $jobNumber, ?string $tagged, Collection $pmsByJob): array
    {
        $pms = ($pmsByJob[$jobNumber] ?? collect())->pluck('member_name');

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
