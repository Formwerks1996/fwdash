<?php

namespace Database\Seeders;

use App\Enums\Phase;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PhasesSeeder extends Seeder
{
    public function run(): void
    {
        $phases = collect(Phase::cases())
            ->map(fn (Phase $phase) => ['code' => $phase->value, 'seq' => $phase->seq(), 'is_hourly' => false])
            ->push(['code' => Phase::Hours, 'seq' => 99, 'is_hourly' => true])
            ->all();

        DB::table('phases')->upsert($phases, ['code'], ['seq', 'is_hourly']);
    }
}
