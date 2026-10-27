<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Who looks after a project. projects.project_lead is the partner on the
 * contract; this is the PM (or a team member) doing the work.
 *
 * @property int $id
 * @property string $job_number
 * @property string $member_name
 * @property string $role
 * @property ?string $position
 * @property string $set_by
 * @property CarbonImmutable $set_at
 */
#[Fillable(['job_number', 'member_name', 'role', 'position', 'set_by', 'set_at'])]
class ProjectAssignment extends Model
{
    public const Roles = ['pm', 'member'];

    /** What people do on a job, as Mosaic's project roles had it. */
    public const Positions = [
        'Principal', 'Senior Project Manager', 'Project Manager', 'Technologist',
        'Senior Interior Designer', 'Interior Designer', 'Senior Landscape Designer',
        'Intermediate Landscape Designer', 'Landscape Designer', 'Junior Landscape Designer',
        'Visualization Specialist', 'Planner',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'set_at' => 'datetime',
        ];
    }
}
