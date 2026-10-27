<?php

namespace App\Support;

use App\Models\LineupWeek;
use App\Models\TeamMember;
use Illuminate\Support\Collection;

/*
 * One week of the Team Lineup as a tree: two partners at the top, anyone else
 * under anyone. A saved week stores only the links someone set; a PM the week
 * has not placed starts under their default partner, and "none" means a PM
 * was taken off for the week (so the default does not come back).
 *
 * Reading is forgiving: links to people who have left are dropped and a link
 * that would make a loop is cut, so an old week always draws.
 */
class LineupTree
{
    public const None = 'none';

    public const Tray = 'tray';

    public const Badges = [
        'help' => ['label' => 'Needs help', 'hint' => 'Could use a hand this week'],
        'available' => ['label' => 'Available', 'hint' => 'Has time to help others this week'],
    ];

    /** Weeks saved before the deadline badge was dropped read it as "help". */
    protected const RetiredDeadlineBadge = 'deadline';

    /** Guard against a malformed chain when walking up the tree. */
    protected const MaxHops = 40;

    /** @var array<string, array<int, string>> */
    protected array $children = [];

    /**
     * @param  array<string, TeamMember>  $people  active members keyed by name
     * @param  array<string, string>  $boss  person => the person they go to, or "none"
     */
    public function __construct(public array $people, public array $boss)
    {
        foreach (array_keys($this->people) as $name) {
            $parent = $this->parentOf($name);

            if ($parent !== null) {
                $this->children[$parent][] = $name;
            }
        }

        foreach ($this->children as $parent => $names) {
            usort($names, fn (string $a, string $b) => [! $this->isPm($a), $this->people[$a]->displayName()] <=> [! $this->isPm($b), $this->people[$b]->displayName()]);
            $this->children[$parent] = $names;
        }
    }

    /** @return array<string, TeamMember> */
    public static function roster(): array
    {
        return TeamMember::active()
            ->get()
            ->sortBy(fn (TeamMember $member) => $member->displayName())
            ->keyBy('name')
            ->all();
    }

    /**
     * @param  array<string, TeamMember>  $people
     * @param  array<string, mixed>|null  $placements
     */
    public static function fromPlacements(array $people, ?array $placements): self
    {
        $placements ??= [];
        $boss = [];

        foreach ($people as $name => $member) {
            if ($member->isPartner()) {
                continue;
            }

            $to = $placements[$name] ?? ($member->isPm() ? $member->default_boss : null);

            if ($to === self::None || (is_string($to) && $to !== $name && isset($people[$to]))) {
                $boss[$name] = $to;
            }
        }

        foreach (array_keys($boss) as $name) {
            $seen = [$name => true];

            for ($at = $boss[$name]; isset($boss[$at]); $at = $boss[$at]) {
                if (isset($seen[$at])) {
                    unset($boss[$name]);

                    break;
                }

                $seen[$at] = true;
            }
        }

        return new self($people, $boss);
    }

    /** @param  array<string, TeamMember>|null  $people */
    public static function forWeek(?LineupWeek $week, ?array $people = null): self
    {
        return self::fromPlacements($people ?? self::roster(), $week?->placements);
    }

    public function has(string $name): bool
    {
        return isset($this->people[$name]);
    }

    public function isPartner(string $name): bool
    {
        return isset($this->people[$name]) && $this->people[$name]->isPartner();
    }

    public function isPm(string $name): bool
    {
        return isset($this->people[$name]) && $this->people[$name]->isPm();
    }

    /** @return array<int, string> the two partners, left side first */
    public function partners(): array
    {
        return collect($this->people)
            ->filter(fn (TeamMember $member) => $member->isPartner())
            ->keys()
            ->sort()
            ->take(TeamMember::MaxPartners)
            ->values()
            ->all();
    }

    public function parentOf(string $name): ?string
    {
        $to = $this->boss[$name] ?? null;

        return $to !== null && isset($this->people[$to]) ? $to : null;
    }

    /** @return array<int, string> PMs first, then by name */
    public function childrenOf(string $name): array
    {
        return $this->children[$name] ?? [];
    }

    public function countUnder(string $name): int
    {
        return array_sum(array_map(fn (string $child) => 1 + $this->countUnder($child), $this->childrenOf($name)));
    }

    public function isUnder(string $name, string $ancestor): bool
    {
        $at = $this->parentOf($name);

        for ($hops = 0; $at !== null && $hops < self::MaxHops; $hops++) {
            if ($at === $ancestor) {
                return true;
            }

            $at = $this->parentOf($at);
        }

        return false;
    }

    public function rootOf(string $name): string
    {
        $at = $name;

        for ($hops = 0; ($parent = $this->parentOf($at)) !== null && $hops < self::MaxHops; $hops++) {
            $at = $parent;
        }

        return $at;
    }

    public function isPlaced(string $name): bool
    {
        return $this->isPartner($this->rootOf($name)) && ! $this->isPartner($name);
    }

    /** @return array<int, string> children of a partner that head a team: PMs, or anyone with people under them */
    public function teamsUnder(string $partner): array
    {
        return array_values(array_filter(
            $this->childrenOf($partner),
            fn (string $name) => $this->isPm($name) || $this->childrenOf($name),
        ));
    }

