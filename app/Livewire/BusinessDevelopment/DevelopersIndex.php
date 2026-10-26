<?php

namespace App\Livewire\BusinessDevelopment;

use App\Actions\BusinessDevelopment\MergeDevelopersAction;
use App\Actions\BusinessDevelopment\SaveDeveloperAction;
use App\Billing\DeveloperIndex;
use App\Enums\Permission;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read Collection<string, array<string, mixed>> $developers
 */
#[Title('Developers')]
class DevelopersIndex extends Component
{
    #[Url]
    public string $search = '';

    public string $newName = '';

    public bool $adding = false;

    /** @return Collection<string, array<string, mixed>> */
    #[Computed]
    public function developers(): Collection
    {
        return collect(app(DeveloperIndex::class)->all());
    }

    public function add(SaveDeveloperAction $saveDeveloper): void
    {
        $this->authorize(Permission::EditBusinessDevelopment->value);

        $developer = $saveDeveloper->execute($this->newName);

        $this->redirectRoute('developers.show', $developer->key, navigate: true);
    }

    /** Keeps one spelling and folds the other into it. */
    public function merge(string $keep, string $drop, MergeDevelopersAction $merge): void
    {
        $this->authorize(Permission::EditBusinessDevelopment->value);

        $merge->merge($drop, $keep, $this->developers[$drop]['name'] ?? null);

        app(DeveloperIndex::class)->forget();
        unset($this->developers);
    }

    public function render(): View
    {
        $search = Str::lower(trim($this->search));

        $rows = $this->developers->when($search !== '', fn (Collection $rows) => $rows->filter(
            fn (array $row) => Str::contains(Str::lower("{$row['name']} {$row['city']}"), $search),
        ));

        $largest = $this->developers->sortByDesc('contractMax')->first();

        return view('livewire.business-development.developers-index', [
            'rows' => $rows,
            'withJobs' => $this->developers->where('jobCount', '>', 0)->count(),
            'underContract' => $this->developers->sum('contractTotal'),
            'largest' => $largest,
            'duplicates' => app(DeveloperIndex::class)->possibleDuplicates(),
            'canEdit' => auth()->user()->can(Permission::EditBusinessDevelopment->value),
            'showMoney' => auth()->user()->can(Permission::ViewFinancials->value),
        ]);
    }
}
