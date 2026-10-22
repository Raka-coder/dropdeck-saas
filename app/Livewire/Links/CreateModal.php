<?php

declare(strict_types=1);

namespace App\Livewire\Links;

use App\Actions\DropLinks\CreateDropLink;
use App\Models\Attachment;
use App\Models\Card;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

final class CreateModal extends Component
{
    #[Locked]
    public ?int $attachmentId = null;

    #[Locked]
    public ?int $cardId = null;

    public int $expiresInHours = 24;

    public ?int $maxDownloads = null;

    public string $password = '';

    public string $label = '';

    #[Locked]
    public ?string $createdUrl = null;

    #[On('droplink-request')]
    public function openForAttachment(int $attachmentId): void
    {
        $attachment = Attachment::query()
            ->where('user_id', auth()->id())
            ->whereKey($attachmentId)
            ->firstOrFail();

        $this->resetForm();
        $this->attachmentId = $attachment->id;
        $this->cardId = null;

        Flux::modal('droplink-create')->show();
    }

    #[On('droplink-request-card')]
    public function openForCard(int $cardId): void
    {
        $card = Card::query()
            ->where('user_id', auth()->id())
            ->whereKey($cardId)
            ->firstOrFail();

        $this->resetForm();
        $this->cardId = $card->id;
        $this->attachmentId = null;

        Flux::modal('droplink-create')->show();
    }

    public function save(CreateDropLink $create): void
    {
        $this->label = trim($this->label);
        $this->password = trim($this->password);

        $this->validate([
            'expiresInHours' => ['required', 'integer', 'min:1', 'max:'.((int) config('dropdeck.limits.link_max_days') * 24)],
            'maxDownloads' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('dropdeck.limits.link_max_downloads')],
            'password' => $this->password === '' ? ['nullable'] : ['string', 'min:4', 'max:64'],
            'label' => ['nullable', 'string', 'max:120'],
        ]);

        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        if ($this->isRateLimited($user)) {
            return;
        }

        $link = $create->handle(
            $user,
            $this->resolveTarget(),
            now()->addHours($this->expiresInHours),
            $this->maxDownloads,
            $this->password === '' ? null : $this->password,
            $this->label === '' ? null : $this->label,
        );

        $this->createdUrl = route('droplink.show', $link->token);

        $this->dispatch('droplink-created');

        Flux::toast(variant: 'success', text: __('droplinks.created'));
    }

    public function close(): void
    {
        Flux::modal('droplink-create')->close();

        $this->resetForm();
    }

    public function render(): View
    {
        return view('livewire.links.create-modal', [
            'targetName' => $this->targetName(),
        ]);
    }

    #[Computed]
    public function targetName(): string
    {
        if ($this->attachmentId !== null) {
            return (string) Attachment::query()->whereKey($this->attachmentId)->value('original_name');
        }

        if ($this->cardId !== null) {
            return (string) Card::query()->whereKey($this->cardId)->value('title');
        }

        return '';
    }

    private function isRateLimited(User $user): bool
    {
        $key = 'droplink-create:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, (int) config('dropdeck.limits.link_create_per_minute'))) {
            $this->addError('label', __('droplinks.errors.rate_limited'));

            return true;
        }

        RateLimiter::hit($key, 60);

        return false;
    }

    private function resolveTarget(): Attachment|Card
    {
        if ($this->attachmentId !== null) {
            return Attachment::query()
                ->where('user_id', auth()->id())
                ->whereKey($this->attachmentId)
                ->firstOrFail();
        }

        return Card::query()
            ->where('user_id', auth()->id())
            ->whereKey($this->cardId)
            ->firstOrFail();
    }

    private function resetForm(): void
    {
        $this->expiresInHours = 24;
        $this->maxDownloads = null;
        $this->password = '';
        $this->label = '';
        $this->createdUrl = null;
        $this->resetValidation();
    }
}
