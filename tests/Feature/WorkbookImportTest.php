<?php

use App\Actions\Billing\SavePaymentAction;
use App\Actions\ImportWorkbookAction;
use App\Billing\BillingEngine;
use App\Models\AuditLog;
use App\Models\Extra;
use App\Models\HourLine;
use App\Models\MonthlyCost;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * A small Dashboard Data workbook: two projects, two months of billing, every
 * kind of Month End row, and a Report for Jim worked out by hand.
 *
 * @param  array<string, array<int, array<int, mixed>>>  $overrides
 */
function makeWorkbook(array $overrides = []): string
{
    $path = tempnam(sys_get_temp_dir(), 'workbook').'.xlsx';

    $date = fn (string $value) => new DateTimeImmutable($value);

    $sheets = [
        'Contracts' => [
            ['DeveloperName', 'JobName', 'JobNumber', 'Division', 'Active', 'Project Lead', 'Andrew', 'FS', 'SD', 'DD to RZ', 'RZP', 'DD to DP', 'DPP', 'BP', 'IFT', 'IFC', 'CA', 'Misc.', 'Ca/Month', 'Total', 'Hourly', 'Total w/ Extra', 'Total Payments', 'Last Payment Date', 'Last Payment Amount', 'IFC Date', 'Complete', 'Junior', 'Intermediate', 'Senior', 'Principal'],
            ['Acme', 'Alpha', '#100', 'MF', 'Y', 'Josh', 'N', 10000, 20000, null, null, null, null, null, null, null, 12000, null, 1500, 42000, 1450, 43450, 8000, $date('2026-06-15'), 8000, null, null, 120, null, null, null],
            ['Beta Corp', 'Beta', '#200', 'CHC', 'N', 'Nicola', 'N', null, 5000, null, null, null, null, null, null, null, null, null, null, 5000, 3000, 8000, 1000, $date('2026-07-01'), 1000, null, null, null, null, null, null],
        ],
        'Report for Jim' => [
            ['DeveloperName', 'JobName', 'Contract Amount', 'Billed to Date ($)', 'Billed To Date (%)', 'Balance', 'Balance Design', 'Balance CA', 'Consultants Cost', 'Hours (total)', '$ / hr', 'Last Payment Date', 'Last Payment Amount', 'Total Payments', 'Outstanding Balance', 'Active'],
            ['Acme', 'Alpha', 42000, 17950, 17950 / 42000, 24050, 13550, 10500, 80.12, 150, 119.13, $date('2026-06-15'), 8000, 8000, 9950, 'Y'],
            ['Beta Corp', 'Beta', 5000, 3000, 0.6, 2000, 2000, 0, 0, 10, 300, $date('2026-07-01'), 1000, 1000, 2000, 'N'],
        ],
        'Hours' => [
            ['Date', 'JobName', 'Hours', 'JobNumber', 'Source'],
            [$date('2022-04-30'), 'Alpha', 100, '#100', 'Prior Hours'],
            [$date('2026-05-31'), 'Alpha', 50, '#100', 'Mosaic'],
            [$date('2026-06-30'), 'Beta', 10, '#200', 'Mosaic'],
        ],
        'Payments' => [
            ['Date', 'Job Name', 'Payment Amount', 'Job Number', 'Note'],
            [$date('2026-06-15'), 'Alpha', 8000, '#100', 'cheque 12'],
            [$date('2026-07-01'), 'Beta', 1000, '#200', null],
            [$date('2026-07-02'), 'Ghost', 500, '#999', null],
        ],
        'Month End Data Entry' => [
            ['JobNumber', 'JobName', 'DeveloperName', 'Active', 'Division', 'Project Lead', 'Date', 'Phase', 'Contract Amt', 'Completion', 'BilledToDate', 'BilledThisMonth', '%CompThisMonth', 'CA Monthly', 'Staff', 'Hours', 'Rate', 'Total', 'Notes'],
            ['#100', 'Alpha', 'Acme', 'Y', 'MF', 'Josh', $date('2026-05-31'), 'SD', 20000, 0.5, 0, 10000, 0.5, null, null, null, null, null, null],
            ['#100', 'Alpha', 'Acme', 'Y', 'MF', 'Josh', $date('2026-05-31'), 'CA', 12000, 0.125, 0, 1500, null, 1500, null, null, null, null, null],
            ['#100', 'Alpha', 'Acme', 'Y', 'MF', 'Josh', $date('2026-05-31'), 'Hours', null, null, null, null, null, null, 'Adam', 10, 145, 1450, null],
            ['#100', 'Alpha', 'Acme', 'Y', 'MF', 'Josh', $date('2026-05-31'), 'Hours', null, null, null, null, null, null, 'Bob', 2.5, 100, 0, 'not billed'],
            ['#100', 'Alpha', 'Acme', 'Y', 'MF', 'Josh', $date('2026-06-30'), 'SD', 20000, 0.75, 10000, 5000, 0.25, null, null, null, null, null, null],
            ['#200', 'Beta', 'Beta Corp', 'N', 'CHC', 'Nicola', $date('2026-06-30'), 'Hours', null, null, null, null, null, null, '(hourly total)', null, null, 3000, 'Job-month hourly total'],
        ],
        'Consultants' => [
            ['Date', 'JobNumber', 'JobName', 'Consultant', 'Hours', 'Total', 'Note'],
            [$date('2026-05-31'), '#100', 'Alpha', 'SKETS', 4, 80.123456, null],
        ],
        'Reference' => [
            ['Staff', 'Charge Out', 'Status', 'Rate Title', 'Start', 'Finish', 'Current', null, 'Rate Title', 'Charge Out', null, 'PhaseCode', 'PhaseDescription', null, 'Date', 'Consultant', 'Exchange Rate', 'Rate'],
            ['Adam', 145, 'Design', 'Junior', $date('2022-06-13'), null, 'Y', null, 'Junior', 145, null, 'FS', 'Feasibility Study', null, $date('2026-05-31'), 'SKETS', null, 20],
        ],
        'Billing Periods' => [
            ['Period', 'Status', 'Closed', 'Closed By'],
            [$date('2026-06-30'), 'open', null, null],
            [$date('2026-05-31'), 'closed', $date('2026-06-10'), 'nathan'],
        ],
        'Software' => [
            ['Software', 'Division', 'Price', 'Currency', 'Cycle', 'Seats', 'Seats Used', 'Description', 'Note', 'Active', 'Updated'],
            ['Vectorworks', 'Everyone', 23520, 'CAD', 'Annual', 30, 27, null, null, 'Y', $date('2026-09-23')],
        ],
        'Hardware' => [
            ['CID', 'User', 'Name (Future)', 'Dept', 'Replacement Yr', 'Override', 'Upgrade', 'Sophos', 'Location', 'IP Address', 'Purchase Date', 'Purchase Price', 'Serial', 'Model', 'Screen Size', 'Version', 'CPU', 'CPU Speed', 'CPU Cores', 'Memory', 'Mem Type', 'Mem Banks', 'Graphics', 'HD 1', 'HD 2', 'Notes', 'Retired'],
            ['FWMB0018', 'Reception', null, 'MF', 2027, null, null, 'Y', null, null, $date('2023-01-10'), 3200, 'ABC123', 'MacBook Pro 16,4', '16"', null, 'Intel Core i9', null, 8, '32 GB', null, null, null, 'SSD 1TB', null, null, null],
        ],
        ...$overrides,
    ];

    $writer = SimpleExcelWriter::create($path)->noHeaderRow();
    $first = true;

    foreach ($sheets as $name => $rows) {
        $first
            ? $writer->nameCurrentSheet($name)
            : $writer->addNewSheetAndMakeItCurrent($name);

        $first = false;

        foreach ($rows as $row) {
            $writer->addRow($row);
        }
    }

    $writer->close();

    return $path;
}

