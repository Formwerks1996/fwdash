<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Collection;

/*
 * Click-to-sort tables: text columns start ascending, figures descending, and
 * blanks always sort last whichever way the column runs.
 */
trait SortsRows
{
    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sort = $column;
        $this->direction = in_array($column, $this->textColumns, true) ? 'asc' : 'desc';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    protected function sortRows(Collection $rows): Collection
    {
        $column = $this->sort;
        $descending = $this->direction === 'desc';

        [$blank, $filled] = $rows->partition(fn (array $row) => $row[$column] === null || $row[$column] === '');

        $sorted = in_array($column, $this->textColumns, true)
            ? $filled->sortBy(fn (array $row) => mb_strtolower((string) $row[$column]), SORT_NATURAL, $descending)
            : $filled->sortBy($column, SORT_REGULAR, $descending);

        return $sorted->concat($blank)->values();
    }
}
