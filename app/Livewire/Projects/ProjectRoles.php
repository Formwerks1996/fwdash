<?php

namespace App\Livewire\Projects;

use App\Actions\Team\SetProjectRoleAction;
use App\Billing\ProjectBurn;
use App\Enums\Permission;
use App\Models\Project;
use App\Models\ProjectRole;
use App\Models\TeamMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Managers set who leads each project and who manages it under them.
 *
 * @property-read Collection<int, array{jobNumber: string, jobName: string, developer: ?string, lead: ?string, pm: ?string}> $rows
 */
#[Title('Leads & PMs')]
class ProjectRoles extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $show = 'active';

    /** @return Collection<int, array{jobNumber: string, jobName: string, developer: ?string, lead: ?string, pm: ?string}> */
    #[Computed]
    public function rows(): Collection
    {
        $burn = app(ProjectBurn::class);
        $roles = ProjectRole::byJob();
        $search = Str::lower(trim($this->search));

        return Project::orderBy('job_number')
            ->get(['job_number', 'job_name', 'developer'])
            ->map(fn (Project $project) => [
                'jobNumber' => $project->job_number,
                'jobName' => $project->job_name,
                'developer' => $project->developer,
                'lead' => $roles[$project->job_number][ProjectRole::Lead][0] ?? null,
                'pm' => $roles[$project->job_number][ProjectRole::ProjectManager][0] ?? null,
            ])
            ->filter(fn (array $row) => match ($this->show) {
                'unmanaged' => $row['pm'] === null && $burn->effectiveStatus($row['jobNumber']) === 'active',
                'all' => true,
                default => $burn->effectiveStatus($row['jobNumber']) === 'active',
            })
            ->when($search !== '', fn (Collection $rows) => $rows->filter(
                fn (array $row) => Str::contains(Str::lower(implode(' ', [$row['jobNumber'], $row['jobName'], $row['developer'], $row['lead'], $row['pm']])), $search),
            ))
            ->values();
    }

    public function setRole(string $jobNumber, string $role, string $memberName, SetProjectRoleAction $setRole): void
    {
        $this->authorize(Permission::EditTeam->value);

        $this->resetValidation();
        $setRole->execute($jobNumber, $role, $memberName === '' ? null : $memberName);

        unset($this->rows);
    }

    public function render(): View
    {
        $team = TeamMember::active()->orderBy('name')->get();

        return view('livewire.projects.roles', [
            'leads' => $team->filter(fn (TeamMember $person) => $person->isPartner()),
            'team' => $team,
        ]);
    }
}
