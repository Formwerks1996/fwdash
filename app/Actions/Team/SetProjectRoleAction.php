<?php

namespace App\Actions\Team;

use App\Models\Project;
use App\Models\ProjectRole;
use App\Models\TeamMember;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * Sets who leads or who manages a project: one person per role, so setting a
 * new one replaces the last, and a null name clears the role. Setting the same
 * person again is a no-op, so it is not logged twice.
 */
class SetProjectRoleAction
{
    public function __construct(protected AuditLogger $audit) {}

    public function execute(string $jobNumber, string $role, ?string $memberName): void
    {
        if (! Project::whereKey($jobNumber)->exists()) {
            throw ValidationException::withMessages(['role' => __('unknown project :job', ['job' => $jobNumber])]);
        }

        if (! in_array($role, ProjectRole::Roles, true)) {
            throw ValidationException::withMessages(['role' => __('a project role is lead or pm')]);
        }

        if ($memberName !== null && ! TeamMember::active()->where('name', $memberName)->exists()) {
            throw ValidationException::withMessages(['role' => __('unknown team member :name', ['name' => $memberName])]);
        }

        $current = ProjectRole::where(['job_number' => $jobNumber, 'role' => $role])->pluck('member_name')->all();

        if ($current === array_filter([$memberName])) {
            return;
        }

        DB::transaction(function () use ($jobNumber, $role, $memberName, $current) {
            ProjectRole::where(['job_number' => $jobNumber, 'role' => $role])->delete();

            if ($memberName !== null) {
                ProjectRole::create([
                    'job_number' => $jobNumber,
                    'member_name' => $memberName,
                    'role' => $role,
                    'set_by' => auth()->user()->name ?? 'system',
                    'set_at' => now(),
                ]);
            }

            $this->audit->log(
                $memberName === null ? 'delete' : 'update',
                'project_roles',
                "{$jobNumber}|{$role}",
                before: ['member_name' => implode(', ', $current) ?: null],
                after: ['member_name' => $memberName],
            );
        });
    }
}
