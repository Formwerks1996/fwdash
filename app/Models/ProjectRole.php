<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Who leads and who manages a project, as Formwerks sets it rather than as
 * Mosaic has it (MosaicProjectRole). A lead is the partner over the project;
 * the PM runs it under them.
 *
 * @property int $id
 * @property string $job_number
 * @property string $member_name
 * @property string $role
 * @property string $set_by
 * @property CarbonImmutable $set_at
 */
#[Fillable(['job_number', 'member_name', 'role', 'set_by', 'set_at'])]
class ProjectRole extends Model
{
    public const Lead = 'lead';

    public const ProjectManager = 'pm';

    public const Roles = [self::Lead, self::ProjectManager];

    public $timestamps = false;

    /**
     * The leads and PMs of each project, by job number.
     *
     * @param  ?array<int, string>  $jobNumbers  null for every project
     * @return array<string, array{lead: array<int, string>, pm: array<int, string>}>
     */
    public static function byJob(?array $jobNumbers = null): array
    {
        $byJob = [];

        self::query()
            ->when($jobNumbers !== null, fn ($query) => $query->whereIn('job_number', $jobNumbers))
            ->orderBy('member_name')
            ->get(['job_number', 'member_name', 'role'])
            ->each(function (self $role) use (&$byJob) {
                $byJob[$role->job_number] ??= [self::Lead => [], self::ProjectManager => []];
                $byJob[$role->job_number][$role->role][] = $role->member_name;
            });

        return $byJob;
    }

    protected function casts(): array
    {
        return [
            'set_at' => 'datetime',
        ];
    }
}
