<?php

namespace App\Livewire\Timesheets;

use App\Actions\Timesheets\SaveTimesheetEntryAction;
use App\Enums\Permission;
use App\Enums\Phase;
use App\Enums\TimeCategory;
use App\Models\Project;
use App\Models\TimesheetEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * A person's week, laid out as Mosaic did: a row per project (and phase) or
 * kind of non-project time, a column per day, totals against 7.5 h a day.
 * Everyone edits only their own week; admins can open anyone's to read.
 */
/**
 * @property-read CarbonImmutable $weekStart
 * @property-read User $person
 * @property-read bool $isOwn
 * @property-read Collection<int, array{date: string, label: string, day: string, capacity: float, isToday: bool, isWeekend: bool}> $days
 * @property-read Collection<int, TimesheetEntry> $entries
 * @property-read Collection<int, array<string, mixed>> $rows
 * @property-read array<string, mixed> $totals
 */
#[Title('Timesheets')]
class Timesheet extends Component
{
    #[Url]
    public ?string $week = null;

    #[Url(as: 'person')]
    public ?int $personId = null;

    /** @var array<int, string> Rows added this week that have no hours yet. */
    public array $extraRows = [];

    public bool $addingRow = false;

    /** @var array{category: string, job_number: string, phase_code: string} */
    public array $newRow = ['category' => 'project', 'job_number' => '', 'phase_code' => ''];

    public bool $editingCell = false;

    /** @var array{row: string, date: string, hours: string, note: string} */
    public array $cell = ['row' => '', 'date' => '', 'hours' => '', 'note' => ''];

    public function mount(): void
    {
        $this->week = $this->normalizedWeek($this->week);
    }

