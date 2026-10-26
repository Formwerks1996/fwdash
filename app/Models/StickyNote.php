<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Route;

/**
 * @property string $id
 * @property string $place_target
 * @property ?string $place_arg
 * @property string $place_label
 * @property string $body
 * @property string $author
 * @property string $pos_x
 * @property int $pos_y
 * @property ?CarbonImmutable $updated_at
 */
#[Fillable(['place_target', 'place_arg', 'place_label', 'body', 'author', 'user_id', 'status', 'pos_x', 'pos_y', 'deleted_by', 'edited_at'])]
class StickyNote extends Model
{
    use HasUlids;
    use SoftDeletes;

    public const MaxLength = 4000;

    protected function casts(): array
    {
        return [
            'pos_y' => 'integer',
            'edited_at' => 'datetime',
        ];
    }

    public function url(): ?string
    {
        if (! Route::has($this->place_target)) {
            return null;
        }

        return $this->place_arg === null
            ? route($this->place_target)
            : route($this->place_target, $this->place_arg);
    }
}
