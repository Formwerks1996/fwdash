<?php

namespace App\Livewire\Team;

use App\Actions\Team\AssignProjectAction;
use App\Actions\Team\SavePmTaskAction;
use App\Billing\ProjectBurn;
use App\Enums\Permission;
use App\Models\LineupWeek;
use App\Models\PmTask;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\TeamMember;
use App\Support\LineupTree;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One person's week: where they sit on this week's lineup, their team, and
 * each of their projects with how far along it is. "Their projects" are the
 * ones assigned to them, the ones they or their team are tagged to this week,
 * and for a partner the active projects they lead.
 *
 * @property-read array<string, TeamMember> $people
 * @property-read TeamMember $me
 * @property-read LineupTree $tree
 * @property-read array<string, string> $tags
 * @property-read Collection<int, DeskProject> $projects
 * @property-read array<string, array<int, PmTask>> $tasks
 *
 * @phpstan-import-type Burn from ProjectBurn
 *
 * @phpstan-type DeskProject array{burn: Burn, assigned: bool, taggedBy: array<int, string>, hoursLastMonth: float, hoursRecent: float}
 */
#[Title('PM Desk')]
class PmDesk extends Component
{
    protected const LevelOrder = ['over' => 0, 'watch' => 1, 'ok' => 2, 'idle' => 3, 'none' => 4];

    public string $member = '';

    public string $projectSearch = '';

    /** @var array<string, array{title?: string, assignee?: string, due_on?: string}> keyed by taskKey() */
    public array $newTask = [];

    public function mount(?string $member = null): void
    {
        $people = $this->people;

        if ($member !== null) {
            abort_unless(isset($people[$member]), 404);

            $this->member = $member;

            return;
        }

        $email = Str::lower((string) auth()->user()?->email);
        $default = collect($people)->first(fn (TeamMember $person) => $person->email !== null && $person->email === $email)
            ?? collect($people)->first(fn (TeamMember $person) => $person->isPm())
            ?? collect($people)->first();

        abort_unless($default !== null, 404, __('No team members yet. Add people in the Team Lineup roster.'));

        $this->member = $default->name;
    }

    /** @return array<string, TeamMember> */
    #[Computed]
    public function people(): array
    {
        return LineupTree::roster();
    }

    #[Computed]
    public function me(): TeamMember
    {
        return $this->people[$this->member];
    }

    #[Computed]
    public function tree(): LineupTree
    {
        return LineupTree::forWeek(LineupWeek::find(TeamLineup::thisMonday()), $this->people);
    }

    /** @return array<string, string> */
    #[Computed]
    public function tags(): array
    {
        return $this->tree->keepTags(LineupWeek::find(TeamLineup::thisMonday())?->tags);
    }

    /** @return array<int, string> direct reports this week */
    public function team(): array
    {
        return $this->tree->childrenOf($this->member);
    }

    /** @return Collection<int, DeskProject> */
    #[Computed]
    public function projects(): Collection
    {
        $burn = app(ProjectBurn::class);
        $asOf = $burn->asOf();
        $from = $burn->windowStart(3);
        $team = $this->team();

        $assigned = ProjectAssignment::where('member_name', $this->member)->pluck('job_number')->all();

        /** @var array<string, array<int, string>> $taggedBy job => the people tagged to it */
        $taggedBy = [];

        foreach ([$this->member, ...$team] as $name) {
            if (isset($this->tags[$name])) {
                $taggedBy[$this->tags[$name]][] = $name;
            }
        }

        $led = $this->me->isPartner()
            ? collect($burn->engine()->jobs())
                ->filter(fn (array $job, string $number) => $job['project_lead'] === $this->member && $burn->effectiveStatus($number) === 'active')
                ->keys()
                ->all()
            : [];

        return collect([...$assigned, ...array_keys($taggedBy), ...$led])
            ->unique()
            ->filter(fn (string $job) => $burn->engine()->job($job) !== null)
            ->map(fn (string $job) => [
                'burn' => $burn->for($job),
                'assigned' => in_array($job, $assigned, true),
                'taggedBy' => $taggedBy[$job] ?? [],
                'hoursLastMonth' => $asOf ? $burn->engine()->hoursIn($job, $asOf) : 0.0,
                'hoursRecent' => $burn->hoursSince($job, $from),
            ])
            ->sortBy(fn (array $row) => [self::LevelOrder[$row['burn']['level']] ?? 9, -($row['burn']['used'] ?? 0), $row['burn']['jobNumber']])
            ->values();
    }

    /** @return array<string, array<int, PmTask>> open first, then by due date */
    #[Computed]
    public function tasks(): array
    {
        $tasks = PmTask::whereIn('job_number', $this->projects->pluck('burn.jobNumber'))
            ->orderByRaw('done_at is not null')
            ->orderByRaw('due_on is null')
            ->orderBy('due_on')
            ->orderBy('id')
            ->get();

        $grouped = [];

        foreach ($tasks as $task) {
            $grouped[$task->job_number][] = $task;
        }

        return $grouped;
    }

    public function switchTo(string $name): void
    {
        abort_unless(isset($this->people[$name]), 404);

        $this->redirectRoute('pm-desk', $name, navigate: true);
    }

    public function claim(string $jobNumber, AssignProjectAction $assign): void
    {
        $this->authorize(Permission::EditTeam->value);

        $this->resetValidation();
        $assign->execute($jobNumber, $this->member);

        $this->projectSearch = '';
        unset($this->projects, $this->tasks);
    }

    /** "Make these mine": every project this person or their team is tagged to this week. */
    public function claimTagged(AssignProjectAction $assign): void
    {
        $this->authorize(Permission::EditTeam->value);

        $this->projects
            ->reject(fn (array $row) => $row['assigned'])
            ->filter(fn (array $row) => $row['taggedBy'] !== [])
            ->each(fn (array $row) => $assign->execute($row['burn']['jobNumber'], $this->member));

        unset($this->projects, $this->tasks);
    }

    public function release(string $jobNumber, AssignProjectAction $assign): void
    {
        $this->authorize(Permission::EditTeam->value);

        $assign->release($jobNumber, $this->member);

        unset($this->projects, $this->tasks);
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
        $tree = $this->tree;
        $boss = $tree->parentOf($this->member);
        $top = $tree->rootOf($this->member);
        $search = Str::lower(trim($this->projectSearch));
        $mine = $this->projects->pluck('burn.jobNumber')->all();

        return view('livewire.team.pm-desk', [
            'monday' => CarbonImmutable::parse(TeamLineup::thisMonday()),
            'boss' => $boss,
            'chainTop' => $top !== $boss && $tree->isPartner($top) && $top !== $this->member ? $top : null,
            'team' => $this->team(),
            'asOf' => $burn->asOf(),
            'rate' => $burn->planRate(),
            'canEdit' => auth()->user()->can(Permission::EditTeam->value),
            'pms' => collect($this->people)->filter(fn (TeamMember $person) => $person->isPm()),
            'staff' => collect($this->people)->filter(fn (TeamMember $person) => $person->tier === 'staff'),
            'untaggedClaims' => $this->projects->filter(fn (array $row) => ! $row['assigned'] && $row['taggedBy'] !== [])->count(),
            'matches' => $search === ''
                ? collect()
                : Project::orderBy('job_number')
                    ->get(['job_number', 'job_name'])
                    ->reject(fn (Project $project) => in_array($project->job_number, $mine, true))
                    ->filter(fn (Project $project) => Str::contains(Str::lower("{$project->job_number} {$project->job_name}"), $search))
                    ->take(8),
        ]);
    }
}