it('imports every source sheet and reproduces the workbook report to the cent', function () {
    $import = app(ImportWorkbookAction::class)->execute(makeWorkbook(), 'Dashboard Data.xlsx');

    expect($import->counts)->toMatchArray([
        'projects' => 2,
        'project_phase_fees' => 4,
        'project_rate_overrides' => 1,
        'month_entries' => 3,
        'hour_lines' => 2,
        'month_hourly' => 1,
        'payments' => 2,
        'consultant_fees' => 1,
        'job_hours' => 3,
        'staff' => 1,
        'billing_periods' => 2,
        'software' => 1,
        'hardware' => 1,
    ]);

    expect($import->warnings)->toHaveCount(1)
        ->and($import->warnings[0])->toContain('#999');

    expect($import->reconciliation['mismatches'])->toBe([])
        ->and($import->reconciliation['checked'])->toBe(24);

    $alpha = app(BillingEngine::class)->jobSummary('#100');

    expect($alpha)->toMatchArray([
        'contract' => '42000.00',
        'billed' => '17950.00',
        'phaseBilled' => '16500.00',
        'hourlyBilled' => '1450.00',
        'balanceCA' => '10500.00',
        'consultantTotal' => '80.12',
        'hoursTotal' => 150.0,
        'monthlyHoursTotal' => 50.0,
        'ratePerHour' => 119.13,
    ]);
});

