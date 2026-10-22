<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\DropLink;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class RecentLinks extends Component
{
    /**
     * @return Collection<int, DropLink>
     */
    #[Computed]
    public function links(): Collection
    {
        return DropLink::query()
            ->where('user_id', Auth::id())
            ->with(['attachment:id,original_name', 'card:id,title'])
            ->latest('id')
            ->limit(5)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.dashboard.recent-links');
    }
}
