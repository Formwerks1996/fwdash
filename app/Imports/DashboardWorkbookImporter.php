<?php

namespace App\Imports;

use App\Enums\Phase;
use App\Models\MonthlyCost;
use App\Support\Money;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Spatie\SimpleExcel\SimpleExcelReader;

/*
 * Reads "Dashboard Data.xlsx" — the workbook the old dashboard exported — and
 * replaces the billing and office data with what it holds.
 *
 * Only source data is read. Derived sheets (Report for Jim) and derived columns
 * (Contracts Total..Last Payment, Month End BilledToDate/BilledThisMonth) are
 * recomputed by the engine, so they are used only to tell a typed dollar figure
 * from a percentage draw. Staff Status and Project Status are keyed on Mosaic
 * ids and are skipped.
 *
 * Rows that name an unknown job number are reported, never silently dropped.
 */
class DashboardWorkbookImporter
{
    /** The tables a workbook import replaces, children first. */
    public const ReplacedTables = [
        'job_hours', 'consultant_fees', 'payments', 'month_hourly', 'hour_lines', 'month_entries',
        'billing_periods', 'project_rate_overrides', 'project_phase_fees', 'projects',
        'consultant_rates', 'staff', 'rate_titles', 'phase_descriptions', 'software', 'hardware',
    ];

    protected const RequiredSheets = ['Contracts', 'Hours', 'Payments', 'Month End Data Entry', 'Consultants', 'Reference'];

    protected const HourlyTotalMarker = '(hourly total)';

    /** @var array<string, array<int|string, array<string, mixed>>> */
    protected array $rows = [];

    /** @var array<int, string> */
    protected array $warnings = [];

    /** @var array<string, true> */
    protected array $jobNumbers = [];

    /** @var array<string, string> */
    protected array $periods = [];

    /** @var array{lines: array<int, array<string, mixed>>, notes: array<int, array<string, string>>, months: int}|null Null when the workbook has no Costs sheet, so the last costs stay. */
    protected ?array $costs = null;

    public function __construct(protected string $path) {}

    public function import(): WorkbookImportResult
    {
        $this->read();

        $businessDevelopment = new BusinessDevelopmentSheets($this->path);
        $businessDevelopment->read();
        $businessDevelopmentCounts = [];

        DB::transaction(function () use ($businessDevelopment, &$businessDevelopmentCounts) {
            $preservedNotes = $this->preservedNotes();
            $preservedTags = $this->preservedTags();

            foreach (self::ReplacedTables as $table) {
                DB::table($table)->delete();
            }

            foreach ($this->rows as $table => $rows) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }

            $this->restoreNotes($preservedNotes);
            $this->restoreTags($preservedTags);

            if ($this->costs !== null) {
                DB::table('monthly_cost_notes')->delete();
                DB::table('monthly_costs')->delete();

                foreach (array_chunk($this->costs['lines'], 500) as $chunk) {
                    DB::table('monthly_costs')->insert($chunk);
                }

                DB::table('monthly_cost_notes')->insert($this->costs['notes']);
            }

            $businessDevelopmentCounts = $businessDevelopment->apply();
        });