it('treats the CA monthly draw as a typed amount and percentages as percentages', function () {
    app(ImportWorkbookAction::class)->execute(makeWorkbook(), 'Dashboard Data.xlsx');

    $this->assertDatabaseHas('month_entries', ['job_number' => '#100', 'phase_code' => 'CA', 'amount' => 1500, 'pct_complete' => null]);
    $this->assertDatabaseHas('month_entries', ['job_number' => '#100', 'phase_code' => 'SD', 'period' => '2026-05-31', 'amount' => null]);
    $this->assertDatabaseHas('hour_lines', ['staff' => 'Bob', 'amount' => 0]);
    $this->assertDatabaseHas('hour_lines', ['staff' => 'Adam', 'amount' => null, 'rate' => 145]);
    $this->assertDatabaseHas('billing_periods', ['period' => '2026-05-31', 'status' => 'closed', 'closed_by' => 'nathan']);
});

it('replaces the data on a second upload but keeps project statuses', function () {
    $importer = app(ImportWorkbookAction::class);
    $importer->execute(makeWorkbook(), 'first.xlsx');

    ProjectStatus::create(['job_number' => '#100', 'status' => 'paused', 'set_by' => 'Nathan', 'set_at' => now()]);

    $importer->execute(makeWorkbook(), 'second.xlsx');

    $this->assertDatabaseCount('projects', 2);
    $this->assertDatabaseCount('payments', 2);
    $this->assertDatabaseHas('project_statuses', ['job_number' => '#100', 'status' => 'paused']);
});

it('rejects a workbook that is not a Dashboard Data export', function () {
    $path = makeWorkbook();
    $other = tempnam(sys_get_temp_dir(), 'other').'.xlsx';
    SimpleExcelWriter::create($other)->addRow(['a' => 1])->close();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('imports.store'), [
            'workbook' => new UploadedFile($other, 'Other.xlsx', null, null, true),
            'confirm' => '1',
        ])
        ->assertSessionHasErrors('workbook');

    $this->assertDatabaseCount('projects', 0);
});

it('uploads through the page for admins only', function () {
    $upload = fn () => [
        'workbook' => new UploadedFile(makeWorkbook(), 'Dashboard Data.xlsx', null, null, true),
        'confirm' => '1',
    ];

    $this->actingAs(User::factory()->manager()->create())
        ->post(route('imports.store'), $upload())
        ->assertForbidden();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('imports.store'), $upload())
        ->assertRedirect(route('imports.create'));

    $this->assertDatabaseCount('projects', 2);

    expect(AuditLog::where('action', 'import')->sole()->user_id)->toBe($admin->id);

    $this->actingAs($admin)
        ->get(route('imports.create'))
        ->assertSee('match the dashboard to the cent');
});

it('needs the confirmation box ticked', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('imports.store'), ['workbook' => new UploadedFile(makeWorkbook(), 'Dashboard Data.xlsx', null, null, true)])
        ->assertSessionHasErrors('confirm');
});

