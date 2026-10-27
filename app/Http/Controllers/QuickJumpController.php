<?php

namespace App\Http\Controllers;

use App\Billing\DeveloperIndex;
use App\Enums\Permission;
use App\Models\Project;
use App\Support\Navigation;
use Illuminate\Http\JsonResponse;

/*
 * Everything the header search can jump to, filtered by what the user may
 * open. Reads the project list and the developer index, never the billing
 * figures, so it stays cheap.
 */
class QuickJumpController
{
    public function __invoke(DeveloperIndex $developers): JsonResponse
    {
        $user = request()->user();

        $pages = collect(Navigation::for($user))
            ->flatten(1)
            ->map(fn (array $page) => ['kind' => 'Page', 'label' => __($page['label']), 'sub' => null, 'text' => mb_strtolower(__($page['label'])), 'url' => route($page['route'])]);

        $visible = $user->visibleJobNumbers();

        $jobs = $user->can(Permission::ViewProjects->value)
            ? Project::when($visible !== null, fn ($query) => $query->whereIn('job_number', $visible))->orderBy('job_name')->get()->map(fn (Project $project) => [
                'kind' => 'Job',
                'label' => "{$project->job_number} {$project->job_name}",
                'sub' => collect([$project->developer, $project->division, $project->active ? null : __('closed')])->filter()->implode(' · '),
                'text' => mb_strtolower("{$project->job_number} {$project->job_name} {$project->developer}"),
                'url' => route('projects.show', $project->job_number),
            ])
            : collect();

        $developerRows = $user->can(Permission::ViewBusinessDevelopment->value)
            ? collect($developers->all())->map(fn (array $developer) => [
                'kind' => 'Developer',
                'label' => $developer['name'],
                'sub' => $developer['city'],
                'text' => mb_strtolower($developer['name']),
                'url' => route('developers.show', $developer['key']),
            ])->values()
            : collect();

        return response()->json([...$pages, ...$jobs, ...$developerRows]);
    }
}
