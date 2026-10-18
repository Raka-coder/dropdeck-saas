<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Board;
use App\Models\BoardColumn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardColumn>
 */
class BoardColumnFactory extends Factory
{
    public function definition(): array
    {
        return [
            'board_id' => Board::factory(),
            'title' => fake()->randomElement(['Todo', 'In Progress', 'Review', 'Done']),
            'position' => fake()->unique()->numberBetween(0, 999),
        ];
    }
}
