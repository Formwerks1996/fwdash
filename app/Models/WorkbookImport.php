<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $filename
 * @property array<string, int> $counts
 * @property array<int, string> $warnings
 * @property array{checked: int, mismatches: array<int, string>} $reconciliation
 */
#[Fillable(['user_id', 'filename', 'counts', 'warnings', 'reconciliation'])]
class WorkbookImport extends Model
{
    protected function casts(): array
    {
        return [
            'counts' => 'array',
            'warnings' => 'array',
            'reconciliation' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
