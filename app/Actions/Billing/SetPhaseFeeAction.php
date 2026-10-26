<?php

namespace App\Actions\Billing;

use App\Enums\Phase;
use App\Models\Project;
use App\Models\ProjectPhaseFee;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * Amending a fee never restates history: past draws keep the fee they were
 * billed at. Deliberately not gated on period status — a contract is amended
 * when the client signs.
 */
class SetPhaseFeeAction
{
    public function __construct(protected AuditLogger $audit) {}

    public function execute(Project $project, string $phase, ?string $fee): void
    {
        if (! Phase::tryFrom($phase)) {
            throw ValidationException::withMessages(['fee' => __('Unknown phase :phase.', ['phase' => $phase])]);
        }

        if ($fee !== null && (float) $fee < 0) {
            throw ValidationException::withMessages(['fee' => __('A fee cannot be negative.')]);
        }

        $existing = ProjectPhaseFee::where('job_number', $project->job_number)->where('phase_code', $phase)->first();
        $before = $existing?->only(['fee', 'note']);
        $key = "{$project->job_number}|{$phase}";

        DB::transaction(function () use ($project, $phase, $fee, $existing, $before, $key) {
            if ($fee === null && ! $existing?->note) {
                $existing?->delete();

                $this->audit->log('delete', 'project_phase_fees', $key, before: $before);

                return;
            }

            ProjectPhaseFee::updateOrCreate(
                ['job_number' => $project->job_number, 'phase_code' => $phase],
                ['fee' => $fee],
            );

            $this->audit->log($existing ? 'update' : 'insert', 'project_phase_fees', $key, before: $before, after: ['fee' => $fee, 'note' => $existing?->note]);
        });
    }
}