    /** @return array<int, string> */
    public function directUnder(string $partner): array
    {
        return array_values(array_diff($this->childrenOf($partner), $this->teamsUnder($partner)));
    }

    /** @return array<int, string> everyone at the top of a branch that does not reach a partner */
    public function loose(): array
    {
        return array_values(array_filter(
            array_keys($this->people),
            fn (string $name) => ! $this->isPartner($name) && $this->parentOf($name) === null,
        ));
    }

    /** @return array<int, string> */
    public function nonPartners(): array
    {
        return array_values(array_filter(array_keys($this->people), fn (string $name) => ! $this->isPartner($name)));
    }

    public function placedCount(): int
    {
        return count(array_filter($this->nonPartners(), fn (string $name) => $this->isPlaced($name)));
    }

    /**
     * Why a drop is refused, or null when it is allowed. Drops are "tray" or
     * "under:<name>" (a person, or a partner's side).
     */
    public function refusal(string $name, string $drop): ?string
    {
        if (! $this->has($name)) {
            return __('unknown team member :name', ['name' => $name]);
        }

        if ($this->isPartner($name)) {
            return __('partners cannot be moved');
        }

        $person = $this->people[$name]->displayName();

        if ($drop === self::Tray) {
            if ($this->parentOf($name) === null) {
                return __(':name is already not placed', ['name' => $person]);
            }

            return null;
        }

        if (! str_starts_with($drop, 'under:')) {
            return __('unknown drop target');
        }

        $to = substr($drop, 6);

        if (! $this->has($to)) {
            return __('unknown team member :name', ['name' => $to]);
        }

        if ($to === $name || $this->isUnder($to, $name)) {
            return __('that would put :name under their own team', ['name' => $person]);
        }

        if ($this->parentOf($name) === $to) {
            return __(':name already goes to :boss', ['name' => $person, 'boss' => $this->people[$to]->displayName()]);
        }

        return null;
    }

    public function accepts(string $name, string $drop): bool
    {
        return $this->refusal($name, $drop) === null;
    }

    /** The tree after a move; the person's whole branch goes with them. */
    public function moved(string $name, string $drop): self
    {
        $boss = $this->boss;

        if ($drop === self::Tray) {
            if ($this->isPm($name)) {
                $boss[$name] = self::None;
            } else {
                unset($boss[$name]);
            }

            return new self($this->people, $boss);
        }

        $boss[$name] = substr($drop, 6);

        return new self($this->people, $boss);
    }

    /**
     * What is stored: every link except a PM still at their default partner,
     * so a later change of default reaches weeks that never placed that PM.
     *
     * @return array<string, string>
     */
    public function toPlacements(): array
    {
        $placements = [];

        foreach ($this->boss as $name => $to) {
            $member = $this->people[$name] ?? null;

            if ($member && $member->isPm() && $member->default_boss === $to) {
                continue;
            }

            $placements[$name] = $to;
        }

        ksort($placements);

        return $placements;
    }

    /** @return array{sides: array<int, array{name: string, first: string, teams: int, people: int}>, loose: int} */
    public function summary(): array
    {
        return [
            'sides' => array_map(fn (string $partner) => [
                'name' => $partner,
                'first' => $this->people[$partner]->firstName(),
                'teams' => count($this->teamsUnder($partner)),
                'people' => $this->countUnder($partner),
            ], $this->partners()),
            'loose' => count(array_filter($this->nonPartners(), fn (string $name) => ! $this->isPlaced($name))),
        ];
    }

    /**
     * Tags kept for people still on the roster (never partners), and, when a
     * project list is given, only for projects still on it.
     *
     * @param  array<string, mixed>|null  $tags
     * @param  Collection<int, string>|null  $jobNumbers
     * @return array<string, string>
     */
    public function keepTags(?array $tags, ?Collection $jobNumbers = null): array
    {
        $known = $jobNumbers?->flip();

        return collect($tags ?? [])
            ->filter(fn (mixed $job, string|int $name) => is_string($job)
                && $this->has((string) $name)
                && ! $this->isPartner((string) $name)
                && ($known === null || $known->isEmpty() || $known->has($job)))
            ->mapWithKeys(fn (string $job, string|int $name) => [(string) $name => $job])
            ->sortKeys()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $badges
     * @return array<string, array{type: string, note?: string}>
     */
    public function keepBadges(?array $badges): array
    {
        $kept = [];

        foreach ($badges ?? [] as $name => $badge) {
            $type = is_array($badge) ? ($badge['type'] ?? null) : null;
            $type = $type === self::RetiredDeadlineBadge ? 'help' : $type;

            if (! $this->has((string) $name) || $this->isPartner((string) $name) || ! is_string($type) || ! isset(self::Badges[$type])) {
                continue;
            }

            $note = mb_substr(trim((string) ($badge['note'] ?? '')), 0, 80);
            $kept[(string) $name] = $note === '' ? ['type' => $type] : ['type' => $type, 'note' => $note];
        }

        ksort($kept);

        return $kept;
    }
}
