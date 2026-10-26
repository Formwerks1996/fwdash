<?php

namespace App\Livewire\Team;

use App\Actions\Team\SaveTeamMemberAction;
use App\Actions\Team\UpdateLineupWeekAction;
use App\Enums\Permission;
use App\Models\LineupWeek;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\ProjectStatus;
use App\Models\TeamMember;
use App\Support\LineupTree;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Who goes to whom, week by week. The tree is drawn here; dragging, the
 * project picker, badges menu and toast live in the page's script, which calls
 * the actions below. Every action re-reads the week, so a stale screen can
 * never overwrite someone else's change.
 *
 * @property-read array<string, TeamMember> $people
 * @property-read ?LineupWeek $saved
 * @property-read LineupTree $tree
 * @property-read Collection<string, array{number: string, title: string, division: ?string, active: bool}> $projects
 * @property-read Collection<int, array{week: string, summary: array{sides: array<int, array{name: string, first: string, teams: int, people: int}>, loose: int}}> $history
 */
#[Title('Team Lineup')]
class TeamLineup extends Component
{
    /** Reference-sheet rows that are rate placeholders, not people. */
    public const Placeholders = ['Fixed', 'Junior', 'Intermediate', 'Senior', 'Principal', 'Technologist', 'Prior', "Andrew's"];

    protected const UndoDepth = 20;

    #[Url]
    public string $week = '';

    /** @var array<int, array<string, mixed>> */
    public array $undo = [];

    public string $status = '';

    public bool $statusIsError = false;

    public bool $confirmErase = false;

    public bool $editorOpen = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(UpdateLineupWeekAction $lineup): void
    {
        if ($this->week === '') {
            $this->week = self::thisMonday();

            return;
        }

        try {
            $lineup->monday($this->week);
        } catch (ValidationException $exception) {
            abort(400, $exception->getMessage());
        }
    }

    public static function thisMonday(): string
    {
        return CarbonImmutable::now(config('app.display_timezone'))->startOfWeek()->toDateString();
    }

    /** @return array<string, TeamMember> */
    #[Computed]
    public function people(): array
    {
        return LineupTree::roster();
    }

    #[Computed]
    public function saved(): ?LineupWeek
    {
        return LineupWeek::find($this->week);
    }

    #[Computed]
    public function tree(): LineupTree
    {
        return LineupTree::forWeek($this->saved, $this->people);
    }

    /** @return Collection<string, array{number: string, title: string, division: ?string, active: bool}> */
    #[Computed]
    public function projects(): Collection
    {
        $overrides = ProjectStatus::pluck('status', 'job_number');

        return Project::orderBy('job_number')
            ->get()
            ->mapWithKeys(fn (Project $project) => [$project->job_number => [
                'number' => $project->job_number,
                'title' => $project->job_name,
                'division' => $project->division,
                'active' => ($overrides[$project->job_number] ?? ($project->active ? 'active' : 'closed')) === 'active',
            ]]);
    }

    /** @return Collection<int, array{week: string, summary: array{sides: array<int, array{name: string, first: string, teams: int, people: int}>, loose: int}}> */
    #[Computed]
    public function history(): Collection
    {
        return LineupWeek::orderByDesc('week')
            ->get()
            ->map(fn (LineupWeek $week) => [
                'week' => $week->week,
                'summary' => LineupTree::forWeek($week, $this->people)->summary(),
            ]);
    }

    public function previousWeek(): void
    {
        $this->goToWeek(CarbonImmutable::parse($this->week)->subWeek()->toDateString());
    }

    public function nextWeek(): void
    {
        $this->goToWeek(CarbonImmutable::parse($this->week)->addWeek()->toDateString());
    }

    public function thisWeek(): void
    {
        $this->goToWeek(self::thisMonday());
    }

    public function goToWeek(string $week): void
    {
        $date = CarbonImmutable::parse($week)->startOfWeek()->toDateString();

        $this->week = $date;
        $this->undo = [];
        $this->confirmErase = false;
        $this->status = '';
        unset($this->saved, $this->tree);
    }

    public function move(string $name, string $drop, UpdateLineupWeekAction $lineup): void
    {
        $this->change(fn () => $lineup->move($this->week, $name, $drop));
    }

    public function setTag(string $name, ?string $jobNumber, UpdateLineupWeekAction $lineup): void
    {
        $this->change(fn () => $lineup->setTag($this->week, $name, $jobNumber));
    }

    public function setBadge(string $name, ?string $type, ?string $note, UpdateLineupWeekAction $lineup): void
    {
        $this->change(fn () => $lineup->setBadge($this->week, $name, $type, $note));
    }

    public function erase(UpdateLineupWeekAction $lineup): void
    {
        $this->confirmErase = false;
        $this->change(fn () => $lineup->erase($this->week));
    }

    public function copyLastWeek(UpdateLineupWeekAction $lineup): void
    {
        $this->change(fn () => $lineup->copyLastWeek($this->week));
    }

    public function undoLast(UpdateLineupWeekAction $lineup): void
    {
        $snapshot = array_pop($this->undo);

        if ($snapshot === null) {
            return;
        }

        $this->change(fn () => $lineup->restore($this->week, $snapshot), remember: false);
    }

