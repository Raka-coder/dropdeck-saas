<div class="space-y-3">
    @if ($attachments->isEmpty())
        <flux:text class="text-sm text-neutral-500">{{ __('attachments.no_files') }}</flux:text>
    @else
        <ul class="divide-y divide-neutral-200 dark:divide-neutral-700">
            @foreach ($attachments as $attachment)
                <li wire:key="attachment-{{ $attachment->id }}" class="flex items-center gap-3 py-2">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-md bg-neutral-100 dark:bg-neutral-800">
                        @if ($attachment->thumbnail_path !== null)
                            <img
                                src="{{ route('attachments.thumbnail', $attachment) }}"
                                alt=""
                                class="h-full w-full object-cover"
                                loading="lazy"
                            />
                        @else
                            <span class="text-[10px] font-semibold uppercase text-neutral-500">
                                {{ $attachment->extension ?? 'file' }}
                            </span>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-neutral-800 dark:text-neutral-100">
                            {{ $attachment->original_name }}
                        </p>
                        <p class="text-xs text-neutral-500">
                            {{ \App\Support\Bytes::human($attachment->size_bytes) }}
                        </p>
                    </div>

                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="download"
                        :href="route('attachments.download', $attachment)"
                    >
                        {{ __('attachments.download') }}
                    </flux:button>

                    <flux:button
                        type="button"
                        size="sm"
                        variant="filled"
                        icon="share-2"
                        wire:click="share({{ $attachment->id }})"
                    >
                        {{ __('droplinks.share') }}
                    </flux:button>

                    @if ($deletingId === $attachment->id)
                        <flux:button type="button" size="sm" variant="danger" icon="trash-2" wire:click="delete">
                            {{ __('attachments.confirm_delete') }}
                        </flux:button>

                        <flux:button type="button" size="sm" variant="ghost" icon="x" wire:click="cancelDelete">
                            {{ __('attachments.cancel') }}
                        </flux:button>
                    @else
                        <flux:button
                            type="button"
                            size="sm"
                            variant="ghost"
                            icon="trash-2"
                            wire:click="confirmDelete({{ $attachment->id }})"
                        >
                            {{ __('attachments.delete') }}
                        </flux:button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
