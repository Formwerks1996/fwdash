<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\StaffCostRate;
use App\Models\StaffMonthHours;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Loads what each person costs an hour (cost_rate.json) and the hours they
 * logged each month (time_entry-*.json) from a Mosaic Mock pull, keyed by
 * email. Mosaic's hours are replaced on every run; a rate is only added, so
 * one set on the Staff Costs page is never overwritten. Mosaic labels its
 * amounts USD, but the team bills in CAD, so they are taken as CAD. "Prior
 * Hours" is not a person: it holds past projects' hours, loaded in lumps on
 * the day they were imported, so it is left out.
 *
 * Refresh the pull first, GET only:
 *   python3 "Mosaic Mock/sync/mosaic_pull.py" --only reference
 *   python3 "Mosaic Mock/sync/mosaic_pull.py" --only time
 */
class ImportMosaicCostsCommand extends Command
{
    protected $signature = 'mosaic:import-costs {path : Mosaic Mock\'s data/raw folder}';

    protected $description = 'Load cost rates and monthly hours per person from a Mosaic pull';

    /** Mosaic accounts that hold loaded history rather than anyone's time. */
    protected const Placeholders = ['priorhours@formwerks.ca'];

    public function handle(): int
    {
        $path = rtrim((string) $this->argument('path'), '/');

        $rates = $this->rows("{$path}/cost_rate.json");
        $people = $this->rows("{$path}/employee.json");
        $entryFiles = glob("{$path}/time_entry-*.json") ?: [];

        if ($rates === null || $people === null || $entryFiles === []) {
            $this->error("`{$path}` has no cost_rate.json, employee.json and time entries from a Mosaic pull.");

            return self::FAILURE;
        }

        $byId = $people
            ->filter(fn (array $person) => filled($person['email'] ?? null))
            ->reject(fn (array $person) => in_array(Str::lower(trim($person['email'])), self::Placeholders, true))
            ->keyBy('mosaic_id')
            ->map(fn (array $person) => [
                'email' => Str::lower(trim($person['email'])),
                'name' => trim(($person['first_name'] ?? '').' '.($person['last_name'] ?? '')) ?: null,
            ]);

        $costRates = $rates
            ->reject(fn (array $rate) => ($rate['discarded'] ?? false) || ! isset($byId[$rate['member_id'] ?? null]))
            ->filter(fn (array $rate) => (float) ($rate['actual_hourly_rate'] ?? 0) > 0)
            ->map(fn (array $rate) => [
                ...$byId[$rate['member_id']],
                'hourly_cost' => round((float) $rate['actual_hourly_rate'], 2),
                'starts_on' => $rate['start_date'] ?? '2000-01-01',
            ]);

        // Totalled a file at a time: the whole history does not fit in memory at once.
        $totals = [];

        foreach ($entryFiles as $file) {
            foreach ($this->rows($file) ?? [] as $entry) {
                if (($entry['status'] ?? null) === 'rejected' || blank($entry['member_email'] ?? null)) {
                    continue;
                }

                $email = Str::lower(trim($entry['member_email']));
                $month = substr((string) $entry['date'], 0, 7);

                if (in_array($email, self::Placeholders, true)) {
                    continue;
                }

                $totals["{$email}|{$month}"] ??= ['email' => $email, 'name' => $entry['member_name'] ?? null, 'month' => $month, 'hours' => 0.0, 'source' => 'mosaic'];
                $totals["{$email}|{$month}"]['hours'] += (float) $entry['hours'];
            }
        }

        $hours = collect($totals)
            ->map(fn (array $row) => [...$row, 'hours' => round($row['hours'], 2)])
            ->values();

        DB::transaction(function () use ($costRates, $hours) {
            foreach ($costRates as $rate) {
                if (! StaffCostRate::where('email', $rate['email'])->whereDate('starts_on', $rate['starts_on'])->exists()) {
                    StaffCostRate::create($rate);
                }
            }

            StaffMonthHours::where('source', 'mosaic')->delete();

            foreach ($hours->chunk(500) as $chunk) {
                StaffMonthHours::insert($chunk->all());
            }

            AppSetting::updateOrCreate(['key' => 'mosaic.hours_through'], ['value' => $hours->max('month')]);
        });

        $this->comment("Imported {$costRates->count()} cost rates and {$hours->count()} months of hours for {$hours->pluck('email')->unique()->count()} people, through {$hours->max('month')}.");

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
