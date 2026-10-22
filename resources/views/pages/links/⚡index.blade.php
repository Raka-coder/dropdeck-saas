<?php

use App\Actions\DropLinks\RevokeDropLink;
use App\Enums\DropLinkStatus;
use App\Enums\DropLinkTarget;
use App\Models\Attachment;
use App\Models\Card;
use App\Models\DropLink;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('DropLink')] class extends Component {
    #[Locked]
    public ?int $revokingId = null;

    public ?int $selectedAttachmentId = null;

    public string $targetType = 'file';

    public ?int $selectedCardId = null;

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, DropLink>
     */
    #[Computed]
    public function links()
    {
        return Auth::user()->dropLinks()
            ->with(['attachment', 'card'])
            ->latest('id')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Attachment>
     */
    #[Computed]
    public function attachments()
    {
        return Auth::user()->attachments()
            ->with('card:id,title')
            ->latest('id')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Card>
     */
    #[Computed]
    public function cards()
    {
        return Card::query()
            ->where('user_id', Auth::id())
            ->with('board:id,title')
            ->latest('id')
            ->get();
    }

    public function openCreate(): void
    {
        $this->targetType = $this->attachments->isNotEmpty() ? 'file' : 'card';
        $this->selectedAttachmentId = $this->attachments->first()?->id;
        $this->selectedCardId = $this->cards->first()?->id;

        Flux::modal('link-target')->show();
    }

    public function continueCreate(): void
    {
        Flux::modal('link-target')->close();

        if ($this->targetType === 'card') {
            $card = Card::query()
                ->where('user_id', Auth::id())
                ->findOrFail($this->selectedCardId);

            $this->dispatch('droplink-request-card', cardId: $card->id);

            return;
        }

        $attachment = Auth::user()->attachments()->findOrFail($this->selectedAttachmentId);

        $this->dispatch('droplink-request', attachmentId: $attachment->id);
    }

    public function confirmRevoke(int $linkId): void
    {
        $this->revokingId = $this->findLink($linkId)->id;

        Flux::modal('revoke-droplink')->show();
    }

    public function revoke(RevokeDropLink $revoke): void
    {
        if ($this->revokingId === null) {
            return;
        }

        $link = $this->findLink($this->revokingId);

        $this->authorize('update', $link);

        $revoke->handle(Auth::user(), $link);

        Flux::modal('revoke-droplink')->close();

        $this->revokingId = null;

        Flux::toast(variant: 'success', text: __('droplinks.revoked'));

        unset($this->links);
    }

    private function findLink(int $linkId): DropLink
    {
        return Auth::user()->dropLinks()->findOrFail($linkId);
    }
}; ?>

<div>
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('droplinks.title') }}</flux:heading>
                <flux:text class="mt-1">{{ __('droplinks.subheading') }}</flux:text>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button :href="route('links.activity')" wire:navigate>
                    {{ __('dashboard.activity.view_all') }}
                </flux:button>

                <flux:button variant="primary" icon="plus" wire:click="openCreate">
                    {{ __('droplinks.create') }}
                </flux:button>
            </div>
        </div>

        @if ($this->links->isEmpty())
            <flux:card class="flex flex-col items-center justify-center gap-2 py-16 text-center">
                <flux:heading>{{ __('droplinks.empty') }}</flux:heading>
                <flux:text>{{ __('droplinks.empty_hint') }}</flux:text>
            </flux:card>
        @else
            <div class="space-y-3">
                @foreach ($this->links as $link)
                    @php($status = $link->status())
                    @php($target = $link->target_type === DropLinkTarget::File
                        ? $link->attachment?->original_name
                        : $link->card?->title)

                    <flux:card wire:key="droplink-{{ $link->id }}" class="flex flex-col gap-3">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <flux:heading class="truncate">{{ $target ?? __('droplinks.errors.target_missing') }}</flux:heading>

                                    <flux:badge size="sm" :color="match ($status) {
                                        DropLinkStatus::Active => 'emerald',
                                        DropLinkStatus::Expired => 'amber',
                                        DropLinkStatus::Exhausted => 'zinc',
                                        DropLinkStatus::Revoked => 'red',
                                    }">
                                        {{ __('droplinks.statuses.'.$status->value) }}
                                    </flux:badge>
                                </div>

                                @if ($link->label)
                                    <flux:text class="mt-1">{{ $link->label }}</flux:text>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="clipboard"
                                    type="button"
                                    x-on:click="navigator.clipboard.writeText(@js(route('droplink.show', $link->token))); $flux.toast(@js(__('droplinks.copied')))"
                                >
                                    {{ __('droplinks.copy') }}
                                </flux:button>

                                @if ($status === DropLinkStatus::Active)
                                    <flux:button
                                        variant="danger"
                                        size="sm"
                                        wire:click="confirmRevoke({{ $link->id }})"
                                    >
                                        {{ __('droplinks.revoke') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>

                        <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="text-neutral-500">{{ __('droplinks.expires_at') }}</dt>
                                <dd class="mt-0.5">{{ $link->expires_at->translatedFormat('d M Y H:i') }}</dd>
                            </div>

                            <div>
                                <dt class="text-neutral-500">{{ __('droplinks.downloads') }}</dt>
                                <dd class="mt-0.5">
                                    {{ $link->download_count }} / {{ $link->max_downloads ?? '∞' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-neutral-500">{{ __('droplinks.remaining') }}</dt>
                                <dd class="mt-0.5">
                                    {{ $link->remainingDownloads() ?? __('droplinks.unlimited') }}
                                </dd>
                            </div>
                        </dl>
                    </flux:card>
                @endforeach
            </div>
        @endif
    </div>

    <flux:modal name="link-target" class="w-full md:max-w-md">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('droplinks.create') }}</flux:heading>
                <flux:text class="mt-1">{{ __('droplinks.create_hint') }}</flux:text>
            </div>

            @if ($this->attachments->isEmpty() && $this->cards->isEmpty())
                <flux:text>{{ __('droplinks.no_targets_hint') }}</flux:text>

                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('droplinks.close') }}</flux:button>
                    </flux:modal.close>
                </div>
            @else
                <flux:radio.group wire:model.live="targetType" :label="__('droplinks.target')">
                    @if ($this->attachments->isNotEmpty())
                        <flux:radio value="file" :label="__('droplinks.pick_attachment')" />
                    @endif

                    @if ($this->cards->isNotEmpty())
                        <flux:radio value="card" :label="__('droplinks.pick_card')" />
                    @endif
                </flux:radio.group>

                @if ($targetType === 'file')
                    <flux:select wire:model="selectedAttachmentId" :label="__('droplinks.pick_attachment')">
                        @foreach ($this->attachments as $attachment)
                            <flux:select.option value="{{ $attachment->id }}">
                                {{ $attachment->original_name }}@if ($attachment->card) — {{ $attachment->card->title }}@endif
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @else
                    <flux:select wire:model="selectedCardId" :label="__('droplinks.pick_card')">
                        @foreach ($this->cards as $card)
                            <flux:select.option value="{{ $card->id }}">
                                {{ $card->title }}@if ($card->board) — {{ $card->board->title }}@endif
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('droplinks.cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="primary" wire:click="continueCreate">
                        {{ __('droplinks.continue') }}
                    </flux:button>
                </div>
            @endif
        </div>
    </flux:modal>

    <livewire:links.create-modal />

    <flux:modal name="revoke-droplink" class="w-full md:max-w-md">
        <div class="space-y-5">
            <flux:heading size="lg">{{ __('droplinks.revoke_confirm') }}</flux:heading>
            <flux:text>{{ __('droplinks.revoke_hint') }}</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('droplinks.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="revoke">{{ __('droplinks.revoke') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
