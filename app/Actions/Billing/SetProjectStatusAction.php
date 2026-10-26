<?php

namespace App\Actions\Billing;

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class SetProjectStatusAction
{
    public function __construct(protected AuditLogger $audit) {}

    /**
     * A status that agrees with projects.active removes the override instead
     * of storing it, so it cannot silently pin the project later.
     */
    public function execute(Project $project, string $status, ?string $note = null): void
    {
        $derived = $project->active ? 'active' : 'closed';
        $existing = ProjectStatus::find($project->job_number);
        $before = $existing?->only(['status', 'note']);

        DB::transaction(function () use ($project, $status, $note, $derived, $existing, $before) {
            if ($status === $derived) {
                $existing?->delete();

                $this->audit->log('delete', 'project_statuses', $project->job_number, before: $before);

                return;
            }

            ProjectStatus::updateOrCreate(
                ['job_number' => $project->job_number],
                ['status' => $status, 'note' => $note, 'set_by' => auth()->user()->name ?? 'system', 'set_at' => now()],
            );

            $this->audit->log($existing ? 'update' : 'insert', 'project_statuses', $project->job_number, before: $before, after: ['status' => $status, 'note' => $note]);
        });
    }
}
