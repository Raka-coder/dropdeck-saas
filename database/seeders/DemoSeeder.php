<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DropLinkTarget;
use App\Models\Attachment;
use App\Models\Board;
use App\Models\Card;
use App\Models\DropLink;
use App\Models\Label;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@dropdeck.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $board = Board::factory()->create([
            'user_id' => $user->id,
            'title' => 'Alur Konten',
            'description' => 'Papan contoh untuk mengelola alur produksi konten.',
        ]);

        $columnTitles = ['Ide', 'Draft', 'Review', 'Publish'];

        $columns = collect($columnTitles)
            ->map(fn (string $title, int $index) => $board->columns()->create([
                'title' => $title,
                'position' => $index,
            ]));

        $labels = collect([
            ['name' => 'Prioritas', 'color' => '#EF4444'],
            ['name' => 'Konten', 'color' => '#3B82F6'],
            ['name' => 'Desain', 'color' => '#8B5CF6'],
        ])->map(fn (array $data) => Label::factory()->create([
            'board_id' => $board->id,
            'name' => $data['name'],
            'color' => $data['color'],
        ]));

        $ideas = $columns->first();

        $cards = collect([
            'Rencana konten bulan depan',
            'Naskah video tutorial',
            'Desain thumbnail baru',
            'Artikel blog: tips produktivitas',
            'Kampanye media sosial',
        ])->map(fn (string $title, int $index) => Card::factory()->create([
            'user_id' => $user->id,
            'board_id' => $board->id,
            'column_id' => $ideas->id,
            'title' => $title,
            'position' => $index,
        ]));

        $cards->each(function (Card $card) use ($labels) {
            $card->labels()->attach(
                $labels->random(2)->pluck('id')->all()
            );

            $card->checklistItems()->createMany([
                ['content' => 'Riset referensi', 'position' => 0],
                ['content' => 'Buat draft', 'position' => 1],
            ]);
        });

        $firstCard = $cards->first();

        $attachment = Attachment::factory()->create([
            'user_id' => $user->id,
            'card_id' => $firstCard->id,
            'original_name' => 'brief-konten.pdf',
            'path' => 'attachments/'.Str::uuid()->toString().'/brief-konten.pdf',
        ]);

        DropLink::factory()->create([
            'user_id' => $user->id,
            'target_type' => DropLinkTarget::File,
            'attachment_id' => $attachment->id,
            'card_id' => null,
            'token' => Str::random(40),
            'label' => 'Brief untuk klien',
            'expires_at' => now()->addDays(7),
        ]);
    }
}
