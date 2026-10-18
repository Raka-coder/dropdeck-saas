<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ThumbnailStatus;
use App\Models\Attachment;
use App\Models\Card;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->word().'.pdf';

        return [
            'user_id' => User::factory(),
            'card_id' => Card::factory(),
            'disk' => 'vault',
            'path' => 'attachments/'.fake()->uuid().'/'.$name,
            'original_name' => $name,
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1024, 5_000_000),
            'checksum_sha256' => hash('sha256', fake()->uuid()),
            'thumbnail_path' => null,
            'thumbnail_status' => ThumbnailStatus::None,
        ];
    }
}
