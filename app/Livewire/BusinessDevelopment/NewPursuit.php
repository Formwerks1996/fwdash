<?php

namespace App\Livewire\BusinessDevelopment;

use App\Actions\BusinessDevelopment\SaveDeveloperAction;
use App\Actions\BusinessDevelopment\SavePursuitAction;
use App\Billing\DeveloperIndex;
use App\Enums\Permission;
use App\Enums\PursuitStage;
use App\Support\DeveloperKey;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * The header's "New Pursuit" form, available on every page.
 *
 * @property-read array<int, array{key: string, name: string, jobs: int}> $clients
 */
class NewPursuit extends Component
{
    public bool $open = false;

    public string $project = '';

    public string $client = '';

    public ?string $fee = null;

    public ?string $owner = null;

    public string $stage = 'Contacted';

    public string $note = '';

    #[On('open-new-pursuit')]
    public function show(): void
    {
        $this->reset('project', 'client', 'fee', 'owner', 'note');
        $this->stage = PursuitStage::Contacted->value;
        $this->resetValidation();
        $this->open = true;
    }

    /** @return array<int, array{key: string, name: string, jobs: int}> */
    #[Computed]
    public function clients(): array
    {
        return collect(app(DeveloperIndex::class)->all())
            ->map(fn (array $row) => ['key' => $row['key'], 'name' => $row['name'], 'jobs' => $row['jobCount']])
            ->sortBy(fn (array $row) => mb_strtolower($row['name']))
            ->values()
            ->all();
    }

    public function save(SavePursuitAction $savePursuit, SaveDeveloperAction $saveDeveloper): void
    {
        $this->authorize(Permission::EditBusinessDevelopment->value);

        $client = trim($this->client);

        if (trim($this->project) === '' && $client === '') {
            throw ValidationException::withMessages(['project' => __('Give it a project or a client, so the row can be found again.')]);
        }

        $known = collect($this->clients)->firstWhere('key', app(DeveloperIndex::class)->resolve(DeveloperKey::make($client)));

        $savePursuit->execute([
            'developer' => $known['name'] ?? $client,
            'project' => $this->project,
            'fee' => $this->fee,
            'owner' => $this->owner,
            'stage' => $this->stage,
            'note' => $this->note,
        ]);

        if ($client !== '' && ! $known) {
            try {
                $saveDeveloper->execute($client);
            } catch (Throwable) {
                // The pursuit is saved; the profile can be added on the Developers page later.
            }
        }

        $this->open = false;
        $this->redirectRoute('pipeline', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.business-development.new-pursuit', [
            'owners' => config('bizdev.owners'),
            'stages' => array_filter(PursuitStage::cases(), fn (PursuitStage $stage) => ! $stage->isSettled()),
        ]);
    }
}
