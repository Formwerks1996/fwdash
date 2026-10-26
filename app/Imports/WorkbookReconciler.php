<?php

namespace App\Imports;

use App\Billing\BillingEngine;
use Spatie\SimpleExcel\SimpleExcelReader;

/*
 * Proves the engine against the workbook it was imported from.
 *
 * "Report for Jim" is the old dashboard's own engine output, so after an
 * import every figure on it must come out of this engine to the cent.
 * Anything that does not is reported.
 */
class WorkbookReconciler
{
    protected const ReportColumns = [
        'Contract Amount' => 'contract',
        'Billed to Date ($)' => 'billed',
        'Balance' => 'balance',
        'Balance Design' => 'balanceDesign',
        'Balance CA' => 'balanceCA',
        'Consultants Cost' => 'consultantTotal',
        'Hours (total)' => 'hoursTotal',
        '$ / hr' => 'ratePerHour',
        'Last Payment Amount' => 'lastPaymentAmount',
        'Total Payments' => 'paymentsTotal',
        'Outstanding Balance' => 'outstanding',
    ];

    public function __construct(
        protected string $path,
        protected BillingEngine $engine,
    ) {}

    /** @return array{checked: int, mismatches: array<int, string>} */
    public function reconcile(): array
    {
        $reader = SimpleExcelReader::create($this->path, 'xlsx');

        if (! in_array('Report for Jim', $reader->getSheetNames(), true)) {
            return ['checked' => 0, 'mismatches' => []];
        }

        $jobsByName = collect($this->engine->jobs())->mapWithKeys(fn (array $job) => [$job['job_name'] => $job['job_number']]);

        $checked = 0;
        $mismatches = [];

        foreach (SimpleExcelReader::create($this->path, 'xlsx')->fromSheetName('Report for Jim')->getRows() as $row) {
            $jobName = trim((string) ($row['JobName'] ?? ''));

            if ($jobName === '') {
                continue;
            }

            $jobNumber = $jobsByName[$jobName] ?? null;

            if (! $jobNumber) {
                $mismatches[] = "Report for Jim: '{$jobName}' is not a project.";

                continue;
            }

            $summary = $this->engine->jobSummary($jobNumber);

            foreach (self::ReportColumns as $column => $key) {
                $checked++;

                $expected = $row[$column] ?? null;
                $actual = $summary[$key];

                if (! $this->matches($expected, $actual)) {
                    $mismatches[] = "{$jobName} — {$column}: workbook ".$this->show($expected).', dashboard '.$this->show($actual).'.';
                }
            }

            $checked++;

            $expectedDate = ExcelValue::date($row['Last Payment Date'] ?? null);

            if ($expectedDate !== $summary['lastPaymentDate']) {
                $mismatches[] = "{$jobName} — Last Payment Date: workbook ".($expectedDate ?? '—').', dashboard '.($summary['lastPaymentDate'] ?? '—').'.';
            }
        }

        return ['checked' => $checked, 'mismatches' => $mismatches];
    }

    protected function matches(mixed $expected, mixed $actual): bool
    {
        $expectedBlank = $expected === null || $expected === '';
        $actualBlank = $actual === null || $actual === '';

        if ($expectedBlank || $actualBlank) {
            return $expectedBlank === $actualBlank
                || abs((float) $expected) < 0.005 && abs((float) $actual) < 0.005;
        }

        return abs((float) $expected - (float) $actual) < 0.005;
    }

    protected function show(mixed $value): string
    {
        return $value === null || $value === '' ? '—' : (string) $value;
    }
}
