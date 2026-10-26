<?php

namespace App\Actions\Team;

use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\TeamMember;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * Who looks after a project. Assigning the same person twice is a no-op, so
 * it is not logged twice.
 */
class AssignProjectAction
{
    public function __construct(protected AuditLogger $audit) {}

    public function execute(string $jobNumber, string $memberName, string $role = 'pm'): ?ProjectAssignment
    {
        if (! Project::whereKey($jobNumber)->exists()) {
            throw ValidationException::withMessages(['assignment' => __('unknown project :job', ['job' => $jobNumber])]);
        }

        if (! TeamMember::active()->where('name', $memberName)->exists()) {
            throw ValidationException::withMessages(['assignment' => __('unknown team member :name', ['name' => $memberName])]);
        }

        if (! in_array($role, ProjectAssignment::Roles, true)) {
            throw ValidationException::withMessages(['assignment' => __('a project role is pm or member')]);
        }

        if (ProjectAssignment::where(['job_number' => $jobNumber, 'member_name' => $memberName])->exists()) {
            return null;
        }

        return DB::transaction(function () use ($jobNumber, $memberName, $role) {
            $assignment = ProjectAssignment::create([
                'job_number' => $jobNumber,
                'member_name' => $memberName,
                'role' => $role,
                'set_by' => auth()->user()->name ?? 'system',
                'set_at' => now(),
            ]);

            $this->audit->log('insert', 'project_assignments', "{$jobNumber}|{$memberName}", after: [
                'job_number' => $jobNumber,
                'member_name' => $memberName,
                'role' => $role,
            ]);

            return $assignment;
        });
    }

    public function release(string $jobNumber, string $memberName): void
    {
        $assignment = ProjectAssignment::where(['job_number' => $jobNumber, 'member_name' => $memberName])->first();

        if (! $assignment) {
            return;
        }

        DB::transaction(function () use ($assignment, $jobNumber, $memberName) {
            $before = $assignment->only(['job_number', 'member_name', 'role']);
            $assignment->delete();

            $this->audit->log('delete', 'project_assignments', "{$jobNumber}|{$memberName}", before: $before);
        });
    }
}
