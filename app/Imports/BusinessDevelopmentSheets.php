<?php

namespace App\Imports;

use App\Enums\PursuitStage;
use App\Models\Developer;
use App\Models\DeveloperAlias;
use App\Models\Pursuit;
use App\Support\DeveloperKey;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Spatie\SimpleExcel\SimpleExcelReader;

/*
 * The Pipeline, Developers and Developer Merges sheets.
 *
 * These are kept in the dashboard now, so an upload never wipes them:
 * developers and merges are merged in by key, and pursuits load only into an
 * empty Pipeline (the first upload from the old dashboard).
 */
class BusinessDevelopmentSheets
{
    /** @var array<int, array<string, mixed>> */
    protected array $pursuits = [];

    /** @var array<string, array<string, mixed>> */
    protected array $developers = [];

    /** @var array<string, array<string, mixed>> */
    protected array $aliases = [];

    /** @var array<int, string> */
    public array $warnings = [];

    public function __construct(protected string $path) {}

    public function read(): void
    {
        $sheets = SimpleExcelReader::create($this->path, 'xlsx')->getSheetNames();

        if (in_array('Pipeline', $sheets, true)) {
            $this->readPipeline();
        }

        if (in_array('Developers', $sheets, true)) {
            $this->readDevelopers();
        }

        if (in_array('Developer Merges', $sheets, true)) {
            $this->readMerges();
        }
    }

    /** @return array<string, int> rows written per table */
    public function apply(): array
    {
        $counts = ['pursuits' => 0, 'developers' => 0, 'developer_aliases' => 0];

        foreach ($this->developers as $key => $developer) {
            Developer::updateOrCreate(['key' => $key], $developer);
            $counts['developers']++;
        }

        foreach ($this->aliases as $aliasKey => $alias) {
            DeveloperAlias::updateOrCreate(['alias_key' => $aliasKey], $alias);
            $counts['developer_aliases']++;
        }

        $this->flattenAliases();

        if (Pursuit::query()->doesntExist()) {
            foreach ($this->pursuits as $pursuit) {
                Pursuit::forceCreate($pursuit);
                $counts['pursuits']++;
            }
        }

        return $counts;
    }

    protected function readPipeline(): void
    {
        $owners = config('bizdev.owners');

        foreach ($this->rows('Pipeline') as $line => $row) {
            $developer = trim((string) ($row['Developer'] ?? ''));
            $project = $this->text($row['Project'] ?? null);

            if ($developer === '' && $project === null && blank($row['Fee'] ?? null)) {
                continue;
            }

            $stage = PursuitStage::tryFrom((string) ($row['Stage'] ?? ''));
            $owner = $this->text($row['Lead'] ?? null);

            if (! $stage) {
                $this->warnings[] = "Pipeline row {$line}: unknown stage '".($row['Stage'] ?? '')."' — set to Contacted.";
            }

            if ($owner !== null && ! in_array($owner, $owners, true)) {
                $this->warnings[] = "Pipeline row {$line}: lead '{$owner}' is not one of ".implode(', ', $owners).' — left empty.';
                $owner = null;
            }

            $this->pursuits[] = [
                'developer' => $developer,
                'project' => $project,
                'fee' => is_numeric($row['Fee'] ?? null) ? $row['Fee'] : null,
                'stage' => ($stage ?? PursuitStage::Contacted)->value,
                'owner' => $owner,
                'note' => $this->text($row['Note'] ?? null),
                'created_at' => $this->timestamp($row['Created'] ?? null),
                'updated_at' => $this->timestamp($row['Updated'] ?? null),
            ];
        }
    }

    protected function readDevelopers(): void
    {
        foreach ($this->rows('Developers') as $line => $row) {
            $name = $this->text($row['Developer'] ?? null);
            $key = DeveloperKey::make($name);

            if ($key === '') {
                continue;
            }

            if (isset($this->developers[$key])) {
                $this->warnings[] = "Developers row {$line}: '{$name}' is listed twice — kept the last.";
            }

            $this->developers[$key] = [
                'name' => $name,
                'city' => $this->text($row['City'] ?? null),
                'note' => $this->text($row['Note'] ?? null),
            ];
        }
    }

    protected function readMerges(): void
    {
        foreach ($this->rows('Developer Merges') as $line => $row) {
            $spelling = $this->text($row['Spelling'] ?? null);
            $aliasKey = DeveloperKey::make($spelling);
            $canonKey = DeveloperKey::make($this->text($row['Merged Into'] ?? null));

            if ($aliasKey === '' || $canonKey === '') {
                continue;
            }

            if ($aliasKey === $canonKey) {
                $this->warnings[] = "Developer Merges row {$line}: '{$spelling}' is merged into itself — skipped.";

                continue;
            }

            $this->aliases[$aliasKey] = [
                'canon_key' => $canonKey,
                'alias_name' => $spelling,
                'created_at' => $this->timestamp($row['Merged'] ?? null),
            ];
        }
    }

    /** Aliases are one hop: a merge into a name that is itself merged away points at the end. */
    protected function flattenAliases(): void
    {
        $map = DeveloperAlias::pluck('canon_key', 'alias_key')->all();

        foreach ($map as $alias => $canon) {
            $seen = [$alias => true];

            while (isset($map[$canon]) && ! isset($seen[$canon])) {
                $seen[$canon] = true;
                $canon = $map[$canon];
            }

            if ($canon !== $map[$alias]) {
                DeveloperAlias::whereKey($alias)->update(['canon_key' => $canon]);
            }
        }
    }

    /** @return iterable<int, array<string, mixed>> */
    protected function rows(string $sheet): iterable
    {
        foreach (SimpleExcelReader::create($this->path, 'xlsx')->fromSheetName($sheet)->getRows() as $index => $row) {
            yield $index + 2 => $row;
        }
    }

    protected function text(mixed $value): ?string
    {
        $value = trim((string) ($value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value));

        return $value === '' ? null : $value;
    }

    protected function timestamp(mixed $value): CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        $date = ExcelValue::date($value);

        return $date ? CarbonImmutable::parse($date) : CarbonImmutable::now();
    }
}
