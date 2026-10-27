<?php

namespace App\Actions\Billing;

use App\Models\Extra;
use App\Models\HourLine;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/*
 * Which extra each hourly billing line is for. A line is only ever tagged to
 * an extra on its own job. Written with query updates, not model saves: the
 * tag does not change any billing, so project financials need no refresh.
 */
class TagHourLinesAction
{
    /** Notes that mean construction administration. */
    protected const CaNotes = ['construction administration', 'ca', 'contract administration'];

    public function __construct(protected AuditLogger $audit) {}

    /** @param  array<int, int>  $lineIds */
    public function tag(array $lineIds, ?Extra $extra): int
    {
        $lines = HourLine::whereKey($lineIds)->get(['id', 'job_number', 'extra_id']);

        if ($extra !== null && $lines->contains(fn (HourLine $line) => $line->job_number !== $extra->job_number)) {
            throw ValidationException::withMessages(['tag' => __('An hourly line can only be tagged to an extra on its own project.')]);
        }

        $changed = $lines->filter(fn (HourLine $line) => $line->extra_id !== $extra?->id);

        DB::transaction(function () use ($changed, $extra) {
            HourLine::whereKey($changed->modelKeys())->update(['extra_id' => $extra?->id]);

            foreach ($changed as $line) {
                $this->audit->log('tag', 'hour_lines', $line->id, before: ['extra_id' => $line->extra_id], after: ['extra_id' => $extra?->id]);
            }
        });

        return $changed->count();
    }

    /**
     * Tags the untagged lines whose note names an extra on their job: by its
     * number ("8040-04"), by its title, or as construction administration.
     */
    public function autoTag(): int
    {
        $extras = Extra::all()->groupBy('job_number');
        $tagged = 0;

        HourLine::whereNull('extra_id')
            ->whereNotNull('note')
            ->whereIn('job_number', $extras->keys())
            ->get(['id', 'job_number', 'note'])
            ->toBase()
            ->groupBy(fn (HourLine $line) => $this->match($line, $extras[$line->job_number])?->id ?? 0)
            ->except([0])
            ->each(function (Collection $lines, int $extraId) use (&$tagged) {
                $tagged += $this->tag($lines->pluck('id')->all(), Extra::find($extraId));
            });

        return $tagged;
    }

    /** @param  Collection<int, Extra>  $extras */
    protected function match(HourLine $line, Collection $extras): ?Extra
    {
        $note = Str::lower(trim((string) $line->note));

        if (preg_match('/(\d{3,4}-\d{2})/', $note, $code) && $byCode = $extras->first(fn (Extra $extra) => $extra->code === $code[1])) {
            return $byCode;
        }

        $byTitle = $extras->first(fn (Extra $extra) => in_array($note, [Str::lower($extra->title), Str::lower($extra->label()), Str::lower((string) $extra->mosaic_phase)], true));

        if ($byTitle) {
            return $byTitle;
        }

        return in_array($note, self::CaNotes, true)
            ? $extras->first(fn (Extra $extra) => Str::startsWith(Str::lower($extra->title), 'construction administration'))
            : null;
    }
}
