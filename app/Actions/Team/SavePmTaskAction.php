<?php

namespace App\Actions\Team;

use App\Models\PmTask;
use App\Models\Project;
use App\Models\TeamMember;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavePmTaskAction
{
    public function __construct(protected AuditLogger $audit) {}

    public function add(string $jobNumber, string $title, ?string $assignee = null, ?string $dueOn = null): PmTask
    {
        $values = $this->validated($jobNumber, $title, $assignee, $dueOn);

        return DB::transaction(function () use ($values) {
            $task = PmTask::create([...$values, 'created_by' => auth()->id()]);

            $this->audit->log('task.add', 'pm_tasks', $task->id, after: $task->auditValues());

            return $task;
        });
    }

    public function toggle(PmTask $task): void
    {
        DB::transaction(function () use ($task) {
            $before = ['done_at' => $task->done_at?->toDateTimeString()];
            $task->update(['done_at' => $task->done_at ? null : now()]);

            $this->audit->log('task.set', 'pm_tasks', $task->id, before: $before, after: ['done_at' => $task->done_at?->toDateTimeString()]);
        });
    }

    public function delete(PmTask $task): void
    {
        DB::transaction(function () use ($task) {
            $before = $task->auditValues();
            $task->delete();

            $this->audit->log('task.delete', 'pm_tasks', $task->id, before: $before);
        });
    }

    /** @return array{job_number: string, title: string, assignee: ?string, due_on: ?string} */
    protected function validated(string $jobNumber, string $title, ?string $assignee, ?string $dueOn): array
    {
        $title = trim($title);

        if ($title === '' || mb_strlen($title) > 200) {
            throw ValidationException::withMessages(['task' => __('a task needs a title (200 characters at most)')]);
        }

        if (! Project::whereKey($jobNumber)->exists()) {
            throw ValidationException::withMessages(['task' => __('unknown project :job', ['job' => $jobNumber])]);
        }

        $assignee = $assignee ?: null;

        if ($assignee !== null && ! TeamMember::active()->where('name', $assignee)->exists()) {
            throw ValidationException::withMessages(['task' => __('unknown team member :name', ['name' => $assignee])]);
        }

        $dueOn = $dueOn ?: null;

        if ($dueOn !== null && strtotime($dueOn) === false) {
            throw ValidationException::withMessages(['task' => __('a due date must be a date')]);
        }

        return ['job_number' => $jobNumber, 'title' => $title, 'assignee' => $assignee, 'due_on' => $dueOn];
    }
}
