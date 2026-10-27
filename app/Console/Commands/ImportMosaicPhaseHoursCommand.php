<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\ProjectPhaseHours;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Totals the hours on each phase of each job from a Mosaic Mock pull
 * (time_entry-*.json and project-*.json), with what they come to at the
 * charge-out rate on each entry. A Mosaic phase is matched to a billing
 * phase by its wording (phase_descriptions, then the names below); one that
 * matches none is kept with no code so the job's total stays whole. Rejected
 * entries are left out. Re-running replaces the lot.
 *
 * Refresh the pull first, GET only:
 *   python3 "Mosaic Mock/sync/mosaic_pull.py" --only reference
 *   python3 "Mosaic Mock/sync/mosaic_pull.py" --only time
 */
class ImportMosaicPhaseHoursCommand extends Command
{
    protected $signature = 'mosaic:import-phase-hours {path : Mosaic Mock\'s data/raw folder}';

    protected $description = 'Replace hours and charge-out per job phase with a Mosaic pull';

    /** Mosaic phase names the billing wording does not cover. */
    protected const ExtraNames = [
        'design development to rezoning' => 'DD to RZ',
        'rezoning processing' => 'RZP',
        'drawings for building permit and application' => 'BP',
        'tendering' => 'IFT',
    ];

    public function handle(): int
    {
        $path = rtrim((string) $this->argument('path'), '/');

        $entryFiles = glob("{$path}/time_entry-*.json") ?: [];
        $jobNumbers = collect(glob("{$path}/project-*.json") ?: [])
            ->flatMap(fn (string $file) => $this->rows($file) ?? [])
            ->filter(fn (array $project) => filled($project['project_number'] ?? null))
            ->mapWithKeys(fn (array $project) => [$project['mosaic_id'] => trim($project['project_number'])]);

        if ($entryFiles === [] || $jobNumbers->isEmpty()) {
            $this->error("`{$path}` has no time entries and projects from a Mosaic pull.");

            return self::FAILURE;
        }

        $codes = DB::table('phase_descriptions')
            ->pluck('phase_code', 'description')
            ->mapWithKeys(fn (string $code, string $description) => [Str::lower(trim($description)) => $code])
            ->merge(self::ExtraNames)
            ->merge(DB::table('phases')->pluck('code')->mapWithKeys(fn (string $code) => [Str::lower($code) => $code]));

        $known = DB::table('projects')->pluck('job_number')->flip();

        // Totalled a file at a time: the whole history does not fit in memory at once.
        $totals = [];

        foreach ($entryFiles as $file) {
            foreach ($this->rows($file) ?? [] as $entry) {
                $job = $jobNumbers[$entry['project_id'] ?? null] ?? null;
                $phase = trim((string) ($entry['phase_name'] ?? '')) ?: __('No phase');

                if ($job === null || ! isset($known[$job]) || ($entry['status'] ?? null) === 'rejected') {
                    continue;
                }

                $hours = (float) $entry['hours'];
                $rate = (float) ($entry['rate'] ?? 0);

                $totals["{$job}|{$phase}"] ??= [
                    'job_number' => $job,
                    'mosaic_phase' => $phase,
                    'phase_code' => $codes[Str::lower($phase)] ?? null,
                    'hours' => 0.0,
                    'charge_out' => 0.0,
                    'unrated_hours' => 0.0,
                ];
                $totals["{$job}|{$phase}"]['hours'] += $hours;
                $totals["{$job}|{$phase}"][$rate > 0 ? 'charge_out' : 'unrated_hours'] += $rate > 0 ? $hours * $rate : $hours;
            }
        }

        $rows = collect($totals)
            ->map(fn (array $row) => [...$row, 'hours' => round($row['hours'], 2), 'charge_out' => round($row['charge_out'], 2), 'unrated_hours' => round($row['unrated_hours'], 2)])
            ->values();

        DB::transaction(function () use ($rows) {
            ProjectPhaseHours::query()->delete();

            foreach ($rows->chunk(500) as $chunk) {
                ProjectPhaseHours::insert($chunk->all());
            }

            AppSetting::updateOrCreate(['key' => 'mosaic.phase_hours_imported'], ['value' => now()->toDateString()]);
        });

        $matched = $rows->whereNotNull('phase_code');
        $this->comment("Imported {$rows->count()} job phases on {$rows->pluck('job_number')->unique()->count()} jobs; {$matched->count()} match a billing phase, covering ".round($matched->sum('hours') / max(1, $rows->sum('hours')) * 100).'% of the hours.');

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
