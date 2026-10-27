<?php

namespace Database\Factories;

use App\Enums\TimeCategory;
use App\Models\TimesheetEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimesheetEntry>
 */
class TimesheetEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'work_date' => now()->startOfWeek()->toDateString(),
            'category' => TimeCategory::Office,
            'job_number' => null,
            'phase_code' => null,
            'hours' => fake()->randomElement(['0.50', '1.00', '2.50', '7.50']),
            'note' => fake()->optional()->sentence(),
        ];
    }

    public function forProject(string $jobNumber, ?string $phaseCode = null): static
    {
        return $this->state(fn () => [
            'category' => TimeCategory::Project,
            'job_number' => $jobNumber,
            'phase_code' => $phaseCode,
        ]);
    }
}
