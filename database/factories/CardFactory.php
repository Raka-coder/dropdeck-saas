<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'board_id' => Board::factory(),
            'column_id' => BoardColumn::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'position' => fake()->unique()->numberBetween(0, 999),
        ];
    }
}
