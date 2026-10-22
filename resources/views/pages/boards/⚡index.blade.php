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
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Papan')] class extends Component {
    public BoardForm $form;

    public bool $showArchived = false;

    public string $search = '';

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
        $term = '%'.addcslashes(trim($this->search), '%_\\').'%';

        return Auth::user()->boards()
            ->when($this->showArchived, fn ($query) => $query->whereNotNull('archived_at'))
            ->when(! $this->showArchived, fn ($query) => $query->whereNull('archived_at'))
            ->when(trim($this->search) !== '', fn ($query) => $query->where(
                fn ($inner) => $inner->where('title', 'like', $term)->orWhere('description', 'like', $term),
            ))
            ->withCount('cards')
            ->orderBy('title')
            ->get();
    }

    public function mount(): void
    {
        if (request()->boolean('create')) {
            $this->openCreate();
        }
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

    public function validateField(string $field): void
    {
        if (! in_array($field, ['title', 'description', 'color'], true)) {
            return;
        }

        $this->validateOnly('form.'.$field);
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

            try {
                $createBoard->handle(
                    Auth::user(),
                    $this->form->title,
                    $this->form->descriptionOrNull(),
                    $this->form->color,
                );
            } catch (ValidationException $exception) {
                $this->addError('form.title', $exception->validator->errors()->first());

                return;
            }

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
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('dashboard.title') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('boards.title') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('boards.title') }}</flux:heading>
                <flux:text class="mt-1">{{ __('boards.subheading') }}</flux:text>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    :placeholder="__('boards.search_placeholder')"
                    class="max-w-56"
                />

                <flux:button variant="ghost" icon="archive" wire:click="$toggle('showArchived')">
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
                    <flux:button variant="primary" icon="plus" class="mt-2" wire:click="openCreate">
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
                                    <flux:menu.item icon="pencil" wire:click="openEdit({{ $board->id }})">
                                        {{ __('boards.edit') }}
                                    </flux:menu.item>

                                    @if ($board->archived_at)
                                        <flux:menu.item icon="rotate-ccw" wire:click="restore({{ $board->id }})">
                                            {{ __('boards.restore') }}
                                        </flux:menu.item>
                                    @else
                                        <flux:menu.item icon="archive" wire:click="archive({{ $board->id }})">
                                            {{ __('boards.archive') }}
                                        </flux:menu.item>
                                    @endif

                                    <flux:menu.separator />

                                    <flux:menu.item variant="danger" icon="trash-2" wire:click="confirmDelete({{ $board->id }})">
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

                            <flux:button variant="ghost" size="sm" icon="external-link" :href="route('boards.show', $board)" wire:navigate>
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

            <div>
                <flux:input
                    wire:model="form.title"
                    x-on:blur="$wire.validateField('title')"
                    :label="__('boards.name')"
                    required
                    autofocus
                />

                @error('form.title')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>

            <div>
                <flux:textarea
                    wire:model="form.description"
                    x-on:blur="$wire.validateField('description')"
                    :label="__('boards.description')"
                    rows="3"
                />

                @error('form.description')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>

            <div>
                <flux:label>{{ __('boards.color') }}</flux:label>

                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <input
                        type="color"
                        wire:model.live="form.color"
                        x-on:blur="$wire.validateField('color')"
                        class="h-10 w-16 cursor-pointer rounded-md border border-neutral-300 bg-transparent dark:border-neutral-700"
                    >

                    <div class="flex items-center gap-2">
                        @foreach (['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'] as $preset)
                            <button
                                type="button"
                                wire:click="$set('form.color', '{{ $preset }}')"
                                class="size-6 rounded-full border-2 border-white shadow ring-1 ring-black/10 transition hover:scale-110 dark:border-neutral-900"
                                style="background-color: {{ $preset }}"
                                aria-label="{{ $preset }}"
                            ></button>
                        @endforeach
                    </div>

                    <span class="font-mono text-xs text-neutral-500">{{ $form->color }}</span>
                </div>

                @error('form.color')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>

            @if (trim($form->title) !== '')
                <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-700 dark:bg-neutral-900/40">
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">
                        {{ __('boards.preview') }}
                    </p>

                    <div class="flex items-start gap-3">
                        <span
                            class="mt-1 size-3 shrink-0 rounded-full ring-1 ring-black/10"
                            style="background-color: {{ $form->color }}"
                        ></span>

                        <div class="min-w-0">
                            <p class="truncate font-medium text-neutral-800 dark:text-neutral-100">{{ $form->title }}</p>

                            @if (trim($form->description) !== '')
                                <p class="mt-0.5 line-clamp-2 text-sm text-neutral-500">{{ $form->description }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" icon="x">{{ __('boards.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button
                    variant="primary"
                    type="submit"
                    icon="check"
                    wire:loading.attr="disabled"
                    wire:target="save"
                >
                    <span wire:loading.remove wire:target="save">{{ __('boards.save') }}</span>
                    <span wire:loading wire:target="save">{{ __('boards.saving') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-board" class="w-full md:max-w-md">
        <div class="space-y-5">
            <flux:heading size="lg">{{ __('boards.delete_confirm') }}</flux:heading>
            <flux:text>{{ __('boards.delete_hint') }}</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" icon="x">{{ __('boards.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" icon="trash-2" wire:click="delete">{{ __('boards.delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