    /**
     * The tag picker's list: this person's likely projects first, each with
     * why it is suggested, then every project (the page filters as you type).
     *
     * @return array{mine: array<int, array{number: string, note: string}>, all: array<int, array{number: string, title: string, division: ?string, active: bool}>}
     */
    #[Renderless]
    public function projectChoices(string $name): array
    {
        $person = $this->people[$name] ?? null;

        if (! $person) {
            return ['mine' => [], 'all' => []];
        }

        $first = $person->firstName();
        $mine = collect();

        foreach (ProjectAssignment::where('member_name', $name)->orderBy('job_number')->pluck('job_number') as $job) {
            $mine->push(['number' => $job, 'note' => __('assigned to :first', ['first' => $first])]);
        }

        LineupWeek::where('week', '<', $this->week)
            ->where('week', '>=', CarbonImmutable::parse($this->week)->subWeeks(8)->toDateString())
            ->orderByDesc('week')
            ->get()
            ->each(function (LineupWeek $week) use ($name, $mine) {
                $job = $week->tags[$name] ?? null;
                $weeksAgo = (int) round(CarbonImmutable::parse($week->week)->diffInWeeks(CarbonImmutable::parse($this->week)));

                if ($job) {
                    $mine->push(['number' => $job, 'note' => $weeksAgo === 1 ? __('tagged last week') : __('tagged :count weeks ago', ['count' => $weeksAgo])]);
                }
            });

        $boss = $this->tree->parentOf($name);
        $bossTag = $boss ? ($this->saved?->tags[$boss] ?? null) : null;

        if ($boss && $bossTag) {
            $mine->push(['number' => $bossTag, 'note' => __(":first's project", ['first' => $this->people[$boss]->firstName()])]);
        }

        if ($person->isPm()) {
            Project::where('project_lead', $name)
                ->pluck('job_number')
                ->filter(fn (string $job) => $this->projects[$job]['active'] ?? false)
                ->each(fn (string $job) => $mine->push(['number' => $job, 'note' => __('lead')]));
        }

        return [
            'mine' => $mine->filter(fn (array $row) => $this->projects->has($row['number']))->unique('number')->values()->all(),
            'all' => $this->projects->values()->all(),
        ];
    }

    public function addPerson(): void
    {
        $this->authorize(Permission::EditTeam->value);

        $this->editingId = null;
        $this->form = ['name' => '', 'full_name' => '', 'email' => '', 'tier' => 'staff', 'grp' => 'other', 'default_boss' => '', 'active' => true];
        $this->resetValidation();
        $this->editorOpen = true;
    }

    public function editPerson(int $id): void
    {
        $this->authorize(Permission::EditTeam->value);

        $member = TeamMember::findOrFail($id);

        $this->editingId = $id;
        $this->form = [
            'name' => $member->name,
            'full_name' => $member->full_name ?? '',
            'email' => $member->email ?? '',
            'tier' => $member->tier,
            'grp' => $member->grp ?? 'other',
            'default_boss' => $member->default_boss ?? '',
            'active' => $member->active,
        ];
        $this->resetValidation();
        $this->editorOpen = true;
    }

    public function savePerson(SaveTeamMemberAction $saveMember): void
    {
        $this->authorize(Permission::EditTeam->value);

        $saveMember->execute($this->form, $this->editingId ? TeamMember::findOrFail($this->editingId) : null);

        $this->editorOpen = false;
        unset($this->people, $this->tree, $this->history);
    }

    public function deletePerson(SaveTeamMemberAction $saveMember): void
    {
        $this->authorize(Permission::EditTeam->value);

        $saveMember->delete(TeamMember::findOrFail($this->editingId));

        $this->editorOpen = false;
        unset($this->people, $this->tree, $this->history);
    }

    /** @param  Closure(): (array{message: string, before: array<string, mixed>}|null)  $write */
    protected function change(Closure $write, bool $remember = true): void
    {
        $this->authorize(Permission::EditTeam->value);

        try {
            $result = $write();
        } catch (ValidationException $exception) {
            $this->status = __("Couldn't save: :message", ['message' => $exception->getMessage()]);
            $this->statusIsError = true;

            return;
        }

        unset($this->saved, $this->tree, $this->history);

        if ($result === null) {
            $this->dispatch('lineup-toast', message: __('Nothing was set up for the week before.'), undo: $this->undo !== []);

            return;
        }

        if ($remember) {
            $this->undo[] = $result['before'];
            $this->undo = array_slice($this->undo, -self::UndoDepth);
        }

        $this->status = __('Saved');
        $this->statusIsError = false;
        $this->dispatch('lineup-toast', message: $result['message'], undo: $this->undo !== []);
    }

    public function render(): View
    {
        $monday = CarbonImmutable::parse($this->week);
        $members = TeamMember::orderBy('name')->get();
        $staffNames = DB::table('staff')->pluck('current', 'name');

        return view('livewire.team.team-lineup', [
            'monday' => $monday,
            'friday' => $monday->addDays(4),
            'isThisWeek' => $this->week === self::thisMonday(),
            'canEdit' => auth()->user()->can(Permission::EditTeam->value),
            'tags' => $this->tree->keepTags($this->saved?->tags),
            'badges' => $this->tree->keepBadges($this->saved?->badges),
            'members' => $members,
            'staffNames' => $staffNames,
            'candidates' => $staffNames
                ->filter(fn (mixed $current) => (bool) $current)
                ->keys()
                ->reject(fn (string $name) => in_array($name, self::Placeholders, true) || $members->contains('name', $name))
                ->sort()
                ->values(),
            'orphanAssignments' => ProjectAssignment::whereNotIn('job_number', Project::select('job_number'))->get(),
        ]);
    }
}
