<?php

namespace App\Console\Commands;

use App\Actions\Billing\TagHourLinesAction;
use App\Models\Extra;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Brings in the extras Mosaic holds as phases of a job (phase_index.json and
 * project-*.json from a Mosaic Mock pull): the numbered ones ("8040-04: New
 * parapet roof profile", at a set fee), ones named as extras, and hourly ones
 * such as "Construction Administration (Hourly)". Mosaic's "not started" is
 * taken as proposed. An extra already brought in is left as the dashboard has
 * it, so edits made here are never overwritten. Then tags the hourly lines
 * whose notes name an extra.
 *
 * Refresh the pull first, GET only:
 *   python3 "Mosaic Mock/sync/mosaic_pull.py" --only reference
 */
class ImportMosaicExtrasCommand extends Command
{
    protected $signature = 'mosaic:import-extras {path : Mosaic Mock\'s data/raw folder}';

    protected $description = 'Add the extras in a Mosaic pull and tag hourly lines to them';

    protected const Statuses = ['active' => 'active', 'complete' => 'complete'];

    public function handle(TagHourLinesAction $tagLines): int
    {
        $path = rtrim((string) $this->argument('path'), '/');

        $phases = $this->rows("{$path}/phase_index.json");
        $jobNumbers = collect(glob("{$path}/project-*.json") ?: [])
            ->flatMap(fn (string $file) => $this->rows($file) ?? [])
            ->filter(fn (array $project) => filled($project['project_number'] ?? null))
            ->mapWithKeys(fn (array $project) => [$project['mosaic_id'] => trim($project['project_number'])]);

        if ($phases === null || $jobNumbers->isEmpty()) {
            $this->error("`{$path}` has no phase_index.json and projects from a Mosaic pull.");

            return self::FAILURE;
        }

        $known = DB::table('projects')->pluck('job_number')->flip();
        $existing = Extra::whereNotNull('mosaic_id')->pluck('mosaic_id')->flip();

        $extras = $phases
            ->map(fn (array $phase) => [...$phase, 'job_number' => $jobNumbers[$phase['project_id'] ?? null] ?? null, 'name' => trim((string) ($phase['name'] ?? ''))])
            ->filter(fn (array $phase) => $phase['job_number'] !== null && isset($known[$phase['job_number']]))
            ->reject(fn (array $phase) => isset($existing[$phase['mosaic_id']]))
            ->map(fn (array $phase) => $this->asExtra($phase))
            ->filter()
            ->values();

        DB::transaction(function () use ($extras) {
            foreach ($extras as $extra) {
                Extra::create($extra);
            }
        });

        $tagged = $tagLines->autoTag();

        $this->comment("Added {$extras->count()} extras on {$extras->pluck('job_number')->unique()->count()} jobs and tagged {$tagged} hourly lines to extras.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $phase
     * @return array<string, mixed>|null
     */
    protected function asExtra(array $phase): ?array
    {
        $name = $phase['name'];
        $numbered = preg_match('/^#?(\d{3,4}-\d{2})\s*:?\s*(.*)$/', $name, $parts) === 1;
        $lower = Str::lower($name);

        if (! $numbered && ! Str::contains($lower, ['extra', '(hourly)'])) {
            return null;
        }

        $total = (float) ($phase['total'] ?? 0);

        return [
            'job_number' => $phase['job_number'],
            'code' => $numbered ? $parts[1] : null,
            'title' => $numbered && trim($parts[2]) !== '' ? trim($parts[2]) : $name,
            'status' => self::Statuses[$phase['budget_status'] ?? ''] ?? 'proposed',
            'budget_amount' => $total > 0 ? number_format($total, 2, '.', '') : null,
            'budget_hours' => (float) ($phase['estimated_hours'] ?? 0) > 0 ? number_format((float) $phase['estimated_hours'], 2, '.', '') : null,
            'mosaic_id' => (int) $phase['mosaic_id'],
            'mosaic_phase' => $name,
        ];
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
