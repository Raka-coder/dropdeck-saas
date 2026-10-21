<?php

declare(strict_types=1);

namespace App\Livewire\Vault;

use App\Actions\Vault\DeleteAttachment;
use App\Models\Card;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

final class AttachmentList extends Component
{
    #[Locked]
    public int $cardId;

    #[Locked]
    public ?int $deletingId = null;

    public function mount(int $cardId): void
    {
        $this->cardId = $this->resolveCard($cardId)->id;
    }

    #[On('attachment-uploaded')]
    public function refreshList(): void
    {
        //
    }

    public function confirmDelete(int $attachmentId): void
    {
        $this->deletingId = $this->resolveCard($this->cardId)
            ->attachments()
            ->whereKey($attachmentId)
            ->value('id');

        abort_if($this->deletingId === null, 404);
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
    }

    public function delete(DeleteAttachment $delete): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $attachment = $this->resolveCard($this->cardId)
            ->attachments()
            ->whereKey($this->deletingId)
            ->firstOrFail();

        $delete->handle($user, $attachment);

        $this->deletingId = null;

        Flux::toast(variant: 'success', text: __('attachments.deleted'));
    }

    public function render(): View
    {
        return view('livewire.vault.attachment-list', [
            'attachments' => $this->resolveCard($this->cardId)
                ->attachments()
                ->orderBy('id')
                ->get(),
        ]);
    }

    private function resolveCard(int $cardId): Card
    {
        return Card::query()
            ->where('user_id', auth()->id())
            ->whereKey($cardId)
            ->firstOrFail();
    }
}
