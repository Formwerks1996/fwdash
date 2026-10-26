<?php

namespace App\Actions\Team;

use App\Models\LineupWeek;
use App\Models\Project;
use App\Support\AuditLogger;
use App\Support\LineupTree;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * Every lineup change applies ONE edit to the freshly read week inside a
 * locked transaction, so two people editing at once never overwrite each
 * other (the old page saved the whole week and the last write won).
 *
 * A week is saved the first time anyone changes it; opening one never writes.
 * Each public method returns the toast message and the week as it was, for Undo.
 */
class UpdateLineupWeekAction
{
    public function __construct(protected AuditLogger $audit) {}

    /** @return array{message: string, before: array{placements: array<string, string>, tags: array<string, string>, badges: array<string, array{type: string, note?: string}>}} */
    public function move(string $week, string $name, string $drop): array
    {
        return $this->write($week, function (LineupTree $tree, array $tags, array $badges) use ($name, $drop) {
            if ($refusal = $tree->refusal($name, $drop)) {
                throw ValidationException::withMessages(['lineup' => $refusal]);
            }

            $person = $tree->people[$name];
            $count = $tree->countUnder($name);
            $team = $count
                ? ' '.__('(and :count under :first)', ['count' => $count === 1 ? __('1 person') : __(':count people', ['count' => $count]), 'first' => $person->firstName()])
                : '';

            $message = $drop === LineupTree::Tray
                ? __(':name moved to Not placed', ['name' => $person->displayName()]).$team
                : __(':name now goes to :boss', ['name' => $person->displayName(), 'boss' => $tree->people[substr($drop, 6)]->displayName()]).$team;

            return [$tree->moved($name, $drop), $tags, $badges, $message];
        });
    }

    /** @return array{message: string, before: array{placements: array<string, string>, tags: array<string, string>, badges: array<string, array{type: string, note?: string}>}} */
    public function setTag(string $week, string $name, ?string $jobNumber): array
    {
        return $this->write($week, function (LineupTree $tree, array $tags, array $badges) use ($name, $jobNumber) {
            $this->ensureTaggable($tree, $name);

            if ($jobNumber === null || $jobNumber === '') {
                unset($tags[$name]);

                return [$tree, $tags, $badges, __("Removed :first's project tag", ['first' => $tree->people[$name]->firstName()])];
            }

            $project = Project::find($jobNumber);

            if (! $project) {
                throw ValidationException::withMessages(['lineup' => __('project tags must be projects from the Projects list (not found: :job)', ['job' => $jobNumber])]);
            }

            $tags[$name] = $project->job_number;

            return [$tree, $tags, $badges, __(':first tagged :job :title', ['first' => $tree->people[$name]->firstName(), 'job' => $project->job_number, 'title' => $project->job_name])];
        });
    }

    /** @return array{message: string, before: array{placements: array<string, string>, tags: array<string, string>, badges: array<string, array{type: string, note?: string}>}} */
    public function setBadge(string $week, string $name, ?string $type, ?string $note = null): array
    {
        return $this->write($week, function (LineupTree $tree, array $tags, array $badges) use ($name, $type, $note) {
            $this->ensureTaggable($tree, $name);

            if ($type === null || $type === '') {
                unset($badges[$name]);

                return [$tree, $tags, $badges, __("Removed :first's badge", ['first' => $tree->people[$name]->firstName()])];
            }

            if (! isset(LineupTree::Badges[$type])) {
                throw ValidationException::withMessages(['lineup' => __('a badge is one of deadline, help, available')]);
            }

            $note = mb_substr(trim((string) $note), 0, 80);
            $badges[$name] = $note === '' ? ['type' => $type] : ['type' => $type, 'note' => $note];

            return [$tree, $tags, $badges, __(':first marked :badge', ['first' => $tree->people[$name]->firstName(), 'badge' => LineupTree::Badges[$type]['label']])];
        });
    }

    /**
     * Back to how a fresh week starts: PMs at their default partner, everyone
     * else not placed, no tags or badges. The row stays saved.
     *
     * @return array{message: string, before: array{placements: array<string, string>, tags: array<string, string>, badges: array<string, array{type: string, note?: string}>}}
     */
    public function erase(string $week): array
    {
        return $this->write($week, fn (LineupTree $tree) => [
            LineupTree::fromPlacements($tree->people, []),
            [],
            [],
            __('Week erased, back to the defaults'),
        ]);
    }