it('stores each project\'s financials, tied to the project', function () {
    app(ImportWorkbookAction::class)->execute(makeWorkbook(), 'Dashboard Data.xlsx');

    $this->assertDatabaseCount('project_financials', 2);

    $alpha = Project::find('#100')->financials;

    expect($alpha->only(['contract', 'billed', 'balance_ca', 'payments_total', 'outstanding', 'consultant_total', 'rate_per_hour']))->toBe([
        'contract' => '42000.00',
        'billed' => '17950.00',
        'balance_ca' => '10500.00',
        'payments_total' => '8000.00',
        'outstanding' => '9950.00',
        'consultant_total' => '80.12',
        'rate_per_hour' => '119.13',
    ])
        ->and($alpha->billed_by_phase['SD'])->toBe('15000.00')
        ->and($alpha->project->job_name)->toBe('Alpha');
});

it('re-works a project\'s financials when its billing changes', function () {
    app(ImportWorkbookAction::class)->execute(makeWorkbook(), 'Dashboard Data.xlsx');

    app(SavePaymentAction::class)->execute('#100', ['paid_on' => '2026-07-15', 'amount' => '950']);

    expect(Project::find('#100')->financials->outstanding)->toBe('9000.00');
});

it('refuses billing rows for a job that is not a project', function () {
    Payment::create(['paid_on' => '2026-07-15', 'job_number' => '#999', 'amount' => 100]);
})->throws(QueryException::class);

it('reads monthly costs from a Costs sheet, a category per column', function () {
    $import = app(ImportWorkbookAction::class)->execute(makeWorkbook(['Costs' => [
        ['Month', 'payroll', 'Rent', 'Software', 'Note'],
        [new DateTimeImmutable('2026-05-31'), 61000, 9500, 4750.5, null],
        ['2026-06', '$64,500', null, 4800, 'rent prepaid in May'],
        ['2026-06-15', 1, 1, 1, null],
        [null, 500, 500, 500, null],
    ]]), 'Dashboard Data.xlsx');

    expect(MonthlyCost::byMonth())->toBe([
        '2026-05' => ['payroll' => 61000.0, 'overhead' => 14250.5, 'total' => 75250.5],
        '2026-06' => ['payroll' => 64500.0, 'overhead' => 4800.0, 'total' => 69300.0],
    ])
        ->and(MonthlyCost::byCategory(['2026-05', '2026-06']))->toBe(['Payroll' => 125500.0, 'Rent' => 9500.0, 'Software' => 9550.5])
        ->and(DB::table('monthly_cost_notes')->pluck('note', 'month')->all())->toBe(['2026-06' => 'rent prepaid in May'])
        ->and($import->counts['monthly_costs'])->toBe(2)
        ->and($import->warnings)->toContain('Costs row 4: 2026-06 is already on an earlier row — skipped.')
        ->and($import->warnings)->toContain('Costs row 5: no month — skipped.');
});

it('keeps the last costs when a workbook has no Costs sheet', function () {
    MonthlyCost::create(['month' => '2026-05', 'category' => 'Payroll', 'amount' => 61000]);

    app(ImportWorkbookAction::class)->execute(makeWorkbook(), 'Dashboard Data.xlsx');

    expect(MonthlyCost::byMonth())->toHaveKey('2026-05');
});

it('keeps which extra each hourly line is tagged to across uploads', function () {
    $importer = app(ImportWorkbookAction::class);
    $importer->execute(makeWorkbook(), 'first.xlsx');

    $line = HourLine::firstOrFail();
    $extra = Extra::create(['job_number' => $line->job_number, 'title' => 'Construction Administration (Hourly)', 'status' => 'active']);
    $line->update(['extra_id' => $extra->id]);

    $importer->execute(makeWorkbook(), 'second.xlsx');

    expect(HourLine::where('extra_id', $extra->id)->count())->toBe(1)
        ->and(HourLine::whereKeyNot(HourLine::where('extra_id', $extra->id)->value('id'))->whereNotNull('extra_id')->count())->toBe(0);
});
