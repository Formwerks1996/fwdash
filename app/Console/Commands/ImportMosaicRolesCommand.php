<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\MosaicProjectRole;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Loads who Mosaic has on each project from a Mosaic Mock pull (data/raw:
 * member_project_role.json, employee.json, role.json and project-*.json).
 * Mosaic's own ids are turned into what this app keys on: the person's email
 * and the project's job number. Re-running replaces the lot.
 *
 * Refresh the pull first, GET only:
 *   python3 "Mosaic Mock/sync/mosaic_pull.py" --only reference
 *   python3 "Mosaic Mock/sync/mosaic_pull.py" --only portfolios
 */
class ImportMosaicRolesCommand extends Command
{
    protected $signature = 'mosaic:import-roles {path : Mosaic Mock\'s data/raw folder}';

    protected $description = 'Replace who is on each project with the roles in a Mosaic pull';

    public function handle(): int
    {
        $path = rtrim((string) $this->argument('path'), '/');

        $roles = $this->rows("{$path}/member_project_role.json");
        $people = $this->rows("{$path}/employee.json");

        if ($roles === null || $people === null) {
            $this->error("`{$path}` has no member_project_role.json and employee.json from a Mosaic pull.");

            return self::FAILURE;
        }

        $emails = $people
            ->filter(fn (array $person) => filled($person['email'] ?? null))
            ->mapWithKeys(fn (array $person) => [$person['mosaic_id'] => Str::lower(trim($person['email']))]);

        $roleNames = ($this->rows("{$path}/role.json") ?? collect())->pluck('name', 'mosaic_id');

        $jobNumbers = collect(glob("{$path}/project-*.json") ?: [])
            ->flatMap(fn (string $file) => $this->rows($file) ?? [])
            ->filter(fn (array $project) => filled($project['project_number'] ?? null))
            ->mapWithKeys(fn (array $project) => [$project['mosaic_id'] => trim($project['project_number'])]);

        $rows = $roles
            ->reject(fn (array $role) => filled($role['archived_at'] ?? null))
            ->map(fn (array $role) => [
                'email' => $emails[$role['member_id'] ?? null] ?? null,
                'job_number' => $jobNumbers[$role['project_id'] ?? null] ?? null,
                'role' => $roleNames[$role['role_id'] ?? null] ?? null,
                // Mosaic's "no start" is 1900-01-01.
                'start_date' => ($role['start_date'] ?? null) > '1900-01-01' ? $role['start_date'] : null,
                'end_date' => $role['end_date'] ?? null,
            ]);

        $kept = $rows
            ->filter(fn (array $row) => $row['email'] && $row['job_number'])
            ->unique(fn (array $row) => "{$row['email']}|{$row['job_number']}|{$row['role']}")
            ->values();

        DB::transaction(function () use ($kept) {
            MosaicProjectRole::query()->delete();

            foreach ($kept->chunk(500) as $chunk) {
                MosaicProjectRole::insert($chunk->all());
            }

            AppSetting::updateOrCreate(['key' => 'mosaic.roles_imported_at'], ['value' => now()->toIso8601String()]);
        });

        $skipped = $rows->count() - $rows->filter(fn (array $row) => $row['email'] && $row['job_number'])->count();

        $this->comment("Imported {$kept->count()} project roles for {$kept->pluck('email')->unique()->count()} people on {$kept->pluck('job_number')->unique()->count()} projects.");

        if ($skipped) {
            $this->warn("{$skipped} roles were skipped: their person has no email or their project no job number.");
        }

        return self::SUCCESS;
    }

    /** @return Collection<int, array<string, mixed>>|null The list inside one of Mosaic's responses. */
    protected function rows(string $file): ?Collection
    {
        $payload = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;

        if (! is_array($payload)) {
            return null;
        }

        $list = array_is_list($payload) ? $payload : collect($payload)->first(fn ($value) => is_array($value) && array_is_list($value));

        return is_array($list) ? collect($list)->filter(fn ($row) => is_array($row))->values() : null;
    }
}
