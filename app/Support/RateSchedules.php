<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Charge-out schedules a job can be set up on: one rate for each of the four
 * billing titles. These are the schedules the workbook's jobs actually use;
 * "standard" means the rate titles' own defaults, so the job needs no
 * overrides.
 */
class RateSchedules
{
    /** The titles a schedule prices, lowest first. */
    public const Titles = ['Junior', 'Intermediate', 'Senior', 'Principal'];

    /** @var array<string, array<string, int>> */
    public const Schedules = [
        '100-125-150-225' => ['Junior' => 100, 'Intermediate' => 125, 'Senior' => 150, 'Principal' => 225],
        '90-125-175-225' => ['Junior' => 90, 'Intermediate' => 125, 'Senior' => 175, 'Principal' => 225],
        '100-125-175-225' => ['Junior' => 100, 'Intermediate' => 125, 'Senior' => 175, 'Principal' => 225],
        '90-125-150-225' => ['Junior' => 90, 'Intermediate' => 125, 'Senior' => 150, 'Principal' => 225],
        '95-95-135-200' => ['Junior' => 95, 'Intermediate' => 95, 'Senior' => 135, 'Principal' => 200],
    ];

    /** @return array<string, string> The rate titles' own charge-outs, by title. */
    public static function standard(): array
    {
        $defaults = DB::table('rate_titles')->pluck('default_charge_out', 'title');

        return collect(self::Titles)
            ->mapWithKeys(fn (string $title) => [$title => $defaults[$title] === null ? '' : (string) (float) $defaults[$title]])
            ->all();
    }

    /** @return array<string, string> */
    public static function rates(string $schedule): array
    {
        if (! isset(self::Schedules[$schedule])) {
            return self::standard();
        }

        return array_map(fn (int $rate) => (string) $rate, self::Schedules[$schedule]);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $standard = implode('-', array_filter(self::standard()));

        return [
            'standard' => __('Standard').($standard ? " ({$standard})" : ''),
            ...collect(self::Schedules)->keys()->mapWithKeys(fn (string $key) => [$key => $key])->all(),
            'custom' => __('Custom'),
        ];
    }
}
