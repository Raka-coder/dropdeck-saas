<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Card;
use App\Models\ChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            'content' => fake()->sentence(5),
            'is_done' => fake()->boolean(30),
            'position' => fake()->unique()->numberBetween(0, 999),
        ];
    }
}
