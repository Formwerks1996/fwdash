<?php

namespace App\Models;

use App\Enums\PursuitStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $developer
 * @property ?string $project
 * @property ?string $fee
 * @property PursuitStage $stage
 * @property ?string $owner
 * @property ?string $note
 */
#[Fillable(['developer', 'project', 'fee', 'stage', 'owner', 'note'])]
class Pursuit extends Model
{
    protected function casts(): array
    {
        return [
            'stage' => PursuitStage::class,
            'fee' => 'decimal:2',
        ];
    }
}
