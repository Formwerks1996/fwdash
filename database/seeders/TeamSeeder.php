<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 * The lineup roster as it stood when the old dashboard was retired, and the
 * three real lineup weeks saved there (the made-up sample weeks are left out).
 *
 * Only adds what is missing, so running it again never undoes an edit made in
 * the roster editor or on the lineup.
 */
class TeamSeeder extends Seeder
{
    /** name, full name, email, tier, group, default boss */
    public const Roster = [
        ['Andrew', 'Andrew Cook', 'andrew@formwerks.ca', 'partner', null, null],
        ['Nikolas', 'Nikolas Dittmann', 'nikolas@formwerks.ca', 'partner', null, null],
        ['Josh', 'Josh Schneider', 'josh@formwerks.ca', 'pm', null, null],
        ['Julie', 'Julie VanGrootheest', 'julie@formwerks.ca', 'pm', null, 'Andrew'],
        ['Dasha', 'Dasha Izmaylov', 'dasha@formwerks.ca', 'pm', null, 'Nikolas'],
        ['Maggie', 'Maggie Tsai', 'maggie@formwerks.ca', 'pm', null, null],
        ['Matthew', 'Matthew Mander', 'matthewmander@formwerks.ca', 'pm', null, null],
        ['Nicola', 'Nicola Gebelin', 'nicola@formwerks.ca', 'pm', null, null],
        ['Sarah', 'Sarah Van', 'sarah@formwerks.ca', 'pm', null, null],
        ['Vrinda', 'Vrinda Krishnankuttynair', 'vrinda@formwerks.ca', 'pm', null, 'Andrew'],
        ['Adam', 'Adam Chan', 'adam@formwerks.ca', 'pm', null, null],
        ['Tetiana', 'Tetiana Vinnytska', 'tetiana@formwerks.ca', 'pm', null, null],
        ['Alishah', 'Alishah Shamsher', 'alishah@formwerks.ca', 'staff', 'tech', null],
        ['Edward', 'Edward Palo', 'edward@formwerks.ca', 'staff', 'tech', null],
        ['Kendra', 'Kendra Hoeppner', 'kendra@formwerks.ca', 'staff', 'tech', null],
        ['Nathan', 'Nathan Lam', 'nathan@formwerks.ca', 'staff', 'tech', null],
        ['Mohammad', 'Mohammad Asadi', 'mohammad@formwerks.ca', 'staff', 'other', null],
        ['Shauna', 'Shauna Townsend', 'shauna@formwerks.ca', 'staff', 'other', null],
        ['Sara', 'Sara Adeli', 'sara@formwerks.ca', 'staff', 'other', null],
    ];

    public function run(): void
    {
        $now = now();

        $members = collect(self::Roster)
            ->map(fn (array $row) => [
                'name' => $row[0],
                'full_name' => $row[1],
                'email' => $row[2],
                'tier' => $row[3],
                'grp' => $row[4],
                'default_boss' => $row[5],
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        DB::table('team_members')->insertOrIgnore($members);

        /** @var array<int, array{week: string, placements: array<string, string>, tags: array<string, string>, badges: array<string, mixed>, updated_at: string}> $weeks */
        $weeks = json_decode((string) file_get_contents(__DIR__.'/data/lineup-weeks.json'), true);

        DB::table('lineup_weeks')->insertOrIgnore(
            collect($weeks)
                ->map(fn (array $week) => [
                    'week' => $week['week'],
                    'placements' => json_encode((object) $week['placements']),
                    'tags' => json_encode((object) $week['tags']),
                    'badges' => json_encode((object) $week['badges']),
                    'updated_by' => null,
                    'created_at' => $week['updated_at'],
                    'updated_at' => $week['updated_at'],
                ])
                ->all(),
        );
    }
}
