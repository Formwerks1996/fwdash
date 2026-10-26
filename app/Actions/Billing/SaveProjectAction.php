<?php

namespace App\Actions\Billing;

use App\Models\Project;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveProjectAction
{
    public function __construct(protected AuditLogger $audit) {}

    /** @param array<string, mixed> $attributes */
    public function execute(array $attributes, ?Project $project = null): Project
    {
        $attributes = array_map(fn (mixed $value) => $value === '' ? null : $value, $attributes);

        if (! $project) {
            $attributes['job_number'] = Project::normalizeJobNumber($attributes['job_number']);

            if (Project::whereKey($attributes['job_number'])->exists()) {
                throw ValidationException::withMessages(['job_number' => __('Job :number already exists.', ['number' => $attributes['job_number']])]);
            }
        }

        $clash = Project::where('job_name', $attributes['job_name'] ?? $project?->job_name)
            ->when($project, fn ($query) => $query->whereKeyNot($project->job_number))
            ->first();

        if ($clash) {
            throw ValidationException::withMessages(['job_name' => __("':name' is already job :number.", ['name' => $clash->job_name, 'number' => $clash->job_number])]);
        }

        return DB::transaction(function () use ($attributes, $project) {
            if (! $project) {
                $project = Project::create($attributes);

                $this->audit->log('insert', 'projects', $project->job_number, after: $attributes);

                return $project;
            }

            $before = $project->only(array_keys($attributes));
            $project->update($attributes);

            $this->audit->log('update', 'projects', $project->job_number, before: $before, after: $attributes);

            return $project;
        });
    }
}
