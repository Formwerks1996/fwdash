<?php

namespace App\Billing;

use App\Models\Developer;
use App\Models\DeveloperAlias;
use App\Support\DeveloperKey;
use App\Support\Money;

/*
 * Who the firm works for: the developers named on billing projects plus the
 * ones added by hand, folded onto one key each through the alias map.
 *
 * Merges are an overlay. A project keeps the spelling someone typed; this
 * index decides which firm that spelling belongs to.
 */
class DeveloperIndex
{
    /** @var array<string, array<string, mixed>>|null */
    protected ?array $rows = null;

    /** @var array<string, string>|null */
    protected ?array $aliases = null;

    public function __construct(protected BillingEngine $engine) {}

    /** @return array<string, string> alias key => canonical key */
    public function aliases(): array
    {
        return $this->aliases ??= DeveloperAlias::pluck('canon_key', 'alias_key')->all();
    }

    public function resolve(string $key): string
    {
        return $this->aliases()[$key] ?? $key;
    }

    /** @return array<string, array<string, mixed>> keyed by developer key, ordered by job count then name */
    public function all(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $index = [];
        $spellings = [];

        foreach ($this->engine->jobs() as $job) {
            $raw = DeveloperKey::make($job['developer']);

            if ($raw === '') {
                continue;
            }

            $key = $this->resolve($raw);
            $index[$key] ??= $this->emptyRow($key, trim((string) $job['developer']));
            $index[$key]['sources']['billing'] = true;
            $index[$key]['jobs'][] = $job['job_number'];

            if ($raw === $key) {
                $spellings[$key][trim((string) $job['developer'])] = ($spellings[$key][trim((string) $job['developer'])] ?? 0) + 1;

                continue;
            }

            $index[$key]['aka'][$raw] = trim((string) $job['developer']);
        }

        foreach ($spellings as $key => $counts) {
            arsort($counts);
            $index[$key]['name'] = (string) array_key_first($counts);
        }

        foreach (Developer::all() as $developer) {
            $key = $this->resolve($developer->key);
            $index[$key] ??= $this->emptyRow($key, $developer->name);
            $index[$key]['sources']['added'] = true;

            $developer->key === $key
                ? $index[$key]['name'] = $developer->name
                : $index[$key]['aka'][$developer->key] = $developer->name;

            $index[$key]['city'] = $developer->city ?? $index[$key]['city'];
            $index[$key]['note'] = $developer->note ?? $index[$key]['note'];
        }

        $rows = collect($index)
            ->map(fn (array $row) => $this->summarize($row))
            ->sort(fn (array $a, array $b) => [$b['jobCount'], mb_strtolower($a['name'])] <=> [$a['jobCount'], mb_strtolower($b['name'])]);

        return $this->rows = $rows->all();
    }

    /** @return array<string, mixed>|null */
    public function find(string $key): ?array
    {
        return $this->all()[$this->resolve($key)] ?? null;
    }

    /**
     * Pairs of keys where one starts with the other, long enough to mean something.
     * Reported only: "Chen" sits inside other names and is a different firm.
     *
     * @return array<int, array{0: array<string, mixed>, 1: array<string, mixed>}>
     */
    public function possibleDuplicates(): array
    {
        $rows = collect($this->all())->sortKeys();
        $keys = $rows->keys()->filter(fn (string $key) => mb_strlen($key) >= 6)->values();
        $pairs = [];

        foreach ($keys as $i => $a) {
            foreach ($keys->slice($i + 1) as $b) {
                if (str_starts_with($a, $b) || str_starts_with($b, $a)) {
                    $pairs[] = [$rows[$a], $rows[$b]];
                }
            }
        }

        return $pairs;
    }

    public function forget(): void
    {
        $this->rows = null;
        $this->aliases = null;
    }

    /** @return array<string, mixed> */
    protected function emptyRow(string $key, string $name): array
    {
        return ['key' => $key, 'name' => $name, 'sources' => [], 'jobs' => [], 'city' => null, 'note' => null, 'aka' => []];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function summarize(array $row): array
    {
        $summaries = collect($row['jobs'])->map(fn (string $jobNumber) => $this->engine->jobSummary($jobNumber));
        $contracts = $summaries->pluck('contract')->map(fn (string $contract) => (float) $contract)->filter();

        $lastHours = collect($row['jobs'])
            ->map(fn (string $jobNumber) => array_key_last($this->engine->hoursByPeriod($jobNumber)))
            ->filter();

        $billed = (float) Money::sum($summaries->pluck('billed'));
        $outstanding = (float) Money::sum($summaries->pluck('outstanding'));

        return [
            ...$row,
            'sources' => array_keys($row['sources']),
            'aka' => collect($row['aka'])->map(fn (string $name, string $key) => ['key' => $key, 'name' => $name])->sortBy(fn (array $aka) => mb_strtolower($aka['name']))->values()->all(),
            'summaries' => $summaries->all(),
            'jobCount' => $summaries->count(),
            'liveCount' => $summaries->where('active', true)->count(),
            'contractMax' => $contracts->max(),
            'contractTotal' => $contracts->sum() ?: null,
            'billed' => $billed ?: null,
            'outstanding' => $outstanding ?: null,
            'hours' => round($summaries->sum('hoursTotal'), 1) ?: null,
            'lastActivity' => $summaries->pluck('lastPaymentDate')->merge($lastHours)->filter()->max(),
        ];
    }
}