        return new WorkbookImportResult(
            counts: [
                ...collect($this->rows)->map(fn (array $rows) => count($rows))->all(),
                ...($this->costs === null ? [] : ['monthly_costs' => $this->costs['months']]),
                ...$businessDevelopmentCounts,
            ],
            warnings: [...$this->warnings, ...$businessDevelopment->warnings],
        );
    }

    public function read(): void
    {
        $sheets = SimpleExcelReader::create($this->path, 'xlsx')->getSheetNames();
        $missing = array_diff(self::RequiredSheets, $sheets);

        if ($missing) {
            throw new WorkbookImportException(
                'This is not a Dashboard Data workbook: it has no '.implode(', ', $missing).' sheet.',
            );
        }

        $this->rows = array_fill_keys(array_reverse(self::ReplacedTables), []);
        $this->warnings = [];

        $this->readReference();
        $this->readContracts();
        $this->readBillingPeriods();
        $this->readMonthEnd();
        $this->readPayments();
        $this->readConsultants();
        $this->readHours();

        if (in_array('Software', $sheets, true)) {
            $this->readSoftware();
        }

        if (in_array('Hardware', $sheets, true)) {
            $this->readHardware();
        }

        $this->costs = null;

        if (in_array('Costs', $sheets, true)) {
            $this->readCosts();
        }

        $this->rows['billing_periods'] = collect($this->periods)
            ->map(fn (string $status, string $period) => [
                'period' => $period,
                'status' => $status,
                'closed_at' => $this->rows['billing_periods'][$period]['closed_at'] ?? null,
                'closed_by' => $this->rows['billing_periods'][$period]['closed_by'] ?? null,
            ])
            ->values()
            ->all();
    }

    protected function readReference(): void
    {
        $rows = $this->sheet('Reference', headers: false)->skip(1);

        $titleSeq = 0;

        foreach ($rows as $row) {
            if ($name = $this->text($row[0] ?? null)) {
                $this->rows['staff'][] = [
                    'name' => $name,
                    'charge_out' => $this->number($row[1] ?? null),
                    'status' => $this->text($row[2] ?? null),
                    'rate_title' => $this->text($row[3] ?? null),
                    'start_date' => $this->date($row[4] ?? null),
                    'finish_date' => $this->date($row[5] ?? null),
                    'current' => $this->yes($row[6] ?? null),
                ];
            }

            if ($title = $this->text($row[8] ?? null)) {
                $this->rows['rate_titles'][] = [
                    'title' => $title,
                    'default_charge_out' => $this->number($row[9] ?? null),
                    'seq' => $titleSeq++,
                ];
            }

            $phase = $this->text($row[11] ?? null);
            $description = $this->text($row[12] ?? null);

            if ($phase && $description) {
                $this->rows['phase_descriptions'][] = ['phase_code' => $phase, 'description' => $description];
            }

            $consultant = $this->text($row[15] ?? null);
            $period = $this->date($row[14] ?? null);

            if ($consultant && $period) {
                $this->rows['consultant_rates'][] = [
                    'period' => $period,
                    'consultant' => $consultant,
                    'exchange_rate' => $this->number($row[16] ?? null, 6),
                    'rate' => $this->number($row[17] ?? null),
                ];
            }
        }

        $this->rows['staff'] = collect($this->rows['staff'])->unique('name')->values()->all();
        $this->rows['phase_descriptions'] = collect($this->rows['phase_descriptions'])
            ->unique(fn (array $row) => "{$row['phase_code']}|{$row['description']}")
            ->values()
            ->all();
    }

    protected function readContracts(): void
    {
        $now = now();

        foreach ($this->sheet('Contracts') as $line => $row) {
            $jobNumber = $this->text($row['JobNumber'] ?? null);
            $jobName = $this->text($row['JobName'] ?? null);

            if (! $jobNumber && ! $jobName) {
                continue;
            }

            if (! $jobNumber || ! $jobName) {
                $this->warn("Contracts row {$line}: needs both a job number and a job name — skipped.");

                continue;
            }

            if (isset($this->jobNumbers[$jobNumber])) {
                $this->warn("Contracts row {$line}: job {$jobNumber} appears twice — kept the first.");

                continue;
            }

            $this->jobNumbers[$jobNumber] = true;

            $this->rows['projects'][] = [
                'job_number' => $jobNumber,
                'job_name' => $jobName,
                'developer' => $this->text($row['DeveloperName'] ?? null),
                'division' => $this->text($row['Division'] ?? null),
                'project_lead' => $this->text($row['Project Lead'] ?? null),
                'active' => $this->yes($row['Active'] ?? null),
                'andrew' => $this->yes($row['Andrew'] ?? null),
                'ca_per_month' => $this->number($row['Ca/Month'] ?? null),
                'ifc_date' => $this->date($row['IFC Date'] ?? null),
                'complete_date' => $this->date($row['Complete'] ?? null),
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach (Phase::codes() as $phase) {
                $fee = $this->number($row[$phase] ?? null);

                if ($fee !== null) {
                    $this->rows['project_phase_fees'][] = ['job_number' => $jobNumber, 'phase_code' => $phase, 'fee' => $fee, 'note' => null];
                }
            }

            foreach (['Junior', 'Intermediate', 'Senior', 'Principal'] as $title) {
                $rate = $this->number($row[$title] ?? null);

                if ($rate !== null && ! Money::isZero($rate)) {
                    $this->rows['project_rate_overrides'][] = ['job_number' => $jobNumber, 'rate_title' => $title, 'rate' => $rate];
                }
            }
        }
    }

    protected function readBillingPeriods(): void
    {
        if (! in_array('Billing Periods', SimpleExcelReader::create($this->path, 'xlsx')->getSheetNames(), true)) {
            return;
        }

        foreach ($this->sheet('Billing Periods') as $row) {
            $period = $this->date($row['Period'] ?? null);

            if (! $period) {
                continue;
            }

            $status = strtolower($this->text($row['Status'] ?? null) ?? 'open') === 'closed' ? 'closed' : 'open';

            $this->periods[$period] = $status;
            $this->rows['billing_periods'][$period] = [
                'closed_at' => $status === 'closed' ? $this->timestamp($row['Closed'] ?? null) : null,
                'closed_by' => $status === 'closed' ? $this->text($row['Closed By'] ?? null) : null,
            ];
        }
    }

    protected function readMonthEnd(): void
    {
        $phases = Phase::codes();

        foreach ($this->sheet('Month End Data Entry') as $line => $row) {
            $jobNumber = $this->text($row['JobNumber'] ?? null);
            $period = $this->date($row['Date'] ?? null);
            $phase = $this->text($row['Phase'] ?? null);

            if (! $jobNumber && ! $period && ! $phase) {
                continue;
            }

            if (! $this->knownJob($jobNumber, "Month End row {$line}") || ! $this->knownPeriod($period, "Month End row {$line}")) {
                continue;
            }

            if (in_array($phase, $phases, true)) {
                $this->readPhaseEntry($row, $jobNumber, $period, $phase);

                continue;
            }

            if ($phase !== Phase::Hours) {
                $this->warn("Month End row {$line}: unknown phase '{$phase}' — skipped.");

                continue;
            }

            $staff = $this->text($row['Staff'] ?? null);

            if ($staff === self::HourlyTotalMarker) {
                $this->rows['month_hourly'][] = [
                    'period' => $period,
                    'job_number' => $jobNumber,
                    'amount' => $this->number($row['Total'] ?? null) ?? '0',
                    'note' => null,
                ];

                continue;
            }

            $hours = $this->number($row['Hours'] ?? null, 4);
            $rate = $this->number($row['Rate'] ?? null);
            $total = $this->number($row['Total'] ?? null);

            $typedAmount = $total !== null
                && ($hours === null || $rate === null || $this->differs(Money::round(Money::mul($hours, $rate)), $total));

            $this->rows['hour_lines'][] = [
                'period' => $period,
                'job_number' => $jobNumber,
                'staff' => $staff,
                'hours' => $hours,
                'rate' => $rate,
                'amount' => $typedAmount ? $total : null,
                'rate_source' => 'imported',
                'note' => $this->text($row['Notes'] ?? null),
            ];
        }

        $this->rows['month_entries'] = collect($this->rows['month_entries'])
            ->keyBy(fn (array $entry) => "{$entry['period']}|{$entry['job_number']}|{$entry['phase_code']}")
            ->values()
            ->all();

        $this->rows['month_hourly'] = collect($this->rows['month_hourly'])
            ->keyBy(fn (array $override) => "{$override['period']}|{$override['job_number']}")
            ->values()
            ->all();
    }

    /** @param  array<int|string, mixed>  $row */
    protected function readPhaseEntry(array $row, string $jobNumber, string $period, string $phase): void
    {
        $fee = $this->number($row['Contract Amt'] ?? null);
        $pct = $this->number($row['%CompThisMonth'] ?? null, 8);
        $billed = $this->number($row['BilledThisMonth'] ?? null);
        $caMonthly = $this->number($row['CA Monthly'] ?? null);

        $amount = null;

        if ($phase === Phase::Ca->value && $caMonthly !== null) {
            $amount = $caMonthly;
        } elseif ($billed !== null && ($pct === null || $this->differs(Money::round(Money::mul($fee ?? 0, $pct)), $billed))) {
            $amount = $billed;
        }

        $this->rows['month_entries'][] = [
            'period' => $period,
            'job_number' => $jobNumber,
            'phase_code' => $phase,
            'pct_complete' => $pct,
            'amount' => $amount,
            'fee_at_entry' => $fee,
            'note' => $this->text($row['Notes'] ?? null),
        ];
    }

    protected function readPayments(): void
    {
        foreach ($this->sheet('Payments') as $line => $row) {
            $jobNumber = $this->text($row['Job Number'] ?? null);
            $paidOn = $this->date($row['Date'] ?? null);
            $amount = $this->number($row['Payment Amount'] ?? null);

            if (! $jobNumber && ! $paidOn && $amount === null) {
                continue;
            }

            if (! $this->knownJob($jobNumber, "Payments row {$line}")) {
                continue;
            }

            if (! $paidOn || $amount === null) {
                $this->warn("Payments row {$line}: needs a date and an amount — skipped.");

                continue;
            }

            $this->rows['payments'][] = [
                'paid_on' => $paidOn,
                'job_number' => $jobNumber,
                'amount' => $amount,
                'note' => $this->text($row['Note'] ?? null),
                'source' => 'import',
            ];
        }
    }

    protected function readConsultants(): void
    {
        foreach ($this->sheet('Consultants') as $line => $row) {
            $jobNumber = $this->text($row['JobNumber'] ?? null);
            $period = $this->date($row['Date'] ?? null);

            if (! $jobNumber && ! $period) {
                continue;
            }

            if (! $this->knownJob($jobNumber, "Consultants row {$line}") || ! $period) {
                continue;
            }

            $this->rows['consultant_fees'][] = [
                'period' => $period,
                'job_number' => $jobNumber,
                'consultant' => $this->text($row['Consultant'] ?? null),
                'hours' => $this->number($row['Hours'] ?? null, 4),
                'total' => $this->number($row['Total'] ?? null, 6),
                'note' => $this->text($row['Note'] ?? null),
            ];
        }
    }

    protected function readHours(): void
    {
        $sources = ['Mosaic' => 'live', 'Archived' => 'archived', 'Prior Hours' => 'prior'];
        $hours = [];

        foreach ($this->sheet('Hours') as $line => $row) {
            $jobNumber = $this->text($row['JobNumber'] ?? null);
            $period = $this->date($row['Date'] ?? null);
            $amount = $this->number($row['Hours'] ?? null, 4);

            if (! $jobNumber && ! $period && $amount === null) {
                continue;
            }

            if (! $this->knownJob($jobNumber, "Hours row {$line}") || ! $period || $amount === null) {
                continue;
            }

            $sourceLabel = $this->text($row['Source'] ?? null) ?? 'Mosaic';
            $source = $sources[$sourceLabel] ?? strtolower($sourceLabel);
            $key = "{$period}|{$jobNumber}|{$source}";

            $hours[$key] = [
                'period' => $period,
                'job_number' => $jobNumber,
                'hours' => isset($hours[$key]) ? Money::add($hours[$key]['hours'], $amount) : $amount,
                'source' => $source,
            ];
        }

        $this->rows['job_hours'] = array_values($hours);
    }

    protected function readSoftware(): void
    {
        $now = now();

        foreach ($this->sheet('Software') as $row) {
            $name = $this->text($row['Software'] ?? null);

            if (! $name) {
                continue;
            }

            $updated = $this->timestamp($row['Updated'] ?? null) ?? $now;

            $this->rows['software'][] = [
                'name' => $name,
                'division' => $this->text($row['Division'] ?? null),
                'price' => $this->number($row['Price'] ?? null),
                'currency' => strtoupper(substr($this->text($row['Currency'] ?? null) ?? 'CAD', 0, 3)),
                'cycle' => $this->text($row['Cycle'] ?? null) === 'Annual' ? 'Annual' : 'Monthly',
                'seats' => $this->integer($row['Seats'] ?? null),
                'seats_used' => $this->integer($row['Seats Used'] ?? null),
                'description' => $this->text($row['Description'] ?? null),
                'note' => $this->text($row['Note'] ?? null),
                'active' => ($this->text($row['Active'] ?? null) ?? 'Y') !== 'N',
                'created_at' => $updated,
                'updated_at' => $updated,
            ];
        }
    }

    protected function readHardware(): void
    {
        $now = now();

        foreach ($this->sheet('Hardware') as $row) {
            $record = [
                'cid' => $this->text($row['CID'] ?? null),
                'user_name' => $this->text($row['User'] ?? null),
                'name_future' => $this->text($row['Name (Future)'] ?? null),
                'dept' => $this->text($row['Dept'] ?? null),
                'replace_year' => $this->integer($row['Replacement Yr'] ?? null),
                'override' => $this->text($row['Override'] ?? null),
                'upgrade' => $this->text($row['Upgrade'] ?? null),
                'sophos' => $this->yesNoText($row['Sophos'] ?? null),
                'location' => $this->text($row['Location'] ?? null),
                'ip_address' => $this->text($row['IP Address'] ?? null),
                'purchase_date' => $this->date($row['Purchase Date'] ?? null),
                'purchase_price' => $this->number($row['Purchase Price'] ?? null),
                'serial' => $this->text($row['Serial'] ?? null),
                'model' => $this->text($row['Model'] ?? null),
                'screen_size' => $this->text($row['Screen Size'] ?? null),
                'os_version' => $this->text($row['Version'] ?? null),
                'cpu' => $this->text($row['CPU'] ?? null),
                'cpu_speed' => $this->text($row['CPU Speed'] ?? null),
                'cpu_cores' => $this->integer($row['CPU Cores'] ?? null),
                'memory' => $this->text($row['Memory'] ?? null),
                'mem_type' => $this->text($row['Mem Type'] ?? null),
                'mem_banks' => $this->text($row['Mem Banks'] ?? null),
                'graphics' => $this->text($row['Graphics'] ?? null),
                'hd1' => $this->text($row['HD 1'] ?? null),
                'hd2' => $this->text($row['HD 2'] ?? null),
                'notes' => $this->text($row['Notes'] ?? null),
                'retired' => $this->yes($row['Retired'] ?? null),
            ];

            if (! $record['cid'] && ! $record['user_name'] && ! $record['model']) {
                continue;
            }

            $this->rows['hardware'][] = [...$record, 'created_at' => $now, 'updated_at' => $now];
        }
    }

    /**
     * One row per calendar month. "Month" may be any date in it or text such as
     * "2026-01"; "Note" is optional; every other column is an expense category
     * (Payroll, Rent, Software…), kept in the sheet's column order.
     */
    protected function readCosts(): void
    {
        $this->costs = ['lines' => [], 'notes' => [], 'months' => 0];
        $seen = [];

        foreach ($this->sheet('Costs') as $line => $row) {
            $amounts = collect($row)
                ->except(['Month', 'Note'])
                ->filter(fn (mixed $value, int|string $category) => trim((string) $category) !== '')
                ->map(fn (mixed $value) => $this->number($value))
                ->filter(fn (?string $amount) => $amount !== null);

            if (($row['Month'] ?? null) === null && $amounts->isEmpty()) {
                continue;
            }

            $date = $this->date($row['Month'] ?? null);

            if (! $date) {
                $this->warn("Costs row {$line}: no month — skipped.");

                continue;
            }

            $month = substr($date, 0, 7);

            if (isset($seen[$month])) {
                $this->warn("Costs row {$line}: {$month} is already on an earlier row — skipped.");

                continue;
            }

            $seen[$month] = true;
            $this->costs['months']++;

            foreach (array_keys($row) as $seq => $category) {
                if ($amounts->has($category)) {
                    $name = trim((string) $category);

                    $this->costs['lines'][] = [
                        'month' => $month,
                        'category' => strcasecmp($name, MonthlyCost::Payroll) === 0 ? MonthlyCost::Payroll : $name,
                        'amount' => $amounts[$category],
                        'seq' => $seq,
                    ];
                }
            }

            if ($note = $this->text($row['Note'] ?? null)) {
                $this->costs['notes'][] = ['month' => $month, 'note' => $note];
            }
        }
    }

    /**
     * Which extra each hourly line was tagged to. The upload rewrites every
     * line, so a line is known again by its month, job, person, hours and note.
     *
     * @return array<string, int>
     */
    protected function preservedTags(): array
    {
        return DB::table('hour_lines')
            ->whereNotNull('extra_id')
            ->get()
            ->mapWithKeys(fn (object $line) => [$this->lineKey($line) => (int) $line->extra_id])
            ->all();
    }

    /** @param  array<string, int>  $tags */
    protected function restoreTags(array $tags): void
    {
        if ($tags === []) {
            return;
        }

        DB::table('hour_lines')
            ->get()
            ->filter(fn (object $line) => isset($tags[$this->lineKey($line)]))
            ->groupBy(fn (object $line) => $tags[$this->lineKey($line)])
            ->each(fn ($lines, int $extraId) => DB::table('hour_lines')->whereIn('id', $lines->pluck('id'))->update(['extra_id' => $extraId]));
    }

    protected function lineKey(object $line): string
    {
        return implode('|', [substr((string) $line->period, 0, 10), $line->job_number, $line->staff, Money::round($line->hours ?? 0, 4), $line->note]);
    }

    /**
     * Notes typed in this app that the workbook does not carry.
     *
     * @return array{projects: array<string, string>, fees: array<string, string>}
     */
    protected function preservedNotes(): array
    {
        return [
            'projects' => DB::table('projects')->whereNotNull('notes')->pluck('notes', 'job_number')->all(),
            'fees' => DB::table('project_phase_fees')
                ->whereNotNull('note')
                ->get()
                ->mapWithKeys(fn (object $fee) => ["{$fee->job_number}|{$fee->phase_code}" => $fee->note])
                ->all(),
        ];
    }

    /** @param array{projects: array<string, string>, fees: array<string, string>} $notes */
    protected function restoreNotes(array $notes): void
    {
        foreach ($notes['projects'] as $jobNumber => $note) {
            DB::table('projects')->where('job_number', $jobNumber)->update(['notes' => $note]);
        }

        foreach ($notes['fees'] as $key => $note) {
            [$jobNumber, $phase] = explode('|', $key, 2);

            if (! isset($this->jobNumbers[$jobNumber])) {
                continue;
            }

            DB::table('project_phase_fees')->updateOrInsert(
                ['job_number' => $jobNumber, 'phase_code' => $phase],
                ['note' => $note],
            );
        }
    }

    /** @return LazyCollection<int, array<mixed>> */
    protected function sheet(string $name, bool $headers = true): LazyCollection
    {
        $reader = SimpleExcelReader::create($this->path, 'xlsx')->fromSheetName($name);

        if (! $headers) {
            $reader->noHeaderRow();
        }

        return $reader
            ->getRows()
            ->mapWithKeys(fn (array $row, int $index) => [$index + ($headers ? 2 : 1) => $row]);
    }

    protected function knownJob(?string $jobNumber, string $where): bool
    {
        if ($jobNumber && isset($this->jobNumbers[$jobNumber])) {
            return true;
        }

        $this->warn("{$where}: job '".($jobNumber ?? '')."' is not on the Contracts sheet — skipped.");

        return false;
    }

    protected function knownPeriod(?string $period, string $where): bool
    {
        if (! $period) {
            $this->warn("{$where}: no date — skipped.");

            return false;
        }

        $this->periods[$period] ??= 'open';

        return true;
    }

    protected function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    protected function differs(string $left, string $right): bool
    {
        return abs(Money::toFloat(Money::sub($left, $right))) >= 0.005;
    }

    protected function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function number(mixed $value, int $places = 2): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(['$', ',', ' '], '', $value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        return Money::round($value, $places);
    }

    protected function integer(mixed $value): ?int
    {
        $number = $this->number($value, 0);

        return $number === null ? null : (int) $number;
    }

    protected function date(mixed $value): ?string
    {
        return ExcelValue::date($value);
    }

    protected function timestamp(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        $date = $this->date($value);

        return $date ? CarbonImmutable::parse($date) : null;
    }

    protected function yes(mixed $value): bool
    {
        return strtoupper(substr($this->text($value) ?? '', 0, 1)) === 'Y';
    }

    protected function yesNoText(mixed $value): ?string
    {
        $flag = strtoupper(substr($this->text($value) ?? '', 0, 1));

        return in_array($flag, ['Y', 'N'], true) ? $flag : null;
    }
}
