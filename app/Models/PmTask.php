<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $job_number
 * @property string $title
 * @property ?string $assignee team_members.name
 * @property ?CarbonImmutable $due_on
 * @property ?CarbonImmutable $done_at
 * @property ?int $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['job_number', 'title', 'assignee', 'due_on', 'done_at', 'created_by'])]
class PmTask extends Model
{
    protected function casts(): array
    {
        return [
            'due_on' => 'date:Y-m-d',
            'done_at' => 'datetime',
        ];
    }

    /** @return array{job_number: string, title: string, assignee: ?string, due_on: ?string, done_at: ?string} */
    public function auditValues(): array
    {
        return [
            'job_number' => $this->job_number,
            'title' => $this->title,
            'assignee' => $this->assignee,
            'due_on' => $this->due_on?->toDateString(),
            'done_at' => $this->done_at?->toDateTimeString(),
        ];
    }
}
