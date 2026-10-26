<?php

namespace App\Livewire\BusinessDevelopment;

use App\Models\AppSetting;
use App\Models\OutreachProspect;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read Collection<int, OutreachProspect> $prospects
 * @property-read Collection<int, OutreachProspect> $rows
 */
#[Title('Outreach List')]
class OutreachIndex extends Component
{
    public const Tiers = ['A' => 'A · fit 5', 'B' => 'B · fit 4', 'C' => 'C · fit 3', 'D' => 'D · 1–2'];

    public const Activity = [
        'Active' => ['#1c6f50', 'Something under construction, selling, or in approvals in 2025–26'],
        'Recent' => ['#c28f1c', 'Nothing live found, but a project finished or launched in 2023–24'],
        'Quiet' => ['#9aa0a8', 'Last project activity found was 2022 or earlier. Not the best people to reach out to right now'],
        'Unknown' => ['#c9ccd1', 'No dated evidence either way'],
    ];

    public const Has = [
        'contact' => ['Named contact', 'A named person to approach, not just an office line'],
        'linkedin' => ['LinkedIn', null],
        'email' => ['Email', 'Their own email or the office email'],
        'profile' => ['Full profile', 'Fit 4–5 developers profiled in the regional PDF decks'],
        'dated' => ['Dated source', 'Activity status backed by a dated, linked source'],
        'link' => ['Possible link to us', 'Name looks like an existing client, or we have worked with them before. Confirm before cold outreach'],
    ];

    #[Url]
    public string $search = '';

    #[Url]
    public string $region = '';

    /** @var array<int, string> */
    #[Url]
    public array $tiers = ['A', 'B'];

    /** @var array<int, string> */
    #[Url]
    public array $activity = [];

    /** @var array<int, string> */
    #[Url]
    public array $types = [];

    /** @var array<int, string> */
    #[Url]
    public array $has = [];

    #[Url]
    public string $sort = 'fit';

    #[Url]
    public string $direction = 'asc';

    /** @return Collection<int, OutreachProspect> */
    #[Computed]
    public function prospects(): Collection
    {
        return OutreachProspect::with('projects')
            ->orderBy('position')
            ->get()
            ->reject(fn (OutreachProspect $prospect) => in_array('Skip', $prospect->tags, true))
            ->values();
    }

    /** @return Collection<int, OutreachProspect> */
    #[Computed]
    public function rows(): Collection
    {
        $search = Str::lower(trim($this->search));

        $rows = $this->prospects->filter(function (OutreachProspect $prospect) use ($search) {
            if ($this->tiers && ! in_array($prospect->tier, $this->tiers, true)) {
                return false;
            }

            if ($this->region !== '' && $prospect->region !== $this->region) {
                return false;
            }

            if ($this->activity && ! in_array(static::status($prospect), $this->activity, true)) {
                return false;
            }

            if ($this->types && ! array_intersect($prospect->types, $this->types)) {
                return false;
            }

            foreach ($this->has as $requirement) {
                if (! $this->meets($prospect, $requirement)) {
                    return false;
                }
            }

            return $search === '' || Str::contains($this->haystack($prospect), $search);
        });

        $sorted = $rows->sortBy(fn (OutreachProspect $prospect) => $this->sortKey($prospect))->values();

        return $this->direction === 'desc' ? $sorted->reverse()->values() : $sorted;
    }

    public function sortBy(string $column): void
    {
        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
    }

    public function toggle(string $filter, string $value): void
    {
        abort_unless(in_array($filter, ['tiers', 'activity', 'types', 'has'], true), 422);

        $this->{$filter} = in_array($value, $this->{$filter}, true)
            ? array_values(array_diff($this->{$filter}, [$value]))
            : [...$this->{$filter}, $value];
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'region', 'tiers', 'activity', 'types', 'has');
    }

    public static function status(OutreachProspect $prospect): string
    {
        return $prospect->activity['status'] ?? 'Unknown' ?: 'Unknown';
    }

    /** @return array<string, string> type label => colour */
    public static function typeColors(): array
    {
        return collect(json_decode((string) AppSetting::find('outreach.types')?->value, true) ?: [])
            ->pluck('color', 'label')
            ->all();
    }

    public function render(): View
    {
        $prospects = $this->prospects;

        // The detail page's Previous / Next walk the list as filtered and sorted here.
        session(['outreach.order' => $this->rows->pluck('key')->all()]);

        return view('livewire.business-development.outreach-index', [
            'regions' => $prospects->pluck('region')->filter()->unique()->sort()->values(),
            'typeColors' => static::typeColors(),
            'built' => AppSetting::find('outreach.built')?->value,
            'mapUrl' => AppSetting::find('outreach.map_url')?->value,
            'projectCount' => $prospects->sum(fn (OutreachProspect $prospect) => $prospect->projects->count()),
            'isDefault' => $this->tiers === ['A', 'B'] && $this->region === '' && ! $this->activity && ! $this->types && ! $this->has && $this->search === '',
        ]);
    }

    protected function meets(OutreachProspect $prospect, string $requirement): bool
    {
        return match ($requirement) {
            'contact' => $prospect->has_contact,
            'linkedin' => $prospect->has_linkedin,
            'email' => $prospect->has_email,
            'profile' => in_array('In deck', $prospect->tags, true),
            'dated' => (bool) ($prospect->activity['confirmed'] ?? false),
            'link' => (bool) array_intersect(['Check first', 'Warm lead'], $prospect->tags),
            default => true,
        };
    }

    protected function haystack(OutreachProspect $prospect): string
    {
        return Str::lower(implode(' ', [
            $prospect->name, $prospect->hq, $prospect->region, $prospect->product, $prospect->architect,
            $prospect->person['name'] ?? '', $prospect->others, implode(' ', $prospect->recent),
            $prospect->projects->map(fn ($project) => "{$project->name} {$project->where_text}")->implode(' '),
        ]));
    }

    /** @return array<int, mixed> */
    protected function sortKey(OutreachProspect $prospect): array
    {
        $order = array_search(static::status($prospect), array_keys(self::Activity), true);

        return match ($this->sort) {
            'name' => [Str::lower($prospect->name)],
            'region' => [$prospect->region, -$prospect->score],
            'activity' => [$order, -($prospect->activity['lastYear'] ?? 0), -$prospect->score],
            'contact' => [! $prospect->has_contact, ! $prospect->has_linkedin, -$prospect->score],
            'projects' => [-($prospect->projects->count() ?: count($prospect->recent)), -$prospect->score],
            default => [-$prospect->score, Str::lower($prospect->name)],
        };
    }
}
