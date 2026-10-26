<?php

namespace App\Livewire\BusinessDevelopment;

use App\Actions\BusinessDevelopment\SaveDeveloperAction;
use App\Actions\BusinessDevelopment\SavePursuitAction;
use App\Billing\DeveloperIndex;
use App\Enums\Permission;
use App\Enums\PursuitStage;
use App\Models\Pursuit;
use App\Support\DeveloperKey;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * @property-read Collection<int, Pursuit> $pursuits
 * @property-read array<int, array{key: string, name: string}> $developers
 */
#[Title('Developer Pipeline')]
class Pipeline extends Component
{
    /**
     * Row order is fixed on load and after add, delete or a stage change, so
     * rows do not jump under the cursor while someone types.
     *
     * @var array<int, int>
     */
    public array $order = [];

    public ?string $addedDeveloper = null;

    public function mount(): void
    {
        $this->sortRows();
    }

    /** @return Collection<int, Pursuit> */
    #[Computed]
    public function pursuits(): Collection
    {
        $pursuits = Pursuit::all()->keyBy('id');

        return collect($this->order)
            ->map(fn (int $id) => $pursuits->get($id))
            ->filter()
            ->concat($pursuits->except($this->order)->values())
            ->values();
    }

    /** @return array<int, array{key: string, name: string}> */
    #[Computed]
    public function developers(): array
    {
        return collect(app(DeveloperIndex::class)->all())
            ->map(fn (array $row) => ['key' => $row['key'], 'name' => $row['name']])
            ->sortBy(fn (array $row) => mb_strtolower($row['name']))
            ->values()
            ->all();
    }

    public function add(SavePursuitAction $savePursuit): void
    {
        $this->authorize(Permission::EditBusinessDevelopment->value);

        $pursuit = $savePursuit->execute([]);

        array_unshift($this->order, $pursuit->id);
        unset($this->pursuits);

        $this->dispatch('pursuit-added', id: $pursuit->id);
    }

    public function updateField(int $id, string $field, ?string $value, SavePursuitAction $savePursuit): void
    {
        $this->authorize(Permission::EditBusinessDevelopment->value);

        abort_unless(in_array($field, ['developer', 'project', 'fee', 'stage', 'owner', 'note'], true), 422);

        $pursuit = Pursuit::findOrFail($id);
        $current = $field === 'stage' ? $pursuit->stage->value : $pursuit->{$field};

        if ((string) $current === (string) $value) {
            return;
        }

        $savePursuit->execute([$field => $value ?? ($field === 'developer' ? '' : null)], $pursuit);

        if ($field === 'stage') {
            $this->sortRows();
        }

        unset($this->pursuits);
    }

    /** Saves a new developer's name onto the row, then gives them a profile. */
    public function createDeveloper(int $id, string $name, SavePursuitAction $savePursuit, SaveDeveloperAction $saveDeveloper): void
    {
        $this->authorize(Permission::EditBusinessDevelopment->value);

        $savePursuit->execute(['developer' => $name], Pursuit::findOrFail($id));

        try {
            $this->addedDeveloper = $saveDeveloper->execute($name)->name;
        } catch (Throwable) {
            $this->addedDeveloper = null;
        }

        app(DeveloperIndex::class)->forget();
        unset($this->pursuits, $this->developers);
    }

    public function delete(int $id, SavePursuitAction $savePursuit): void
    {
        $this->authorize(Permission::EditBusinessDevelopment->value);

        $savePursuit->delete(Pursuit::findOrFail($id));

        $this->order = array_values(array_diff($this->order, [$id]));
        unset($this->pursuits);
    }

    #[On('pursuit-created')]
    public function refresh(): void
    {
        $this->sortRows();
    }

    public function render(): View
    {
        $pursuits = $this->pursuits;
        $open = $pursuits->reject(fn (Pursuit $pursuit) => $pursuit->stage->isSettled());
        $won = $pursuits->where('stage', PursuitStage::Won);
        $settled = $pursuits->filter(fn (Pursuit $pursuit) => $pursuit->stage->isSettled())->count();

        return view('livewire.business-development.pipeline', [
            'openFees' => $open->sum(fn (Pursuit $pursuit) => (float) $pursuit->fee),
            'openCount' => $open->count(),
            'wonFees' => $won->sum(fn (Pursuit $pursuit) => (float) $pursuit->fee),
            'wonCount' => $won->count(),
            'settled' => $settled,
            'stages' => PursuitStage::cases(),
            'owners' => config('bizdev.owners'),
            'canEdit' => auth()->user()->can(Permission::EditBusinessDevelopment->value),
            'developerKey' => fn (?string $name) => DeveloperKey::make($name),
            'aliases' => app(DeveloperIndex::class)->aliases(),
        ]);
    }

    protected function sortRows(): void
    {
        $this->order = Pursuit::query()
            ->orderByRaw("case stage when 'Won' then 2 when 'Lost' then 3 else 1 end")
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        unset($this->pursuits);
    }
}
