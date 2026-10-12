<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'done' => false,
            'due_date' => fake()->optional()->dateTimeBetween('now', '+1 month')?->format('Y-m-d'),
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => ['done' => true]);
    }
}
