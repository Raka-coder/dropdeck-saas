<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DropLinkTarget;
use App\Models\Attachment;
use App\Models\DropLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DropLink>
 */
class DropLinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'target_type' => DropLinkTarget::File,
            'attachment_id' => Attachment::factory(),
            'card_id' => null,
            'token' => Str::random(40),
            'label' => fake()->optional()->sentence(2),
            'password_hash' => null,
            'expires_at' => now()->addDays(7),
            'max_downloads' => null,
            'download_count' => 0,
            'last_accessed_at' => null,
            'revoked_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
        ]);
    }

    public function withPassword(string $password = 'secret'): static
    {
        return $this->state(fn (array $attributes) => [
            'password_hash' => bcrypt($password),
        ]);
    }
}
