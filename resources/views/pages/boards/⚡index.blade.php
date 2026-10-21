<?php

use App\Actions\Boards\ArchiveBoard;
use App\Actions\Boards\CreateBoard;
use App\Actions\Boards\DeleteBoard;
use App\Actions\Boards\RestoreBoard;
use App\Actions\Boards\UpdateBoard;
use App\Livewire\Forms\BoardForm;
use App\Models\Board;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Papan')] class extends Component {
    public BoardForm $form;

    public bool $showArchived = false;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $deletingId = null;

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Board>
     */
    #[Computed]
    public function boards()
    {
        return Auth::user()->boards()
            ->when($this->showArchived, fn ($query) => $query->whereNotNull('archived_at'))
            ->when(! $this->showArchived, fn ($query) => $query->whereNull('archived_at'))
            ->withCount('cards')
            ->orderBy('title')
            ->get();
    }

    public function openCreate(): void
    {
        $this->authorize('create', Board::class);

        $this->editingId = null;
        $this->form->reset();
        $this->form->color = '#6366f1';
        $this->resetValidation();

        Flux::modal('board-form')->show();
    }

    public function openEdit(int $boardId): void
    {
        $board = $this->findBoard($boardId);

        $this->authorize('update', $board);

        $this->editingId = $board->id;
        $this->form->title = $board->title;
        $this->form->description = $board->description ?? '';
        $this->form->color = $board->color ?? '#6366f1';
        $this->resetValidation();

        Flux::modal('board-form')->show();
    }

    public function save(CreateBoard $createBoard, UpdateBoard $updateBoard): void
    {
        $this->form->validate();

        if ($this->editingId === null) {
            $this->authorize('create', Board::class);

            $createBoard->handle(
                Auth::user(),
                $this->form->title,
                $this->form->descriptionOrNull(),
                $this->form->color,
            );

            Flux::toast(variant: 'success', text: __('boards.created'));
        } else {
            $board = $this->findBoard($this->editingId);

            $this->authorize('update', $board);

            $updateBoard->handle(
                $board,
                $this->form->title,
                $this->form->descriptionOrNull(),
                $this->form->color,
            );

            Flux::toast(variant: 'success', text: __('boards.updated'));
        }

        Flux::modal('board-form')->close();

        $this->form->reset();
        $this->editingId = null;
        unset($this->boards);
    }

    public function archive(int $boardId, ArchiveBoard $archiveBoard): void
    {
        $board = $this->findBoard($boardId);

        $this->authorize('update', $board);

        $archiveBoard->handle($board);

        Flux::toast(variant: 'success', text: __('boards.archived'));
        unset($this->boards);
    }

    public function restore(int $boardId, RestoreBoard $restoreBoard): void
    {
        $board = $this->findBoard($boardId);

        $this->authorize('update', $board);

        $restoreBoard->handle($board);

        Flux::toast(variant: 'success', text: __('boards.restored'));
        unset($this->boards);
    }

    public function confirmDelete(int $boardId): void
    {
        $board = $this->findBoard($boardId);

        $this->authorize('delete', $board);

        $this->deletingId = $board->id;

        Flux::modal('delete-board')->show();
    }

    public function delete(DeleteBoard $deleteBoard): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $board = $this->findBoard($this->deletingId);

        $this->authorize('delete', $board);

        $deleteBoard->handle($board);

        Flux::modal('delete-board')->close();

        $this->deletingId = null;

        Flux::toast(variant: 'success', text: __('boards.deleted'));
        unset($this->boards);
    }

    private function findBoard(int $boardId): Board
    {
        return Auth::user()->boards()->findOrFail($boardId);
    }
}; ?>

<div>

    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('boards.title') }}</flux:heading>
                <flux:text class="mt-1">{{ __('boards.subheading') }}</flux:text>
            </div>

            <div class="flex items-center gap-2">
                <flux:button variant="ghost" wire:click="$toggle('showArchived')">
                    {{ $showArchived ? __('boards.show_active') : __('boards.show_archived') }}
                </flux:button>

                <flux:button variant="primary" icon="plus" wire:click="openCreate">
                    {{ __('boards.new') }}
                </flux:button>
            </div>
        </div>

        @if ($this->boards->isEmpty())
            <flux:card class="flex flex-col items-center justify-center gap-2 py-16 text-center">
                <flux:heading>{{ $showArchived ? __('boards.archived_empty') : __('boards.empty') }}</flux:heading>

                @unless ($showArchived)
                    <flux:text>{{ __('boards.empty_hint') }}</flux:text>
                    <flux:button variant="primary" class="mt-2" wire:click="openCreate">
                        {{ __('boards.create') }}
                    </flux:button>
                @endunless
            </flux:card>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->boards as $board)
                    <flux:card wire:key="board-{{ $board->id }}" class="flex flex-col gap-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span
                                    class="mt-1 size-3 shrink-0 rounded-full ring-1 ring-black/10"
                                    style="background-color: {{ $board->color ?? '#6366f1' }}"
                                ></span>

                                <div>
                                    <flux:heading>{{ $board->title }}</flux:heading>
                                    <flux:text class="mt-0.5">
                                        {{ __('boards.cards_count', ['count' => $board->cards_count]) }}
                                    </flux:text>
                                </div>
                            </div>

                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />

                                <flux:menu>
                                    <flux:menu.item wire:click="openEdit({{ $board->id }})">
                                        {{ __('boards.edit') }}
                                    </flux:menu.item>

                                    @if ($board->archived_at)
                                        <flux:menu.item wire:click="restore({{ $board->id }})">
                                            {{ __('boards.restore') }}
                                        </flux:menu.item>
                                    @else
                                        <flux:menu.item wire:click="archive({{ $board->id }})">
                                            {{ __('boards.archive') }}
                                        </flux:menu.item>
                                    @endif

                                    <flux:menu.separator />

                                    <flux:menu.item variant="danger" wire:click="confirmDelete({{ $board->id }})">
                                        {{ __('boards.delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>

                        @if ($board->description)
                            <flux:text class="line-clamp-2">{{ $board->description }}</flux:text>
                        @endif

                        <div class="mt-auto flex items-center justify-between pt-2">
                            @if ($board->archived_at)
                                <flux:badge color="zinc" size="sm">{{ __('boards.archived_badge') }}</flux:badge>
                            @else
                                <flux:badge color="emerald" size="sm">
                                    {{ __('boards.cards_count', ['count' => $board->cards_count]) }}
                                </flux:badge>
                            @endif

                            <flux:button variant="ghost" size="sm" :href="route('boards.show', $board)" wire:navigate>
                                {{ __('boards.open') }}
                            </flux:button>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @endif
    </div>

    <flux:modal name="board-form" class="w-full md:max-w-md">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">
                {{ $editingId ? __('boards.edit') : __('boards.new') }}
            </flux:heading>

            <flux:input wire:model="form.title" :label="__('boards.name')" required autofocus />

            <flux:textarea wire:model="form.description" :label="__('boards.description')" rows="3" />

            <div>
                <flux:label>{{ __('boards.color') }}</flux:label>

                <input
                    type="color"
                    wire:model="form.color"
                    class="mt-1 h-10 w-16 cursor-pointer rounded-md border border-neutral-300 bg-transparent dark:border-neutral-700"
                >

                @error('form.color')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('boards.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">{{ __('boards.save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-board" class="w-full md:max-w-md">
        <div class="space-y-5">
            <flux:heading size="lg">{{ __('boards.delete_confirm') }}</flux:heading>
            <flux:text>{{ __('boards.delete_hint') }}</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('boards.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="delete">{{ __('boards.delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
