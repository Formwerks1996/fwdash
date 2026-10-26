<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\OutreachProject;
use App\Models\OutreachProspect;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/*
 * Loads the outreach research (outreach.json, built by build_outreach.py in
 * "Developer Outreach") and its thumbnails. Re-running replaces it: the
 * research is regenerated, never edited here.
 */
class ImportOutreachCommand extends Command
{
    protected $signature = 'outreach:import {path : outreach.json} {--thumbs= : Folder of thumbnails (default: outreach-thumbs beside the file)}';

    protected $description = 'Replace the Outreach List with an outreach.json research file';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $data = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($data) || ! isset($data['rows'])) {
            $this->error("`{$path}` is not an outreach.json file.");

            return self::FAILURE;
        }

        $thumbs = $this->option('thumbs') ?: dirname($path).'/outreach-thumbs';
        $rows = collect(Arr::array($data, 'rows'));

        $this->info("Importing {$rows->count()} prospects...");

        $images = $rows
            ->flatMap(fn (array $row) => [$row['img'] ?? null, ...array_column($row['projects'] ?? [], 'img')])
            ->filter()
            ->unique()
            ->values();

        DB::transaction(function () use ($data, $rows, $images, $thumbs) {
            OutreachProject::query()->delete();
            OutreachProspect::query()->delete();
            DB::table('outreach_images')->delete();

            foreach ($rows->values() as $position => $row) {
                OutreachProspect::create($this->prospect($row, $position));

                foreach (array_values($row['projects'] ?? []) as $projectPosition => $project) {
                    OutreachProject::create($this->project($row['key'], $project, $projectPosition));
                }
            }

            $missing = 0;

            foreach ($images as $image) {
                $file = "{$thumbs}/{$image}";

                if (! is_readable($file)) {
                    $missing++;

                    continue;
                }

                DB::table('outreach_images')->insert([
                    'name' => $image,
                    'mime_type' => mime_content_type($file) ?: 'image/jpeg',
                    'data' => base64_encode((string) file_get_contents($file)),
                ]);
            }

            if ($missing) {
                $this->warn("{$missing} referenced thumbnails were not found in `{$thumbs}`.");
            }

            foreach (['built' => $data['built'] ?? null, 'source' => $data['source'] ?? null, 'map_url' => $data['mapUrl'] ?? null, 'types' => json_encode($data['types'] ?? [])] as $key => $value) {
                AppSetting::updateOrCreate(['key' => "outreach.{$key}"], ['value' => $value]);
            }
        });

        $this->comment("Imported {$rows->count()} prospects, ".OutreachProject::count()." projects and {$images->count()} images.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function prospect(array $row, int $position): array
    {
        $text = fn (string $key) => filled($row[$key] ?? null) ? (string) $row[$key] : null;

        return [
            'key' => $row['key'],
            'name' => $row['name'],
            'score' => (int) ($row['score'] ?? 0),
            'tier' => $text('tier'),
            'region' => $text('region'),
            'hq' => $text('hq'),
            'product' => $text('product'),
            'size' => $text('size'),
            'style' => $text('style'),
            'why' => $text('why'),
            'architect' => $text('architect'),
            'phased' => $text('phased'),
            'website' => $text('website'),
            'webcheck' => $text('webcheck'),
            'others' => $text('others'),
            'email' => $text('email'),
            'phone' => $text('phone'),
            'contact_page' => $text('contactPage'),
            'route' => $text('route'),
            'contact_notes' => $text('contactNotes'),
            'confidence' => $text('confidence'),
            'client_note' => $text('clientNote'),
            'flag' => $text('flag'),
            'passes' => $text('passes'),
            'top' => (bool) ($row['top'] ?? false),
            'img' => $text('img'),
            'has_contact' => (bool) ($row['hasContact'] ?? false),
            'has_linkedin' => (bool) ($row['hasLinkedIn'] ?? false),
            'has_email' => (bool) ($row['hasEmail'] ?? false),
            'person' => $row['person'] ?? null,
            'activity' => $row['activity'] ?? [],
            'types' => $row['types'] ?? [],
            'tags' => $row['tags'] ?? [],
            'sources' => $row['sources'] ?? [],
            'recent' => $row['recent'] ?? [],
            'position' => $position,
        ];
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>
     */
    protected function project(string $prospectKey, array $project, int $position): array
    {
        return [
            'prospect_key' => $prospectKey,
            'name' => $project['name'] ?? null,
            'where_text' => $project['where'] ?? null,
            'units' => isset($project['units']) ? (string) $project['units'] : null,
            'url' => $project['url'] ?? null,
            'away' => $project['away'] ?? null,
            'img' => ($project['img'] ?? '') ?: null,
            'hero' => (bool) ($project['hero'] ?? false),
            'types' => $project['types'] ?? [],
            'lat' => $project['lat'] ?? null,
            'lon' => $project['lon'] ?? null,
            'exact' => (bool) ($project['exact'] ?? false),
            'position' => $position,
        ];
    }
}