    #[Computed]
    public function weekStart(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->week);
    }

    #[Computed]
    public function person(): User
    {
        $user = auth()->user();

        if ($this->personId === null || $this->personId === $user->id) {
            return $user;
        }

        abort_unless($user->can(Permission::ViewAllTimesheets->value), 403);

        return User::findOrFail($this->personId);
    }

    #[Computed]
    public function isOwn(): bool
    {
        return $this->person->is(auth()->user());
    }

    /** @return Collection<int, array{date: string, label: string, day: string, capacity: float, isToday: bool, isWeekend: bool}> */
    #[Computed]
    public function days(): Collection
    {
        return collect(range(0, 6))->map(function (int $offset) {
            $day = $this->weekStart->addDays($offset);

            return [
                'date' => $day->toDateString(),
                'label' => $day->format('D'),
                'day' => $day->format('j M'),
                'capacity' => $day->isWeekend() ? 0.0 : TimesheetEntry::DailyCapacity,
                'isToday' => $day->isToday(),
                'isWeekend' => $day->isWeekend(),
            ];
        });
    }

    /** @return Collection<int, TimesheetEntry> */
    #[Computed]
    public function entries(): Collection
    {
        return TimesheetEntry::query()
            ->where('user_id', $this->person->id)
            ->forWeek($this->weekStart)
            ->orderBy('work_date')
            ->get();
    }

    /** @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function rows(): Collection
    {
        $keys = $this->entries
            ->toBase()
            ->map(fn (TimesheetEntry $entry) => $entry->rowKey())
            ->merge($this->isOwn ? $this->extraRows : [])
            ->unique()
            ->values();

        $projects = Project::whereIn('job_number', $keys->map(fn (string $key) => TimesheetEntry::parseRowKey($key)['job_number'])->filter())
            ->pluck('job_name', 'job_number');

        return $keys
            ->map(function (string $key) use ($projects) {
                $row = TimesheetEntry::parseRowKey($key);
                $cells = $this->entries
                    ->filter(fn (TimesheetEntry $entry) => $entry->rowKey() === $key)
                    ->keyBy(fn (TimesheetEntry $entry) => $entry->work_date->toDateString());

                return [
                    'key' => $key,
                    'category' => $row['category'],
                    'jobNumber' => $row['job_number'],
                    'jobName' => $row['job_number'] ? ($projects[$row['job_number']] ?? __('Unknown project')) : null,
                    'phase' => $row['phase_code'],
                    'cells' => $cells,
                    'total' => (float) $cells->sum('hours'),
                ];
            })
            ->sortBy(fn (array $row) => [
                array_search($row['category'], TimeCategory::cases(), true),
                mb_strtolower((string) $row['jobName']),
                Phase::seqOf((string) $row['phase']),
            ])
            ->values();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function totals(): array
    {
        $byDay = $this->entries
            ->groupBy(fn (TimesheetEntry $entry) => $entry->work_date->toDateString())
            ->map(fn (Collection $entries) => (float) $entries->sum('hours'));

        $total = (float) $byDay->sum();
        $capacity = (float) $this->days->sum('capacity');

        return [
            'byDay' => $byDay->all(),
            'total' => $total,
            'capacity' => $capacity,
            'project' => (float) $this->entries->where('category', TimeCategory::Project)->sum('hours'),
            'timeOff' => (float) $this->entries->filter(fn (TimesheetEntry $entry) => $entry->category->isTimeOff())->sum('hours'),
        ];
    }

    public function previousWeek(): void
    {
        $this->goToWeek($this->weekStart->subWeek()->toDateString());
    }

    public function nextWeek(): void
    {
        $this->goToWeek($this->weekStart->addWeek()->toDateString());
    }

    public function goToWeek(?string $date): void
    {
        $this->week = $this->normalizedWeek($date);
        $this->extraRows = [];
        $this->resetErrorBag();
        $this->refreshWeek();
    }

    public function updatedPersonId(): void
    {
        $this->extraRows = [];
        $this->resetErrorBag();
        $this->refreshWeek();
    }

    public function saveHours(string $rowKey, string $date, ?string $hours, SaveTimesheetEntryAction $save): void
    {
        $this->ensureOwnWeek($date);
        $this->resetErrorBag();

        $current = $this->entryFor($rowKey, $date);

        if (SaveTimesheetEntryAction::parseHours($hours) === $current?->hours) {
            return;
        }

        $this->keepRow($rowKey);
        $save->execute(auth()->user(), $rowKey, $date, $hours, $current?->note);

        $this->refreshWeek();
    }

    public function editCell(string $rowKey, string $date): void
    {
        $this->ensureOwnWeek($date);
        $this->resetErrorBag();

        $entry = $this->entryFor($rowKey, $date);

        $this->cell = [
            'row' => $rowKey,
            'date' => $date,
            'hours' => $entry ? rtrim(rtrim((string) $entry->hours, '0'), '.') : '',
            'note' => (string) $entry?->note,
        ];
        $this->editingCell = true;
    }

    public function saveCell(SaveTimesheetEntryAction $save): void
    {
        $this->ensureOwnWeek($this->cell['date']);
        $this->resetErrorBag();

        $this->validate(['cell.note' => ['nullable', 'string', 'max:2000']], attributes: ['cell.note' => __('note')]);

        $this->keepRow($this->cell['row']);
        $save->execute(auth()->user(), $this->cell['row'], $this->cell['date'], $this->cell['hours'], $this->cell['note']);

        $this->editingCell = false;
        $this->refreshWeek();
    }

    public function addRow(): void
    {
        abort_unless($this->isOwn, 403);

        $validated = $this->validate([
            'newRow.category' => ['required', Rule::enum(TimeCategory::class)],
            'newRow.job_number' => [Rule::requiredIf($this->newRow['category'] === TimeCategory::Project->value), 'nullable', 'string', Rule::exists('projects', 'job_number')],
            'newRow.phase_code' => ['nullable', 'string', Rule::in([...Phase::codes(), Phase::Hours])],
        ], attributes: [
            'newRow.job_number' => __('project'),
            'newRow.phase_code' => __('phase'),
        ])['newRow'];

        $category = TimeCategory::from($validated['category']);
        $key = $category->needsProject()
            ? TimesheetEntry::makeRowKey($category, $validated['job_number'], $validated['phase_code'] ?: null)
            : TimesheetEntry::makeRowKey($category, null, null);

        $this->keepRow($key);

        $this->newRow = ['category' => TimeCategory::Project->value, 'job_number' => '', 'phase_code' => ''];
        $this->addingRow = false;
        unset($this->rows);
    }

    public function removeRow(string $rowKey, SaveTimesheetEntryAction $save): void
    {
        abort_unless($this->isOwn, 403);

        $this->entries
            ->filter(fn (TimesheetEntry $entry) => $entry->rowKey() === $rowKey)
            ->each(fn (TimesheetEntry $entry) => $save->delete($entry));

        $this->extraRows = array_values(array_diff($this->extraRows, [$rowKey]));
        $this->refreshWeek();
    }

    /** Mosaic's "copy rows from last week": the rows, not the hours. */
    public function copyPreviousRows(): void
    {
        abort_unless($this->isOwn, 403);

        TimesheetEntry::query()
            ->where('user_id', auth()->id())
            ->forWeek($this->weekStart->subWeek())
            ->get()
            ->each(fn (TimesheetEntry $entry) => $this->keepRow($entry->rowKey()));

        unset($this->rows);
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.timesheets.timesheet', [
            'categories' => TimeCategory::cases(),
            'phases' => [...Phase::codes(), Phase::Hours],
            'projectGroups' => $this->isOwn ? $this->projectGroups($user) : collect(),
            'people' => $user->can(Permission::ViewAllTimesheets->value)
                ? User::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    /**
     * The Add row picker: the projects Mosaic has you on first, then the rest.
     *
     * @return Collection<string, Collection<int, Project>>
     */
    protected function projectGroups(User $user): Collection
    {
        $assigned = $user->mosaicJobNumbers();

        $projects = Project::query()->orderByDesc('active')->orderBy('job_name')->get(['job_number', 'job_name', 'active']);

        return $projects
            ->groupBy(fn (Project $project) => match (true) {
                in_array($project->job_number, $assigned, true) => __('Your projects'),
                $project->active => __('Other active projects'),
                default => __('Inactive'),
            })
            ->sortBy(fn (Collection $group, string $label) => array_search($label, [__('Your projects'), __('Other active projects'), __('Inactive')], true))
            ->toBase();
    }

    protected function entryFor(string $rowKey, string $date): ?TimesheetEntry
    {
        return $this->entries->first(
            fn (TimesheetEntry $entry) => $entry->rowKey() === $rowKey && $entry->work_date->toDateString() === $date,
        );
    }

    protected function keepRow(string $rowKey): void
    {
        if (! in_array($rowKey, $this->extraRows, true)) {
            $this->extraRows[] = $rowKey;
        }
    }

    /** Writes are only ever to your own sheet, and only for a day on the week shown. */
    protected function ensureOwnWeek(string $date): void
    {
        abort_unless($this->isOwn, 403);
        abort_unless($this->days->contains('date', $date), 422);
    }

    protected function normalizedWeek(?string $date): string
    {
        try {
            $day = $date ? CarbonImmutable::parse($date) : CarbonImmutable::today();
        } catch (\Throwable) {
            $day = CarbonImmutable::today();
        }

        return $day->startOfWeek()->toDateString();
    }

    protected function refreshWeek(): void
    {
        unset($this->weekStart, $this->person, $this->isOwn, $this->days, $this->entries, $this->rows, $this->totals);
    }
}
