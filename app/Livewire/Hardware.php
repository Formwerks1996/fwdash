<?php

namespace App\Livewire;

use App\Enums\Permission;
use App\Models\Hardware as HardwareModel;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read Collection<int, HardwareModel> $computers
 * @property-read Collection<int, HardwareModel> $live
 * @property-read array<string, array<int, string>> $repeatedSerials
 * @property-read Collection<string, Collection<int, HardwareModel>> $groups
 */
#[Title('Hardware')]
class Hardware extends Component
{
    protected const Fields = [
        'cid', 'user_name', 'name_future', 'dept', 'location', 'purchase_date', 'purchase_price', 'replace_year', 'override',
        'upgrade', 'sophos', 'ip_address', 'serial', 'model', 'screen_size', 'os_version', 'cpu', 'cpu_speed', 'cpu_cores',
        'memory', 'mem_type', 'mem_banks', 'graphics', 'hd1', 'hd2', 'notes', 'retired',
    ];

    #[Url]
    public string $kind = 'all';

    #[Url]
    public string $search = '';

    #[Url]
    public string $dept = '';

    #[Url]
    public bool $showRetired = false;

    public bool $editorOpen = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @return Collection<int, HardwareModel> */
    #[Computed]
    public function computers(): Collection
    {
        return HardwareModel::orderBy('retired')->orderByRaw('lower(cid)')->get();
    }

    /** @return Collection<int, HardwareModel> */
    #[Computed]
    public function live(): Collection
    {
        return $this->computers->where('retired', false)->values();
    }

    /** @return array<string, array<int, string>> serial => CIDs, for serials on more than one row */
    #[Computed]
    public function repeatedSerials(): array
    {
        return $this->computers
            ->filter(fn (HardwareModel $computer) => filled($computer->serial))
            ->groupBy(fn (HardwareModel $computer) => Str::upper(trim($computer->serial)))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->map(fn (Collection $group) => $group->pluck('cid')->all())
            ->all();
    }

    /** @return Collection<string, Collection<int, HardwareModel>> */
    #[Computed]
    public function groups(): Collection
    {
        $search = Str::lower(trim($this->search));

        return $this->computers
            ->when(! $this->showRetired, fn (Collection $rows) => $rows->where('retired', false))
            ->when($this->kind !== 'all', fn (Collection $rows) => $rows->filter(fn (HardwareModel $row) => $row->kind() === $this->kind))
            ->when($this->dept !== '', fn (Collection $rows) => $rows->where('dept', $this->dept))
            ->when($search !== '', fn (Collection $rows) => $rows->filter(fn (HardwareModel $row) => Str::contains(
                Str::lower(implode(' ', [$row->cid, $row->user_name, $row->name_future, $row->dept, $row->model, $row->serial, $row->cpu, $row->notes, $row->upgrade, $row->location])),
                $search,
            )))
            ->groupBy(fn (HardwareModel $row) => $row->kind())
            ->sortBy(fn (Collection $rows, string $kind) => array_search($kind, array_keys(HardwareModel::Kinds), true));
    }

    public function create(): void
    {
        $this->authorize(Permission::EditOffice->value);

        $this->editingId = null;
        $this->form = array_fill_keys(self::Fields, '');
        $this->form['retired'] = false;
        $this->resetValidation();
        $this->editorOpen = true;
    }

    public function edit(int $id): void
    {
        $this->authorize(Permission::EditOffice->value);

        $computer = HardwareModel::findOrFail($id);

        $this->editingId = $id;
        $this->form = collect(self::Fields)
            ->mapWithKeys(fn (string $field) => [$field => $computer->{$field} ?? ''])
            ->all();
        $this->form['purchase_date'] = $computer->purchase_date?->format('Y-m-d') ?? '';
        $this->resetValidation();
        $this->editorOpen = true;
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorize(Permission::EditOffice->value);

        $this->validate([
            'form.purchase_date' => ['nullable', 'date'],
            'form.purchase_price' => ['nullable', 'numeric', 'min:0'],
            'form.replace_year' => ['nullable', 'integer', 'between:2000,2100'],
            'form.cpu_cores' => ['nullable', 'integer', 'min:0'],
            'form.sophos' => ['nullable', 'in:Y,N'],
            'form.retired' => ['boolean'],
        ]);

        $values = collect($this->form)
            ->only(self::Fields)
            ->map(fn (mixed $value) => is_string($value) && trim($value) === '' ? null : (is_string($value) ? trim($value) : $value))
            ->put('retired', (bool) $this->form['retired'])
            ->all();

        if (! $this->editingId && ! $values['cid'] && ! $values['user_name'] && ! $values['model']) {
            throw ValidationException::withMessages(['form.cid' => __('A computer needs at least a CID, a user or a model.')]);
        }

        DB::transaction(function () use ($values, $audit) {
            if (! $this->editingId) {
                $computer = HardwareModel::create($values);

                $audit->log('hardware.add', 'hardware', $computer->id, after: array_filter($values, fn (mixed $value) => $value !== null));

                return;
            }

            $computer = HardwareModel::findOrFail($this->editingId);
            $computer->fill($values);
            $changed = $computer->getDirty();

            if (! $changed) {
                return;
            }

            $before = collect($computer->getOriginal())->only(array_keys($changed))->all();
            $computer->save();

            $audit->log('hardware.set', 'hardware', $computer->id, before: $before, after: $changed);
        });

        $this->editorOpen = false;
        unset($this->computers, $this->live, $this->groups, $this->repeatedSerials);
    }

    public function delete(AuditLogger $audit): void
    {
        $this->authorize(Permission::EditOffice->value);

        $computer = HardwareModel::findOrFail($this->editingId);

        DB::transaction(function () use ($computer, $audit) {
            $before = $computer->only(self::Fields);
            $computer->delete();

            $audit->log('hardware.delete', 'hardware', $computer->id, before: $before);
        });

        $this->editorOpen = false;
        unset($this->computers, $this->live, $this->groups, $this->repeatedSerials);
    }

    public function render(): View
    {
        $thisYear = (int) now()->format('Y');
        $live = $this->live;

        return view('livewire.hardware', [
            'thisYear' => $thisYear,
            'kinds' => HardwareModel::Kinds,
            'kindCounts' => $live->countBy(fn (HardwareModel $row) => $row->kind()),
            'due' => $live->filter(fn (HardwareModel $row) => in_array($row->dueStatus()['key'], ['overdue', 'now'], true))->count(),
            'dueNext' => $live->filter(fn (HardwareModel $row) => $row->dueStatus()['key'] === 'next')->count(),
            'spares' => $live->filter(fn (HardwareModel $row) => $row->isSpare()),
            'unprotected' => $live->filter(fn (HardwareModel $row) => ! $row->isSpare() && $row->sophos !== 'Y')->count(),
            'depts' => $this->computers->pluck('dept')->filter()->unique()->sort()->values(),
            'canEdit' => auth()->user()->can(Permission::EditOffice->value),
        ]);
    }
}
