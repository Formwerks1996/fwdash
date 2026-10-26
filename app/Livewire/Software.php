<?php

namespace App\Livewire;

use App\Enums\Permission;
use App\Models\AppSetting;
use App\Models\Software as SoftwareModel;
use App\Support\AuditLogger;
use App\Support\ExchangeRates;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read Collection<int, SoftwareModel> $subscriptions
 * @property-read array<string, array{rate: float, source: string}> $rates
 * @property-read Collection<int, SoftwareModel> $rows
 * @property-read array<string, mixed> $totals
 */
#[Title('Software')]
class Software extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $division = '';

    public bool $adding = false;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->resetForm();
    }

    /** @return Collection<int, SoftwareModel> */
    #[Computed]
    public function subscriptions(): Collection
    {
        return SoftwareModel::orderByDesc('active')->orderByRaw('lower(name)')->get();
    }

    /** @return array<string, array{rate: float, source: string}> */
    #[Computed]
    public function rates(): array
    {
        return app(ExchangeRates::class)->toCad();
    }

    /** @return Collection<int, SoftwareModel> */
    #[Computed]
    public function rows(): Collection
    {
        $search = Str::lower(trim($this->search));

        return $this->subscriptions
            ->when($this->division !== '', fn (Collection $rows) => $rows->where('division', $this->division))
            ->when($search !== '', fn (Collection $rows) => $rows->filter(
                fn (SoftwareModel $row) => Str::contains(Str::lower("{$row->name} {$row->division} {$row->description} {$row->note}"), $search),
            ))
            ->values();
    }

    public function annualCad(SoftwareModel $software): ?float
    {
        $rate = $this->rates[$software->currency]['rate'] ?? null;

        return $rate === null || $software->annual() === null ? null : $software->annual() * $rate;
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function totals(): array
    {
        $live = $this->subscriptions->where('active', true);
        $missing = $live->pluck('currency')->unique()->reject(fn (string $currency) => isset($this->rates[$currency]))->values();

        $withSeats = $live->filter(fn (SoftwareModel $row) => $row->idleSeats() !== null);
        $idleCostByCurrency = $live
            ->filter(fn (SoftwareModel $row) => $row->idleCost() !== null)
            ->groupBy('currency')
            ->map(fn (Collection $rows) => (float) $rows->sum(fn (SoftwareModel $row) => $row->idleCost() ?? 0));

        return [
            'live' => $live->count(),
            'retired' => $this->subscriptions->count() - $live->count(),
            'divisions' => $live->pluck('division')->filter()->unique()->count(),
            'annualCad' => $missing->isEmpty() ? $live->sum(fn (SoftwareModel $row) => $this->annualCad($row) ?? 0) : null,
            'byCurrency' => $live->groupBy('currency')->map(fn (Collection $rows) => $rows->sum(fn (SoftwareModel $row) => $row->annual() ?? 0)),
            'missing' => $missing,
            'idle' => $withSeats->sum(fn (SoftwareModel $row) => $row->idleSeats()),
            'seats' => $withSeats->sum('seats'),
            'idleCostCad' => $missing->isEmpty()
                ? $idleCostByCurrency->map(fn (float $cost, string $currency) => $cost * $this->rates[$currency]['rate'])->sum()
                : null,
            'idleCostByCurrency' => $idleCostByCurrency,
            'idleUnpriced' => $live->filter(fn (SoftwareModel $row) => $row->idleSeats() && ! $row->idleCost())->count(),
        ];
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorize(Permission::EditOffice->value);

        $validated = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.division' => ['nullable', 'string', 'max:255'],
            'form.price' => ['nullable', 'numeric', 'min:0'],
            'form.currency' => ['required', Rule::in(SoftwareModel::Currencies)],
            'form.cycle' => ['required', Rule::in(SoftwareModel::Cycles)],
            'form.seats' => ['nullable', 'integer', 'min:0'],
            'form.seats_used' => ['nullable', 'integer', 'min:0'],
            'form.description' => ['nullable', 'string', 'max:1000'],
        ], ['form.name.required' => __('It needs a name.')])['form'];

        $validated = array_map(fn (mixed $value) => $value === '' ? null : $value, $validated);

        DB::transaction(function () use ($validated, $audit) {
            $software = SoftwareModel::create($validated);

            $audit->log('software.add', 'software', $software->id, after: $validated);
        });

        $this->adding = false;
        $this->resetForm();
        unset($this->subscriptions);
    }

    public function setActive(int $id, bool $active, AuditLogger $audit): void
    {
        $this->authorize(Permission::EditOffice->value);

        $software = SoftwareModel::findOrFail($id);

        DB::transaction(function () use ($software, $active, $audit) {
            $before = ['active' => $software->active];
            $software->update(['active' => $active]);

            $audit->log('software.set', 'software', $software->id, before: $before, after: ['active' => $active]);
        });

        unset($this->subscriptions);
    }

    public function pinRate(string $currency, ?string $value, AuditLogger $audit): void
    {
        $this->authorize(Permission::EditOffice->value);

        abort_unless(in_array($currency, SoftwareModel::Currencies, true), 422);

        $key = "fx.{$currency}";
        $value = trim((string) $value);
        $existing = AppSetting::find($key);

        if ($value !== '' && (! is_numeric($value) || (float) $value <= 0)) {
            $this->addError('fx', __('A rate must be a number above zero.'));

            return;
        }

        DB::transaction(function () use ($key, $value, $existing, $audit) {
            $value === ''
                ? $existing?->delete()
                : AppSetting::updateOrCreate(['key' => $key], ['value' => $value]);

            $audit->log('setting.set', 'app_settings', $key, before: $existing?->only(['key', 'value']), after: ['key' => $key, 'value' => $value ?: null]);
        });

        unset($this->rates);
    }

    public function render(): View
    {
        return view('livewire.software', [
            'divisions' => $this->subscriptions->pluck('division')->filter()->unique()->sort()->values(),
            'foreign' => $this->subscriptions->where('active', true)->pluck('currency')->unique()->reject(fn (string $currency) => $currency === 'CAD')->sort()->values(),
            'pinned' => app(ExchangeRates::class)->pinned(),
            'published' => app(ExchangeRates::class)->published(),
        ]);
    }

    protected function resetForm(): void
    {
        $this->form = ['name' => '', 'division' => '', 'price' => '', 'currency' => 'CAD', 'cycle' => 'Monthly', 'seats' => '', 'seats_used' => '', 'description' => ''];
    }
}
