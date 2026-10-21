<?php

use App\Actions\Cards\CreateCard;
use App\Actions\Cards\DeleteCard;
use App\Actions\Cards\MoveCard;
use App\Actions\Cards\UpdateCard;
use App\Actions\Checklists\AddItem;
use App\Actions\Checklists\RemoveItem;
use App\Actions\Checklists\ToggleItem;
use App\Actions\Columns\CreateColumn;
use App\Actions\Columns\DeleteColumn;
use App\Actions\Columns\MoveColumn;
use App\Actions\Columns\RenameColumn;
use App\Actions\Labels\AttachLabel;
use App\Actions\Labels\CreateLabel;
use App\Actions\Labels\DetachLabel;
use App\Livewire\Forms\CardForm;
use App\Livewire\Forms\ChecklistItemForm;
use App\Livewire\Forms\ColumnForm;
use App\Livewire\Forms\LabelForm;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\Label;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Papan')] class extends Component {
    public Board $board;

    public CardForm $cardForm;

    public ColumnForm $columnForm;

    public LabelForm $labelForm;

    public ChecklistItemForm $checklistForm;

    #[Locked]
    public ?int $selectedCardId = null;

    #[Locked]
    public ?int $renamingColumnId = null;

    #[Locked]
    public ?int $deletingColumnId = null;

    #[Locked]
    public ?int $deletingCardId = null;

    public string $renamingColumnTitle = '';

    /**
     * @var array<int, string>
     */
    public array $newCardTitles = [];

    public function mount(Board $board): void
    {
        abort_unless($board->user_id === Auth::id(), 404);

        $this->authorize('view', $board);

        $this->board = $board;
        $this->labelForm->boardId = $board->id;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, BoardColumn>
     */
    #[Computed]
    public function columns()
    {
        return $this->board->columns()
            ->with(['cards' => fn ($query) => $query
                ->with('labels')
                ->withCount([
                    'attachments',
                    'checklistItems',
                    'checklistItems as completed_checklist_items_count' => fn ($count) => $count->where('is_done', true),
                ])
                ->orderBy('position')
                ->orderBy('id'),
            ])
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Label>
     */
    #[Computed]
    public function labels()
    {
        return $this->board->labels()->orderBy('name')->get();
    }

    #[Computed]
    public function selectedCard(): ?Card
    {
        if ($this->selectedCardId === null) {
            return null;
        }

        return $this->board->cards()
            ->with(['labels', 'checklistItems', 'attachments'])
            ->find($this->selectedCardId);
    }

    public function createColumn(): void
    {
        $this->columnForm->validate();

        app(CreateColumn::class)->handle($this->board, $this->columnForm->title);

        $this->columnForm->reset();

        unset($this->columns);

        Flux::toast(variant: 'success', text: __('columns.created'));
    }

    public function openRenameColumn(int $columnId): void
    {
        $column = $this->findColumn($columnId);

        $this->authorize('update', $this->board);

        $this->renamingColumnId = $column->id;
        $this->renamingColumnTitle = $column->title;
        $this->resetValidation();

        Flux::modal('rename-column')->show();
    }

    public function renameColumn(RenameColumn $renameColumn): void
    {
        if ($this->renamingColumnId === null) {
            return;
        }

        $this->validate(
            ['renamingColumnTitle' => ['required', 'string', 'max:60']],
            [
                'renamingColumnTitle.required' => __('columns.validation.title_required'),
                'renamingColumnTitle.max' => __('columns.validation.title_max'),
            ],
        );

        $column = $this->findColumn($this->renamingColumnId);

        $renameColumn->handle($column, $this->renamingColumnTitle);

        Flux::modal('rename-column')->close();

        $this->renamingColumnId = null;
        $this->renamingColumnTitle = '';

        unset($this->columns);

        Flux::toast(variant: 'success', text: __('columns.renamed'));
    }

    public function confirmDeleteColumn(int $columnId): void
    {
        $column = $this->findColumn($columnId);

        $this->authorize('update', $this->board);

        $this->deletingColumnId = $column->id;

        Flux::modal('delete-column')->show();
    }

    public function deleteColumn(DeleteColumn $deleteColumn): void
    {
        if ($this->deletingColumnId === null) {
            return;
        }

        $deleteColumn->handle($this->findColumn($this->deletingColumnId));

        Flux::modal('delete-column')->close();

        $this->deletingColumnId = null;

        unset($this->columns);

        Flux::toast(variant: 'success', text: __('columns.deleted'));
    }

    public function handleColumnSort(int $columnId, int $position): void
    {
        app(MoveColumn::class)->handle($this->findColumn($columnId), $position);

        unset($this->columns);
    }

    public function addCard(int $columnId): void
    {
        $column = $this->findColumn($columnId);
        $title = trim((string) ($this->newCardTitles[$columnId] ?? ''));

        if ($title === '') {
            return;
        }

        $this->validate(
            ["newCardTitles.$columnId" => ['required', 'string', 'min:2', 'max:120']],
            [
                "newCardTitles.$columnId.required" => __('cards.validation.title_required'),
                "newCardTitles.$columnId.min" => __('cards.validation.title_min'),
                "newCardTitles.$columnId.max" => __('cards.validation.title_max'),
            ],
        );

        app(CreateCard::class)->handle($this->board, $column, $title);

        unset($this->newCardTitles[$columnId], $this->columns);

        Flux::toast(variant: 'success', text: __('cards.created'));
    }

    public function openCard(int $cardId): void
    {
        $card = $this->findCard($cardId);

        $this->authorize('view', $card);

        $this->selectedCardId = $card->id;
        $this->cardForm->title = $card->title;
        $this->cardForm->description = $card->description ?? '';
        $this->checklistForm->reset();
        $this->resetValidation();

        Flux::modal('card-modal')->show();
    }

    public function saveCard(UpdateCard $updateCard): void
    {
        if ($this->selectedCardId === null) {
            return;
        }

        $this->cardForm->validate();

        $card = $this->findCard($this->selectedCardId);

        $this->authorize('update', $card);

        $updateCard->handle($card, $this->cardForm->title, $this->cardForm->descriptionOrNull());

        unset($this->selectedCard, $this->columns);

        Flux::toast(variant: 'success', text: __('cards.updated'));
    }

    public function confirmDeleteCard(int $cardId): void
    {
        $card = $this->findCard($cardId);

        $this->authorize('delete', $card);

        $this->deletingCardId = $card->id;

        Flux::modal('delete-card')->show();
    }

    public function deleteCard(DeleteCard $deleteCard): void
    {
        if ($this->deletingCardId === null) {
            return;
        }

        $card = $this->findCard($this->deletingCardId);

        $this->authorize('delete', $card);

        $deleteCard->handle($card);

        Flux::modal('delete-card')->close();
        Flux::modal('card-modal')->close();

        $this->deletingCardId = null;
        $this->selectedCardId = null;

        unset($this->selectedCard, $this->columns);

        Flux::toast(variant: 'success', text: __('cards.deleted'));
    }

    public function handleCardSort(int $cardId, int $position, int $columnId): void
    {
        try {
            app(MoveCard::class)->handle($this->findCard($cardId), $this->findColumn($columnId), $position);

            unset($this->columns);
        } catch (Throwable $exception) {
            report($exception);

            unset($this->columns);

            Flux::toast(variant: 'danger', text: __('cards.errors.move_failed'));
        }
    }

    public function toggleLabel(int $labelId, AttachLabel $attachLabel, DetachLabel $detachLabel): void
    {
        if ($this->selectedCardId === null) {
            return;
        }

        $card = $this->findCard($this->selectedCardId);
        $label = $this->board->labels()->findOrFail($labelId);

        $this->authorize('update', $card);

        if ($card->labels()->whereKey($label->id)->exists()) {
            $detachLabel->handle($card, $label);
        } else {
            $attachLabel->handle($card, $label);
        }

        unset($this->selectedCard, $this->columns);
    }

    public function createLabel(CreateLabel $createLabel): void
    {
        $this->authorize('update', $this->board);

        $this->labelForm->boardId = $this->board->id;
        $this->labelForm->validate();

        $createLabel->handle($this->board, $this->labelForm->name, $this->labelForm->color);

        $this->labelForm->reset();
        $this->labelForm->color = '#6366f1';

        unset($this->labels);

        Flux::toast(variant: 'success', text: __('cards.labels_created'));
    }

    public function addChecklistItem(AddItem $addItem): void
    {
        if ($this->selectedCardId === null) {
            return;
        }

        $card = $this->findCard($this->selectedCardId);

        $this->authorize('update', $card);

        $this->checklistForm->validate();

        $addItem->handle($card, $this->checklistForm->content);

        $this->checklistForm->reset();

        unset($this->selectedCard, $this->columns);

        Flux::toast(variant: 'success', text: __('cards.checklist_added'));
    }

    public function toggleChecklistItem(int $itemId, ToggleItem $toggleItem): void
    {
        if ($this->selectedCardId === null) {
            return;
        }

        $card = $this->findCard($this->selectedCardId);
        $item = $card->checklistItems()->findOrFail($itemId);

        $this->authorize('update', $card);

        $toggleItem->handle($item);

        unset($this->selectedCard, $this->columns);
    }

    public function removeChecklistItem(int $itemId, RemoveItem $removeItem): void
    {
        if ($this->selectedCardId === null) {
            return;
        }

        $card = $this->findCard($this->selectedCardId);
        $item = $card->checklistItems()->findOrFail($itemId);

        $this->authorize('update', $card);

        $removeItem->handle($item);

        unset($this->selectedCard, $this->columns);
    }

    private function findColumn(int $columnId): BoardColumn
    {
        return $this->board->columns()->findOrFail($columnId);
    }

    private function findCard(int $cardId): Card
    {
        return $this->board->cards()->findOrFail($cardId);
    }
}; ?>

<div>

    <div class="flex h-full w-full flex-1 flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <flux:button variant="ghost" size="sm" :href="route('boards.index')" wire:navigate>
                    {{ __('boards.back') }}
                </flux:button>

                <span class="size-3 rounded-full ring-1 ring-black/10" style="background-color: {{ $board->color ?? '#6366f1' }}"></span>

                <div>
                    <flux:heading size="lg">{{ $board->title }}</flux:heading>

                    @if ($board->description)
                        <flux:text class="mt-0.5">{{ $board->description }}</flux:text>
                    @endif
                </div>
            </div>

            <flux:button variant="ghost" x-on:click="$flux.modal('label-manager').show()">
                {{ __('cards.manage_labels') }}
            </flux:button>
        </div>

        <div wire:sort="handleColumnSort" class="flex flex-1 items-start gap-4 overflow-x-auto pb-4">
            @foreach ($this->columns as $column)
                <section
                    wire:key="column-{{ $column->id }}"
                    wire:sort:item="{{ $column->id }}"
                    class="flex max-h-full w-80 shrink-0 flex-col rounded-xl border border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900"
                >
                    <header wire:sort:handle class="flex cursor-grab items-center justify-between gap-2 border-b border-neutral-200 px-3 py-2 dark:border-neutral-700">
                        <div class="flex min-w-0 items-center gap-2">
                            <flux:heading size="sm" class="truncate">{{ $column->title }}</flux:heading>
                            <flux:badge size="sm" color="zinc">{{ $column->cards->count() }}</flux:badge>
                        </div>

                        <flux:dropdown position="bottom" align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />

                            <flux:menu>
                                <flux:menu.item wire:click="openRenameColumn({{ $column->id }})">
                                    {{ __('columns.rename') }}
                                </flux:menu.item>

                                <flux:menu.separator />

                                <flux:menu.item variant="danger" wire:click="confirmDeleteColumn({{ $column->id }})">
                                    {{ __('columns.delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </header>

                    <div
                        wire:sort="handleCardSort"
                        wire:sort:group="cards"
                        wire:sort:group-id="{{ $column->id }}"
                        wire:sort:ignore
                        class="flex min-h-24 flex-1 flex-col gap-2 overflow-y-auto p-2"
                    >
                        @foreach ($column->cards as $card)
                            <article
                                wire:key="card-{{ $card->id }}"
                                wire:sort:item="{{ $card->id }}"
                                wire:click="openCard({{ $card->id }})"
                                class="cursor-pointer rounded-lg border border-neutral-200 bg-white p-3 shadow-xs transition hover:border-neutral-300 dark:border-neutral-700 dark:bg-neutral-800 dark:hover:border-neutral-600"
                            >
                                @if ($card->labels->isNotEmpty())
                                    <div class="mb-2 flex flex-wrap gap-1">
                                        @foreach ($card->labels as $label)
                                            <span
                                                class="inline-block h-1.5 w-8 rounded-full"
                                                style="background-color: {{ $label->color }}"
                                                title="{{ $label->name }}"
                                            ></span>
                                        @endforeach
                                    </div>
                                @endif

                                <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ $card->title }}</p>

                                @if ($card->checklist_items_count > 0 || $card->attachments_count > 0)
                                    <div class="mt-2 flex items-center gap-3 text-xs text-neutral-500 dark:text-neutral-400">
                                        @if ($card->checklist_items_count > 0)
                                            <span>
                                                {{ $card->completed_checklist_items_count }}/{{ $card->checklist_items_count }}
                                            </span>
                                        @endif

                                        @if ($card->attachments_count > 0)
                                            <span>{{ $card->attachments_count }}</span>
                                        @endif
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>

                    <div class="border-t border-neutral-200 p-2 dark:border-neutral-700">
                        <form wire:submit="addCard({{ $column->id }})">
                            <flux:input
                                wire:model="newCardTitles.{{ $column->id }}"
                                size="sm"
                                :placeholder="__('cards.placeholder')"
                            />
                        </form>

                        @error("newCardTitles.$column->id")
                            <flux:error class="mt-1">{{ $message }}</flux:error>
                        @enderror
                    </div>
                </section>
            @endforeach

            <div class="w-80 shrink-0">
                <form wire:submit="createColumn" class="rounded-xl border border-dashed border-neutral-300 p-3 dark:border-neutral-700">
                    <flux:input wire:model="columnForm.title" size="sm" :placeholder="__('columns.new')" />

                    @error('columnForm.title')
                        <flux:error class="mt-1">{{ $message }}</flux:error>
                    @enderror

                    <flux:button variant="ghost" size="sm" type="submit" class="mt-2 w-full">
                        {{ __('columns.add') }}
                    </flux:button>
                </form>
            </div>
        </div>
    </div>

    {{-- Card detail --}}
    <flux:modal name="card-modal" class="w-full md:max-w-2xl">
        @if ($this->selectedCard)
            <div class="space-y-6">
                <div class="flex items-start justify-between gap-3">
                    <flux:heading size="lg">{{ __('cards.details') }}</flux:heading>

                    <div class="flex items-center gap-2">
                        <flux:button
                            variant="filled"
                            size="sm"
                            wire:click="$dispatch('droplink-request-card', { cardId: {{ $this->selectedCard->id }} })"
                        >
                            {{ __('droplinks.share') }}
                        </flux:button>

                        <flux:button
                            variant="danger"
                            size="sm"
                            wire:click="confirmDeleteCard({{ $this->selectedCard->id }})"
                        >
                            {{ __('cards.delete') }}
                        </flux:button>
                    </div>
                </div>

                <form wire:submit="saveCard" class="space-y-4">
                    <flux:input wire:model="cardForm.title" :label="__('cards.title')" required />

                    <flux:textarea wire:model="cardForm.description" :label="__('cards.description')" rows="4" />

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('cards.save') }}</flux:button>
                    </div>
                </form>

                <div>
                    <flux:heading size="sm">{{ __('cards.labels') }}</flux:heading>

                    @if ($this->labels->isEmpty())
                        <flux:text class="mt-1">{{ __('cards.no_labels') }}</flux:text>
                    @else
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($this->labels as $label)
                                @php($attached = $this->selectedCard->labels->contains('id', $label->id))

                                <button
                                    type="button"
                                    wire:key="label-toggle-{{ $label->id }}"
                                    wire:click="toggleLabel({{ $label->id }})"
                                    class="rounded-full border px-3 py-1 text-xs font-medium transition {{ $attached ? 'border-transparent text-white' : 'border-neutral-300 text-neutral-700 dark:border-neutral-600 dark:text-neutral-200' }}"
                                    style="{{ $attached ? 'background-color: '.$label->color : '' }}"
                                >
                                    {{ $label->name }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div>
                    <flux:heading size="sm">{{ __('cards.checklist') }}</flux:heading>

                    <form wire:submit="addChecklistItem" class="mt-2 flex items-end gap-2">
                        <flux:input wire:model="checklistForm.content" :placeholder="__('cards.checklist_placeholder')" class="flex-1" />
                        <flux:button variant="filled" type="submit">{{ __('cards.checklist_add') }}</flux:button>
                    </form>

                    @error('checklistForm.content')
                        <flux:error class="mt-1">{{ $message }}</flux:error>
                    @enderror

                    @if ($this->selectedCard->checklistItems->isEmpty())
                        <flux:text class="mt-2">{{ __('cards.checklist_empty') }}</flux:text>
                    @else
                        <ul class="mt-2 space-y-1">
                            @foreach ($this->selectedCard->checklistItems as $item)
                                <li wire:key="checklist-{{ $item->id }}" class="flex items-center gap-2 rounded-md px-1 py-0.5">
                                    <flux:checkbox
                                        :checked="$item->is_done"
                                        wire:click="toggleChecklistItem({{ $item->id }})"
                                        :aria-label="$item->content"
                                    />

                                    <span class="flex-1 text-sm {{ $item->is_done ? 'text-neutral-400 line-through' : 'text-neutral-800 dark:text-neutral-100' }}">
                                        {{ $item->content }}
                                    </span>

                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        wire:click="removeChecklistItem({{ $item->id }})"
                                    />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div>
                    <flux:heading size="sm">{{ __('cards.attachments') }}</flux:heading>

                    <div class="mt-3 space-y-4">
                        <livewire:vault.upload-zone
                            :card-id="$this->selectedCard->id"
                            :key="'upload-zone-'.$this->selectedCard->id"
                        />

                        <livewire:vault.attachment-list
                            :card-id="$this->selectedCard->id"
                            :key="'attachment-list-'.$this->selectedCard->id"
                        />
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>

    <livewire:links.create-modal />

    {{-- Label manager --}}
    <flux:modal name="label-manager" class="w-full md:max-w-md">
        <div class="space-y-5">
            <flux:heading size="lg">{{ __('cards.manage_labels') }}</flux:heading>

            @if ($this->labels->isEmpty())
                <flux:text>{{ __('cards.no_labels') }}</flux:text>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->labels as $label)
                        <span
                            wire:key="label-row-{{ $label->id }}"
                            class="rounded-full px-3 py-1 text-xs font-medium text-white"
                            style="background-color: {{ $label->color }}"
                        >
                            {{ $label->name }}
                        </span>
                    @endforeach
                </div>
            @endif

            <form wire:submit="createLabel" class="space-y-3 border-t border-neutral-200 pt-4 dark:border-neutral-700">
                <flux:input wire:model="labelForm.name" :label="__('cards.label_name')" />

                <div>
                    <flux:label>{{ __('cards.label_color') }}</flux:label>

                    <input
                        type="color"
                        wire:model="labelForm.color"
                        class="mt-1 h-10 w-16 cursor-pointer rounded-md border border-neutral-300 bg-transparent dark:border-neutral-700"
                    >

                    @error('labelForm.color')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>

                <div class="flex justify-end">
                    <flux:button variant="primary" type="submit">{{ __('cards.label_add') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Rename column --}}
    <flux:modal name="rename-column" class="w-full md:max-w-md">
        <form wire:submit="renameColumn" class="space-y-5">
            <flux:heading size="lg">{{ __('columns.rename') }}</flux:heading>

            <flux:input wire:model="renamingColumnTitle" :label="__('columns.title')" required />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('columns.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">{{ __('columns.save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete column --}}
    <flux:modal name="delete-column" class="w-full md:max-w-md">
        <div class="space-y-5">
            <flux:heading size="lg">{{ __('columns.delete_confirm') }}</flux:heading>
            <flux:text>{{ __('columns.delete_hint') }}</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('columns.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteColumn">{{ __('columns.delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Delete card --}}
    <flux:modal name="delete-card" class="w-full md:max-w-md">
        <div class="space-y-5">
            <flux:heading size="lg">{{ __('cards.delete_confirm') }}</flux:heading>
            <flux:text>{{ __('cards.delete_hint') }}</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('cards.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteCard">{{ __('cards.delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
