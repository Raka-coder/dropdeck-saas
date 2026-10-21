<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Enums\AccessEvent;
use App\Models\DropLinkAccess;
use DateTimeImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class ActivityLog extends Component
{
    use WithPagination;

    #[Url]
    public string $event = '';

    #[Url]
    public string $date = '';

    public int $perPage = 15;

    /**
     * @return LengthAwarePaginator<int, DropLinkAccess>
     */
    #[Computed]
    public function accesses(): LengthAwarePaginator
    {
        $userId = (int) Auth::id();
        $event = $this->eventFilter();
        $date = $this->dateFilter();

        return DropLinkAccess::query()
            ->whereHas('droplink', fn (Builder $query) => $query->where('user_id', $userId))
            ->with(['droplink:id,label,token,target_type'])
            ->when($event !== null, fn (Builder $query) => $query->where('event', $event))
            ->when($date !== null, fn (Builder $query) => $query->whereDate('created_at', $date))
            ->latest('id')
            ->paginate($this->perPage);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function eventOptions(): array
    {
        $options = [];

        foreach (AccessEvent::cases() as $case) {
            $options[$case->value] = (string) __('dashboard.events.'.$case->value);
        }

        return $options;
    }

    public function updatedEvent(): void
    {
        $this->resetPage();
    }

    public function updatedDate(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->event = '';
        $this->date = '';

        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.dashboard.activity-log');
    }

    private function eventFilter(): ?string
    {
        return AccessEvent::tryFrom($this->event)?->value;
    }

    private function dateFilter(): ?string
    {
        if ($this->date === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $this->date);

        if ($date === false || $date->format('Y-m-d') !== $this->date) {
            return null;
        }

        return $this->date;
    }
}
