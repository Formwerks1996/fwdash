<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['job_number', 'status', 'note', 'set_by', 'set_at'])]
class ProjectStatus extends Model
{
    public const Statuses = ['active', 'paused', 'closed'];

    protected $primaryKey = 'job_number';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'set_at' => 'datetime',
        ];
    }
}
