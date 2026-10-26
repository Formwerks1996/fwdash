<?php

namespace App\Livewire\MonthEnd;

use App\Actions\Billing\AddConsultantFeeAction;
use App\Actions\Billing\SaveHourLineAction;
use App\Actions\Billing\SaveMonthEntryAction;
use App\Actions\Billing\SetHourlyOverrideAction;
use App\Actions\Billing\SetPeriodStatusAction;
use App\Billing\BillingEngine;
use App\Enums\Permission;
use App\Enums\Phase;
use App\Models\HourLine;
use App\Support\Format;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * The screen that replaces the Excel month-end routine: about twenty phase
 * percentages and thirty-odd hourly lines a month. Every field saves as soon
 * as it is left, so a half-entered month is simply a month in progress.
 */
/**
 * @property-read array{period: string, status: string, billed: string, outstandingCount: int, jobs: array<int, array<string, mixed>>} $worklist
 * @property-read array<string, mixed>|null $grid
 * @property-read Collection<int, object> $staff
 * @property-read Collection<int, string> $consultants
 */
#[Title('Month end entry')]
class MonthEndEntry extends Component
{
    public ?string $period = null;

    #[Url(as: 'job')]
    public ?string $jobNumber = null;

    #[Url]
    public ?string $from = null;

    public string $filter = 'todo';

    public ?string $flash = null;

    public ?string $flashKey = null;

    /** @var array<string, mixed>|null the last removed hour line, whole, for Undo */
    public ?array $undo = null;

    /** @var array{staff: string, hours: string, rate: string, amount: string, note: string} */
    public array $newLine = ['staff' => '', 'hours' => '', 'rate' => '', 'amount' => '', 'note' => ''];

    public string $flatAmount = '';

    /** @var array{consultant: string, hours: string, total: string, note: string} */
    public array $newFee = ['consultant' => '', 'hours' => '', 'total' => '', 'note' => ''];

    public function mount(BillingEngine $engine, ?string $period = null): void
    {
        $this->period = $period !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $period) && BillingEngine::monthEnd($period) === $period
            ? $period
            : $engine->latestPeriod();

        if ($this->jobNumber !== null && $engine->job($this->jobNumber) === null) {
            $this->jobNumber = null;
        }

        if ($this->jobNumber !== null) {
            $this->filter = 'all';
        }

