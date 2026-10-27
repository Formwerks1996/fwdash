<?php

namespace App\Livewire;

use App\Actions\Billing\SaveExtraAction;
use App\Actions\Billing\TagHourLinesAction;
use App\Billing\ProjectBurn;
use App\Enums\Permission;
use App\Models\Extra;
use App\Models\HourLine;
use App\Models\Project;
use App\Models\ProjectPhaseHours;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Extras: work beyond a job's contract phases, each at a set amount or hours
 * or open-ended hourly, and proposed, active, complete or rejected. Every hourly billing
 * line belongs to one; the lines not yet tagged wait in their own list. What
 * an extra has billed is the total of its tagged lines; Mosaic's hours on the
 * matching phase, at charge-out, show the time it has taken.
 */
/**
 * @property-read Collection<int, array<string, mixed>> $extras
 * @property-read Collection<int, array<string, mixed>> $rows
 * @property-read Collection<string, Collection<int, HourLine>> $untagged
 * @property-read array<string, string> $jobNames
 */
#[Title('Extras')]
class Extras extends Component
{
    protected const Views = ['active', 'proposed', 'complete', 'rejected', 'all', 'untagged'];

    #[Url]
    public string $view = 'active';

    #[Url]
    public string $search = '';

    #[Url]
    public string $job = '';

    /** The extra whose lines are open. */
    public ?int $open = null;

    /** The job whose untagged lines are open. */
    public ?string $openJob = null;

    public bool $editorOpen = false;

    public ?int $editingId = null;

    /** @var array{job_number: string, code: string, title: string, status: string, budget_amount: string, budget_hours: string, note: string} */
    public array $form = ['job_number' => '', 'code' => '', 'title' => '', 'status' => 'proposed', 'budget_amount' => '', 'budget_hours' => '', 'note' => ''];

    /** What the last auto-tag did. */
    public string $notice = '';

    /** @var array<int, bool> untagged line id => ticked */
    public array $picked = [];

    public function mount(): void
    {
        if (! in_array($this->view, self::Views, true)) {
            $this->view = 'active';
        }
    }

    /** @return array<string, string> */
    #[Computed]
    public function jobNames(): array
    {
        return Project::orderBy('job_number')->pluck('job_name', 'job_number')->all();
    }

    /**
     * Every extra with what its tagged lines add up to.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function extras(): Collection
    {
        $billed = DB::table('hour_lines')
            ->whereNotNull('extra_id')
            ->groupBy('extra_id')
            ->selectRaw('extra_id, count(*) as line_count, sum(coalesce(hours, 0)) as hours, sum(coalesce(amount, round(hours * rate, 2), 0)) as billed, max(period) as last_period')
            ->get()
            ->keyBy('extra_id');

        $rate = app(ProjectBurn::class)->planRate();
        $logged = ProjectPhaseHours::all()->keyBy(fn (ProjectPhaseHours $row) => "{$row->job_number}|{$row->mosaic_phase}");

        return Extra::orderBy('job_number')->orderBy('code')->orderBy('title')->get()
            ->map(function (Extra $extra) use ($billed, $logged, $rate) {
                $lines = $billed[$extra->id] ?? null;
                $mosaic = $extra->mosaic_phase ? ($logged["{$extra->job_number}|{$extra->mosaic_phase}"] ?? null) : null;
                $amount = $lines ? (float) $lines->billed : 0.0;
                $budget = $extra->budget_amount !== null ? (float) $extra->budget_amount : null;

                return [
                    'extra' => $extra,
                    'jobName' => $this->jobNames[$extra->job_number] ?? null,
                    'lineCount' => $lines ? (int) $lines->line_count : 0,
                    'hours' => $lines ? (float) $lines->hours : 0.0,
                    'billed' => $amount,
                    'lastPeriod' => $lines?->last_period,
                    'loggedHours' => $mosaic ? (float) $mosaic->hours : null,
                    'loggedValue' => $mosaic ? (float) $mosaic->charge_out + (float) $mosaic->unrated_hours * $rate : null,
                    'budget' => $budget,
                    'left' => $budget !== null ? $budget - $amount : null,
                    'hoursLeft' => $extra->budget_hours !== null ? (float) $extra->budget_hours - ($lines ? (float) $lines->hours : 0.0) : null,
                ];
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function rows(): Collection
    {
        $search = Str::lower(trim($this->search));

        return $this->extras
            ->when($this->view !== 'all', fn (Collection $rows) => $rows->filter(fn (array $row) => $row['extra']->status === $this->view))
            ->when($this->job !== '', fn (Collection $rows) => $rows->filter(fn (array $row) => $row['extra']->job_number === $this->job))
            ->when($search !== '', fn (Collection $rows) => $rows->filter(fn (array $row) => Str::contains(
                Str::lower(implode(' ', [$row['extra']->label(), $row['extra']->job_number, $row['jobName'], $row['extra']->note])),
                $search,
            )))
            ->sortBy([fn (array $a, array $b) => strcmp((string) $b['lastPeriod'], (string) $a['lastPeriod']), fn (array $a, array $b) => strcmp($a['extra']->job_number, $b['extra']->job_number)])
            ->values();
    }

    /** @return Collection<string, Collection<int, HourLine>> untagged lines by job, the most recent job first */
    #[Computed]
    public function untagged(): Collection
    {
        $search = Str::lower(trim($this->search));

        return HourLine::whereNull('extra_id')
            ->when($this->job !== '', fn ($query) => $query->where('job_number', $this->job))
            ->orderByDesc('period')
            ->orderBy('id')
            ->get()
            ->when($search !== '', fn (Collection $lines) => $lines->filter(fn (HourLine $line) => Str::contains(
                Str::lower(implode(' ', [$line->job_number, $this->jobNames[$line->job_number] ?? '', $line->staff, $line->note])),
                $search,
            )))
            ->groupBy('job_number');
    }

