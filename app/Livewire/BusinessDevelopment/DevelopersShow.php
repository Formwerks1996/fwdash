<?php

namespace App\Livewire\BusinessDevelopment;

use App\Actions\BusinessDevelopment\MergeDevelopersAction;
use App\Actions\BusinessDevelopment\SaveDeveloperAction;
use App\Billing\DeveloperIndex;
use App\Enums\Permission;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * @property-read array<string, mixed> $developer
 */
class DevelopersShow extends Component
{
    public string $developerKey;

    public function mount(string $developerKey): void
    {
        $canonical = app(DeveloperIndex::class)->resolve($developerKey);

        abort_if(app(DeveloperIndex::class)->find($canonical) === null, 404);

        $this->developerKey = $canonical;
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function developer(): array
    {
        return app(DeveloperIndex::class)->find($this->developerKey) ?? abort(404);
    }

    public function saveField(string $field, ?string $value, SaveDeveloperAction $saveDeveloper): void
    {
        $this->authorize(Permission::EditBusinessDevelopment->value);

        abort_unless(in_array($field, ['city', 'note'], true), 422);

        if ((string) ($this->developer[$field] ?? '') === trim((string) $value)) {
            return;
        }

        $saveDeveloper->execute($this->developer['name'], [$field => $value]);

        $this->refreshIndex();
    }

    public function separate(string $aliasKey, MergeDevelopersAction $merge): void
    {
        $this->authorize(Permission::EditBusinessDevelopment->value);

        $merge->separate($aliasKey);

        $this->refreshIndex();
    }

    public function render(): View
    {
        return view('livewire.business-development.developers-show', [
            'canEdit' => auth()->user()->can(Permission::EditBusinessDevelopment->value),
            'showMoney' => auth()->user()->can(Permission::ViewFinancials->value),
        ])->title($this->developer['name']);
    }

    protected function refreshIndex(): void
    {
        app(DeveloperIndex::class)->forget();
        unset($this->developer);
    }
}