        $this->resetFlatAmount();
    }

    public function changePeriod(string $period): void
    {
        $this->redirectRoute('monthEnd.entry', ['period' => $period, 'from' => $this->from], navigate: true);
    }

    public function openJob(string $jobNumber): void
    {
        abort_if(app(BillingEngine::class)->job($jobNumber) === null, 404);

        $this->jobNumber = $jobNumber;
        $this->undo = null;
        $this->flash = null;
        $this->flashKey = null;
        $this->newLine = ['staff' => '', 'hours' => '', 'rate' => '', 'amount' => '', 'note' => ''];
        $this->resetErrorBag();
        unset($this->grid);
        $this->resetFlatAmount();
    }

    public function savePct(string $phase, ?string $value, SaveMonthEntryAction $saveEntry): void
    {
        $this->authorize(Permission::EditBilling->value);

        $number = $this->parseNumber($value, __('The percentage'));

        if ($number !== null && (float) $number < 0) {
            throw ValidationException::withMessages(['billing' => __('A percentage cannot be negative.')]);
        }

        $entry = $this->entry($phase);

        $saveEntry->execute(
            (string) $this->period,
            (string) $this->jobNumber,
            $phase,
            pct: $number === null ? null : bcdiv($number, '100', 8),
            amount: null,
            note: $entry['note'] ?? null,
        );

        $this->saved($number === null ? __(':phase cleared', ['phase' => $phase]) : "{$phase} {$number}%", "phase:{$phase}");
    }

    public function saveAmount(string $phase, ?string $value, SaveMonthEntryAction $saveEntry): void
    {
        $this->authorize(Permission::EditBilling->value);

        $number = $this->parseNumber($value, __('The amount'));
        $entry = $this->entry($phase);

        $saveEntry->execute(
            (string) $this->period,
            (string) $this->jobNumber,
            $phase,
            pct: null,
            amount: $number,
            note: $entry['note'] ?? null,
        );

        $this->saved($number === null ? __(':phase cleared', ['phase' => $phase]) : "{$phase} ".Format::money($number), "phase:{$phase}");
    }

    public function saveNote(string $phase, ?string $value, SaveMonthEntryAction $saveEntry): void
    {
        $this->authorize(Permission::EditBilling->value);

        $entry = $this->entry($phase);
        $note = trim((string) $value) ?: null;

        if ($entry === null && $note === null) {
            return;
        }

        $saveEntry->execute(
            (string) $this->period,
            (string) $this->jobNumber,
            $phase,
            pct: $entry && $entry['pct_complete'] !== null ? (string) $entry['pct_complete'] : null,
            amount: $entry && $entry['amount'] !== null ? (string) $entry['amount'] : null,
            note: $note,
        );

        $this->saved(__(':phase note saved', ['phase' => $phase]), "phase:{$phase}");
    }

    public function addLine(SaveHourLineAction $saveLine): void
    {
        $this->authorize(Permission::EditBilling->value);

        if (trim($this->newLine['staff']) === '' || (trim($this->newLine['hours']) === '' && trim($this->newLine['amount']) === '')) {
            throw ValidationException::withMessages(['billing' => __('Pick a staff member and enter hours.')]);
        }

        $saveLine->execute((string) $this->period, (string) $this->jobNumber, $this->newLine);

        $staff = $this->newLine['staff'];
        $this->newLine = ['staff' => '', 'hours' => '', 'rate' => '', 'amount' => '', 'note' => ''];
        $this->undo = null;

        $this->saved(__(':staff added', ['staff' => $staff]), 'hours');
    }

    public function updateLine(int $lineId, string $field, ?string $value, SaveHourLineAction $saveLine): void
    {
        $this->authorize(Permission::EditBilling->value);

        abort_unless(in_array($field, ['staff', 'hours', 'rate', 'amount', 'note'], true), 422);

        $line = $this->line($lineId);

        $attributes = [
            'staff' => $line->staff,
            'hours' => $line->hours === null ? null : (string) $line->hours,
            'rate' => $line->rate === null ? null : (string) $line->rate,
            'amount' => $line->amount === null ? null : (string) $line->amount,
            'note' => $line->note,
            $field => $value,
        ];

        $saveLine->execute((string) $this->period, (string) $this->jobNumber, $attributes, $line);

        $this->saved(__(':staff’s line updated', ['staff' => $line->staff ?? __('the')]), 'hours');
    }

    public function deleteLine(int $lineId, SaveHourLineAction $saveLine): void
    {
        $this->authorize(Permission::EditBilling->value);

        $line = $this->line($lineId);
        $snapshot = $line->snapshot();

        $saveLine->delete($line);

        $this->undo = $snapshot;
        $this->saved(__('line removed'), 'hours');
    }

    public function undoLine(SaveHourLineAction $saveLine): void
    {
        $this->authorize(Permission::EditBilling->value);

        if ($this->undo === null) {
            return;
        }

        $saveLine->restore($this->undo);

        $this->undo = null;
        $this->saved(__('line restored'), 'hours');
    }

    public function setOverride(SetHourlyOverrideAction $setOverride): void
    {
        $this->authorize(Permission::EditBilling->value);

        $amount = $this->parseNumber($this->flatAmount, __('The flat amount'));

        $setOverride->execute((string) $this->period, (string) $this->jobNumber, $amount);

        $this->saved($amount === null ? __('flat amount cleared') : __('flat hourly amount set'), 'hours');
    }

    public function clearOverride(SetHourlyOverrideAction $setOverride): void
    {
        $this->authorize(Permission::EditBilling->value);

        $setOverride->execute((string) $this->period, (string) $this->jobNumber, null);

        $this->saved(__('flat amount cleared'), 'hours');
    }

    public function addConsultantFee(AddConsultantFeeAction $addFee): void
    {
        $this->authorize(Permission::EditBilling->value);

        $addFee->execute(
            (string) $this->period,
            (string) $this->jobNumber,
            $this->newFee['consultant'],
            $this->parseNumber($this->newFee['hours'], __('Hours')),
            $this->parseNumber($this->newFee['total'], __('The total')),
            $this->newFee['note'],
        );

        $consultant = $this->newFee['consultant'];
        $this->newFee = ['consultant' => '', 'hours' => '', 'total' => '', 'note' => ''];

        $this->saved(__(':consultant fee added', ['consultant' => $consultant]), 'consultants');
    }

    public function closePeriod(SetPeriodStatusAction $setPeriodStatus): void
    {
        $this->authorize(Permission::EditBilling->value);

        $setPeriodStatus->execute((string) $this->period, 'closed');

        $this->saved(__(':month closed', ['month' => Format::month($this->period)]), 'period');
    }

    public function reopenPeriod(SetPeriodStatusAction $setPeriodStatus): void
    {
        $this->authorize(Permission::EditBilling->value);

        $setPeriodStatus->execute((string) $this->period, 'open');

        $this->saved(__(':month reopened', ['month' => Format::month($this->period)]), 'period');
    }

    /** @return array{period: string, status: string, billed: string, outstandingCount: int, jobs: array<int, array<string, mixed>>} */
    #[Computed]
    public function worklist(): array
    {
        return app(BillingEngine::class)->worklist((string) $this->period);
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function grid(): ?array
    {
        if ($this->jobNumber === null || $this->period === null) {
            return null;
        }

        return app(BillingEngine::class)->periodGrid($this->period, $this->jobNumber);
    }

    /** @return Collection<int, object> */
    #[Computed]
    public function staff(): Collection
    {
        return DB::table('staff')
            ->select(['name', 'rate_title'])
            ->where('current', true)
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, string> */
    #[Computed]
    public function consultants(): Collection
    {
        return DB::table('consultant_rates')
            ->whereNotNull('consultant')
            ->distinct()
            ->orderBy('consultant')
            ->pluck('consultant');
    }

    public function render(BillingEngine $engine): View
    {
        $jobs = collect($this->worklist['jobs'])
            ->filter(fn (array $job) => match ($this->filter) {
                'done' => $job['done'],
                'all' => true,
                default => ! $job['done'],
            })
            ->values();

        return view('livewire.month-end.entry', [
            'periods' => array_reverse($engine->periods()),
            'engine' => $engine,
            'closed' => $engine->periodStatus((string) $this->period) === 'closed',
            'canEdit' => auth()->user()?->can(Permission::EditBilling->value) ?? false,
            'listed' => $jobs,
            'hoursLogged' => $this->jobNumber && $this->period ? $engine->hoursIn($this->jobNumber, $this->period) : 0.0,
            'consultantFees' => $this->jobNumber && $this->period
                ? DB::table('consultant_fees')->where('job_number', $this->jobNumber)->where('period', $this->period)->orderBy('id')->get()
                : collect(),
            'phases' => Phase::codes(),
        ]);
    }

    /** @return array<string, mixed>|null */
    protected function entry(string $phase): ?array
    {
        if (! Phase::tryFrom($phase)) {
            throw ValidationException::withMessages(['billing' => __('Unknown phase :phase.', ['phase' => $phase])]);
        }

        if ($this->jobNumber === null || app(BillingEngine::class)->job($this->jobNumber) === null) {
            throw ValidationException::withMessages(['billing' => __('Unknown job :job.', ['job' => (string) $this->jobNumber])]);
        }

        return app(BillingEngine::class)->entryAt((string) $this->period, $this->jobNumber, $phase);
    }

    protected function line(int $lineId): HourLine
    {
        return HourLine::where('job_number', $this->jobNumber)->findOrFail($lineId);
    }

    protected function parseNumber(?string $value, string $label): ?string
    {
        $value = trim(str_replace(['$', ',', '%', ' '], '', (string) $value));

        if ($value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['billing' => __(':label must be a number.', ['label' => $label])]);
        }

        return $value;
    }

    protected function saved(string $message, string $key): void
    {
        app(BillingEngine::class)->load();
        unset($this->worklist, $this->grid);

        $this->resetErrorBag();
        $this->flash = $message;
        $this->flashKey = $key;
        $this->resetFlatAmount();
    }

    protected function resetFlatAmount(): void
    {
        $override = $this->jobNumber && $this->period
            ? app(BillingEngine::class)->hourlyOverride($this->jobNumber, $this->period)
            : null;

        $this->flatAmount = $override === null ? '' : (string) $override;
    }
}