    /** @return Collection<int, Extra> the extras a line on this job can be tagged to */
    public function extrasFor(string $jobNumber): Collection
    {
        return $this->extras->pluck('extra')->where('job_number', $jobNumber)->values();
    }

    /** @return Collection<int, HourLine> */
    public function linesOf(int $extraId): Collection
    {
        return HourLine::where('extra_id', $extraId)->orderByDesc('period')->orderBy('id')->get();
    }

    public static function lineTotal(HourLine $line): float
    {
        return $line->amount !== null ? (float) $line->amount : round((float) $line->hours * (float) $line->rate, 2);
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, self::Views, true) ? $view : 'active';
        $this->open = null;
        $this->openJob = null;
        $this->picked = [];
    }

    public function toggle(int $extraId): void
    {
        $this->open = $this->open === $extraId ? null : $extraId;
    }

    public function toggleJob(string $jobNumber): void
    {
        $this->openJob = $this->openJob === $jobNumber ? null : $jobNumber;
        $this->picked = [];
    }

    public function create(?string $jobNumber = null, ?string $title = null): void
    {
        $this->authorize(Permission::EditBilling->value);

        $this->resetValidation();
        $this->editingId = null;
        $this->form = ['job_number' => $jobNumber ?? $this->job, 'code' => '', 'title' => $title ?? '', 'status' => 'active', 'budget_amount' => '', 'budget_hours' => '', 'note' => ''];
        $this->editorOpen = true;
    }

    public function edit(int $extraId): void
    {
        $this->authorize(Permission::EditBilling->value);

        $extra = Extra::findOrFail($extraId);

        $this->resetValidation();
        $this->editingId = $extra->id;
        $this->form = [
            'job_number' => $extra->job_number,
            'code' => (string) $extra->code,
            'title' => $extra->title,
            'status' => $extra->status,
            'budget_amount' => $extra->budget_amount !== null ? rtrim(rtrim((string) $extra->budget_amount, '0'), '.') : '',
            'budget_hours' => $extra->budget_hours !== null ? rtrim(rtrim((string) $extra->budget_hours, '0'), '.') : '',
            'note' => (string) $extra->note,
        ];
        $this->editorOpen = true;
    }

    public function save(SaveExtraAction $saveExtra, TagHourLinesAction $tagLines): void
    {
        $this->authorize(Permission::EditBilling->value);

        $this->resetValidation();
        $extra = $saveExtra->save($this->form, $this->editingId ? Extra::findOrFail($this->editingId) : null);

        // A new extra made from ticked untagged lines takes those lines.
        $picked = $this->pickedIds();

        if ($this->editingId === null && $picked !== []) {
            $tagLines->tag($picked, $extra);
            $this->picked = [];
        }

        $this->editorOpen = false;
        $this->open = $extra->id;
        unset($this->extras, $this->rows, $this->untagged);
    }

    public function delete(SaveExtraAction $saveExtra): void
    {
        $this->authorize(Permission::EditBilling->value);

        $saveExtra->delete(Extra::findOrFail($this->editingId));

        $this->editorOpen = false;
        $this->open = null;
        unset($this->extras, $this->rows, $this->untagged);
    }

    public function setStatus(int $extraId, string $status, SaveExtraAction $saveExtra): void
    {
        $this->authorize(Permission::EditBilling->value);

        $saveExtra->setStatus(Extra::findOrFail($extraId), $status);

        unset($this->extras, $this->rows);
    }

    /** Retags one line: to another extra on its job, or back to untagged with "". */
    public function tagLine(int $lineId, string $extraId, TagHourLinesAction $tagLines): void
    {
        $this->authorize(Permission::EditBilling->value);

        $tagLines->tag([$lineId], $extraId === '' ? null : Extra::findOrFail((int) $extraId));

        unset($this->extras, $this->rows, $this->untagged);
    }

    /** Tags the ticked lines on one job to an extra, or opens a new extra for them with "new". */
    public function tagPicked(string $jobNumber, string $extraId, TagHourLinesAction $tagLines): void
    {
        $this->authorize(Permission::EditBilling->value);

        $lines = HourLine::whereKey($this->pickedIds())->where('job_number', $jobNumber)->get();

        if ($lines->isEmpty()) {
            return;
        }

        if ($extraId === 'new') {
            $this->picked = $lines->mapWithKeys(fn (HourLine $line) => [$line->id => true])->all();
            $this->create($jobNumber, $lines->pluck('note')->filter()->countBy()->sortDesc()->keys()->first());

            return;
        }

        $tagLines->tag($lines->modelKeys(), Extra::findOrFail((int) $extraId));

        $this->picked = [];
        unset($this->extras, $this->rows, $this->untagged);
    }

    public function autoTag(TagHourLinesAction $tagLines): void
    {
        $this->authorize(Permission::EditBilling->value);

        $count = $tagLines->autoTag();

        $this->notice = $count
            ? trans_choice('Tagged :count line from its note.|Tagged :count lines from their notes.', $count)
            : __('No untagged line names an extra in its note.');
        unset($this->extras, $this->rows, $this->untagged);
    }

    public function render(): View
    {
        return view('livewire.extras', [
            'canEdit' => auth()->user()->can(Permission::EditBilling->value),
            'statuses' => Extra::Statuses,
            'untaggedCount' => HourLine::whereNull('extra_id')->count(),
        ]);
    }

    /** @return array<int, int> */
    protected function pickedIds(): array
    {
        return array_map('intval', array_keys(array_filter($this->picked)));
    }
}
