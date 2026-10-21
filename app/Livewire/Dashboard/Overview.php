<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Attachment;
use App\Models\Board;
use App\Models\Card;
use App\Models\DropLink;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class Overview extends Component
{
    /**
     * @return array{boards: int, cards: int, files: int, activeLinks: int}
     */
    #[Computed]
    public function stats(): array
    {
        $userId = (int) Auth::id();

        return [
            'boards' => Board::query()
                ->where('user_id', $userId)
                ->whereNull('archived_at')
                ->count(),
            'cards' => Card::query()
                ->whereIn('board_id', Board::query()->where('user_id', $userId)->select('id'))
                ->count(),
            'files' => Attachment::query()
                ->where('user_id', $userId)
                ->count(),
            'activeLinks' => DropLink::query()
                ->where('user_id', $userId)
                ->active()
                ->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.dashboard.overview');
    }
}
