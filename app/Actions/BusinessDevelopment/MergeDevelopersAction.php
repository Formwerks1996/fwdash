<?php

namespace App\Actions\BusinessDevelopment;

use App\Models\DeveloperAlias;
use App\Support\AuditLogger;
use App\Support\DeveloperKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * Records that two spellings are one firm. Nothing is retyped: the alias map
 * is applied when the developer list is built, so a merge can be undone by
 * deleting one row. Aliases are always one hop — merging into a name that is
 * itself merged away points at the end of the chain.
 */
class MergeDevelopersAction
{
    public function __construct(protected AuditLogger $audit) {}

    public function merge(string $alias, string $canon, ?string $aliasName = null): string
    {
        $alias = DeveloperKey::make($alias);
        $canon = DeveloperKey::make($canon);

        if ($alias === '' || $canon === '') {
            throw ValidationException::withMessages(['merge' => __('A merge needs both names.')]);
        }

        $seen = [$canon => true];

        while (($next = DeveloperAlias::find($canon)?->canon_key) && ! isset($seen[$next])) {
            $canon = $next;
            $seen[$canon] = true;
        }

        if ($alias === $canon) {
            throw ValidationException::withMessages(['merge' => __('A developer cannot be merged into itself.')]);
        }

        DB::transaction(function () use ($alias, $canon, $aliasName) {
            $before = DeveloperAlias::find($alias)?->only(['canon_key', 'alias_name']);

            DeveloperAlias::updateOrCreate(
                ['alias_key' => $alias],
                ['canon_key' => $canon, 'alias_name' => filled($aliasName) ? trim($aliasName) : null, 'created_at' => now()],
            );

            DeveloperAlias::where('canon_key', $alias)->where('alias_key', '!=', $alias)->update(['canon_key' => $canon]);

            $this->audit->log('merge', 'developer_aliases', $alias, before: $before, after: ['canon_key' => $canon, 'alias_name' => $aliasName]);
        });

        return $canon;
    }

    public function separate(string $alias): void
    {
        $row = DeveloperAlias::find(DeveloperKey::make($alias));

        if (! $row) {
            throw ValidationException::withMessages(['merge' => __("':alias' is not merged into anything.", ['alias' => $alias])]);
        }

        DB::transaction(function () use ($row) {
            $before = $row->only(['canon_key', 'alias_name']);
            $row->delete();

            $this->audit->log('delete', 'developer_aliases', $row->alias_key, before: $before);
        });
    }
}
