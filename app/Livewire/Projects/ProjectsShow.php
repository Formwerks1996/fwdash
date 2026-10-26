<?php

namespace App\Livewire\Projects;

use App\Actions\Billing\SaveMonthEntryAction;
use App\Actions\Billing\SavePaymentAction;
use App\Actions\Billing\SaveProjectAction;
use App\Actions\Billing\SetPhaseFeeAction;
use App\Actions\Team\AssignProjectAction;
use App\Billing\BillingEngine;
use App\Enums\Permission;
use App\Models\MonthEntry;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\TeamMember;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * @property-read Project $project
 * @property-read array<string, mixed> $summary
 * @property-read array<int, array<string, mixed>> $fees
 * @property-read array<int, array<string, mixed>> $months
 */
class ProjectsShow extends Component
{
    public string $jobNumber;

    public ?string $openMonth = null;

    public bool $editingDetails = false;

    /** @var array<string, mixed> */
    public array $details = [];

    /** @var array{paid_on: string, amount: string, note: string} */
    public array $newPayment = ['paid_on' => '', 'amount' => '', 'note' => ''];

    public function mount(string $jobNumber): void
    {
        $this->jobNumber = $jobNumber;

        abort_if($this->engine()->job($jobNumber) === null, 404);
    }

    #[Computed]
    public function project(): Project
    {
        return Project::findOrFail($this->jobNumber);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function summary(): array
    {
        return $this->engine()->jobSummary($this->jobNumber);
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function fees(): array
    {
        return $this->engine()->jobFeeDetail($this->jobNumber);
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function months(): array
    {
        return $this->engine()->jobHistory($this->jobNumber);
    }

    public function toggleMonth(string $period): void
    {
        $this->openMonth = $this->openMonth === $period ? null : $period;
    }

    public function editDetails(): void
    {
        $this->authorize(Permission::EditBilling->value);

        $this->details = $this->project->only(['job_name', 'developer', 'division', 'project_lead', 'ca_per_month', 'ifc_date', 'active']);
        $this->details['ifc_date'] = $this->project->ifc_date?->format('Y-m-d');
        $this->editingDetails = true;
    }

    public function saveDetails(SaveProjectAction $saveProject): void
    {
        $this->authorize(Permission::EditBilling->value);

        $validated = $this->validate([
            'details.job_name' => ['required', 'string', 'max:255'],
            'details.developer' => ['nullable', 'string', 'max:255'],
            'details.division' => ['nullable', 'string', 'max:20'],
            'details.project_lead' => ['nullable', 'string', 'max:255'],
            'details.ca_per_month' => ['nullable', 'numeric', 'min:0'],
            'details.ifc_date' => ['nullable', 'date'],
            'details.active' => ['boolean'],
        ])['details'];

        $saveProject->execute($validated, $this->project);

        $this->editingDetails = false;
        $this->refreshFigures();
    }

    public function saveFee(string $phase, ?string $value, SetPhaseFeeAction $setFee): void
    {
        $this->authorize(Permission::EditBilling->value);

        $fee = $this->parseNumber($value, 'fee');

        $setFee->execute($this->project, $phase, $fee);

        $this->refreshFigures();
    }

    public function saveEntry(string $period, string $phase, string $field, ?string $value, SaveMonthEntryAction $saveEntry): void
    {
        $this->authorize(Permission::EditBilling->value);

        $number = $this->parseNumber($value, 'entry');

        $saveEntry->execute(
            $period,
            $this->jobNumber,
            $phase,
            pct: $field === 'pct' && $number !== null ? (string) ((float) $number / 100) : null,
            amount: $field === 'amount' ? $number : null,
            note: MonthEntry::where(['period' => $period, 'job_number' => $this->jobNumber, 'phase_code' => $phase])->value('note'),
        );

        $this->refreshFigures();
    }

    public function addPayment(SavePaymentAction $savePayment): void
    {
        $this->authorize(Permission::EditBilling->value);

        $validated = $this->validate([
            'newPayment.paid_on' => ['required', 'date'],
            'newPayment.amount' => ['required', 'numeric'],
            'newPayment.note' => ['nullable', 'string', 'max:500'],
        ], [
            'newPayment.paid_on.required' => __('A payment needs a date and an amount.'),
            'newPayment.amount.required' => __('A payment needs a date and an amount.'),
        ])['newPayment'];

        $savePayment->execute($this->jobNumber, $validated);

        $this->newPayment = ['paid_on' => '', 'amount' => '', 'note' => ''];
        $this->refreshFigures();
    }

    public function updatePayment(int $paymentId, string $field, ?string $value, SavePaymentAction $savePayment): void
    {
        $this->authorize(Permission::EditBilling->value);

        abort_unless(in_array($field, ['paid_on', 'amount', 'note'], true), 422);

        $payment = Payment::where('job_number', $this->jobNumber)->findOrFail($paymentId);

        $value = match ($field) {
            'amount' => $this->parseNumber($value, 'payment') ?? '0',
            'paid_on' => $value ?: $payment->paid_on->format('Y-m-d'),
            default => $value ?: null,
        };

        $savePayment->execute($this->jobNumber, [$field => $value], $payment);

        $this->refreshFigures();
    }

    public function assign(string $memberName, AssignProjectAction $assignProject): void
    {
        $this->authorize(Permission::EditTeam->value);

        $assignProject->execute($this->jobNumber, $memberName);
    }

    public function release(string $memberName, AssignProjectAction $assignProject): void
    {
        $this->authorize(Permission::EditTeam->value);

        $assignProject->release($this->jobNumber, $memberName);
    }

    public function deletePayment(int $paymentId, SavePaymentAction $savePayment): void
    {
        $this->authorize(Permission::EditBilling->value);

        $savePayment->delete(Payment::where('job_number', $this->jobNumber)->findOrFail($paymentId));

        $this->refreshFigures();
    }

    public function render(): View
    {
        return view('livewire.projects.show', [
            'payments' => Payment::where('job_number', $this->jobNumber)->orderByDesc('paid_on')->orderByDesc('id')->get(),
            'canEdit' => auth()->user()->can(Permission::EditBilling->value),
            'canAssign' => auth()->user()->can(Permission::EditTeam->value),
            'assigned' => ProjectAssignment::where('job_number', $this->jobNumber)->orderBy('member_name')->pluck('member_name'),
            'team' => TeamMember::active()->orderBy('name')->pluck('name'),
            'chart' => $this->chartData(),
        ])->title($this->summary['jobName']);
    }

    /** @return array{labels: array<int, string>, billed: array<int, float>, contract: float} */
    protected function chartData(): array
    {
        $months = array_reverse($this->months);

        return [
            'labels' => array_map(fn (array $month) => date('M y', strtotime($month['period'])), $months),
            'billed' => array_map(fn (array $month) => (float) $month['billedToDate'], $months),
            'contract' => (float) $this->summary['contract'],
        ];
    }

    protected function parseNumber(?string $value, string $field): ?string
    {
        $value = trim(str_replace(['$', ',', '%', ' '], '', (string) $value));

        if ($value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw ValidationException::withMessages([$field => __('":value" is not a number.', ['value' => $value])]);
        }

        return $value;
    }

    protected function refreshFigures(): void
    {
        $this->engine()->load();

        unset($this->project, $this->summary, $this->fees, $this->months);
    }

    protected function engine(): BillingEngine
    {
        return app(BillingEngine::class);
    }
}