    /**
     * Placements and tags from the week before; badges are this week's own.
     *
     * @return array{message: string, before: array{placements: array<string, string>, tags: array<string, string>, badges: array<string, array{type: string, note?: string}>}}|null null when nothing was set up the week before
     */
    public function copyLastWeek(string $week): ?array
    {
        $previous = LineupWeek::find(CarbonImmutable::parse($this->monday($week))->subWeek()->toDateString());

        if (! $previous) {
            return null;
        }

        return $this->write($week, function (LineupTree $tree, array $tags, array $badges) use ($previous) {
            $copied = LineupTree::fromPlacements($tree->people, $previous->placements);

            return [$copied, $copied->keepTags($previous->tags, $this->jobNumbers()), $badges, __("Copied last week's lineup")];
        });
    }

    /**
     * Undo: put back a snapshot this page took, re-validated like any save.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array{message: string, before: array{placements: array<string, string>, tags: array<string, string>, badges: array<string, array{type: string, note?: string}>}}
     */
    public function restore(string $week, array $snapshot): array
    {
        foreach (['placements' => __('placements must map names to names'), 'tags' => __('tags must map names to projects')] as $key => $message) {
            $map = $snapshot[$key] ?? [];

            if (! is_array($map) || collect($map)->contains(fn (mixed $value, mixed $name) => ! is_string($value) || ! is_string($name))) {
                throw ValidationException::withMessages(['lineup' => $message]);
            }
        }

        if (! is_array($snapshot['badges'] ?? [])) {
            throw ValidationException::withMessages(['lineup' => __('a badge is one of deadline, help, available')]);
        }

        return $this->write($week, function (LineupTree $tree) use ($snapshot) {
            $restored = LineupTree::fromPlacements($tree->people, $snapshot['placements'] ?? []);

            return [
                $restored,
                $restored->keepTags($snapshot['tags'] ?? [], $this->jobNumbers()),
                $restored->keepBadges($snapshot['badges'] ?? []),
                __('Undone'),
            ];
        });
    }

    public function monday(string $week): string
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $week) ?: null;

        if (! $date || $date->toDateString() !== $week) {
            throw ValidationException::withMessages(['lineup' => __('week must be a date, YYYY-MM-DD')]);
        }

        if (! $date->isMonday()) {
            throw ValidationException::withMessages(['lineup' => __('week must be the Monday that starts it')]);
        }

        return $week;
    }

    /** @return array{placements: array<string, string>, tags: array<string, string>, badges: array<string, array{type: string, note?: string}>} */
    public static function snapshotOf(?LineupWeek $row): array
    {
        return [
            'placements' => $row->placements ?? [],
            'tags' => $row->tags ?? [],
            'badges' => $row->badges ?? [],
        ];
    }

    protected function ensureTaggable(LineupTree $tree, string $name): void
    {
        if (! $tree->has($name)) {
            throw ValidationException::withMessages(['lineup' => __('unknown team member :name', ['name' => $name])]);
        }

        if ($tree->isPartner($name)) {
            throw ValidationException::withMessages(['lineup' => __('partners do not take project tags or badges')]);
        }
    }

    /** @return Collection<int, string> */
    protected function jobNumbers(): Collection
    {
        return Project::pluck('job_number');
    }

    /**
     * @param  Closure(LineupTree, array<string, string>, array<string, array{type: string, note?: string}>): array{0: LineupTree, 1: array<string, string>, 2: array<string, array{type: string, note?: string}>, 3: string}  $change
     * @return array{message: string, before: array{placements: array<string, string>, tags: array<string, string>, badges: array<string, array{type: string, note?: string}>}}
     */
    protected function write(string $week, Closure $change): array
    {
        $week = $this->monday($week);

        return DB::transaction(function () use ($week, $change) {
            $row = LineupWeek::lockForUpdate()->find($week);
            $tree = LineupTree::forWeek($row);

            [$next, $tags, $badges, $message] = $change(
                $tree,
                $tree->keepTags($row?->tags, $this->jobNumbers()),
                $tree->keepBadges($row?->badges),
            );

            $before = self::snapshotOf($row);
            $updatedAt = $row?->updated_at->toDateTimeString();
            $after = ['placements' => $next->toPlacements(), 'tags' => $tags, 'badges' => $badges];

            $row ??= new LineupWeek(['week' => $week]);
            $row->fill([...$after, 'updated_by' => auth()->id()]);
            $row->save();

            $this->audit->log(
                'lineup.set',
                'lineup_weeks',
                $week,
                before: $updatedAt === null ? null : ['week' => $week, ...$before, 'updatedAt' => $updatedAt],
                after: $after,
            );

            return ['message' => $message, 'before' => $before];
        });
    }
}
