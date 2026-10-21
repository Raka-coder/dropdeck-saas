<div class="space-y-3">
    <div
        x-data="{ uploading: false, progress: 0 }"
        x-on:livewire-upload-start="uploading = true"
        x-on:livewire-upload-finish="uploading = false"
        x-on:livewire-upload-error="uploading = false"
        x-on:livewire-upload-cancel="uploading = false"
        x-on:livewire-upload-progress="progress = $event.detail.progress"
        class="space-y-3"
    >
        <input
            type="file"
            wire:model="files"
            multiple
            class="block w-full cursor-pointer rounded-lg border border-neutral-300 bg-white p-2 text-sm text-neutral-700 file:mr-3 file:rounded-md file:border-0 file:bg-neutral-100 file:px-3 file:py-1.5 file:text-sm file:font-medium dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:file:bg-neutral-800"
        />

        <div x-show="uploading" x-cloak class="space-y-1">
            <flux:text class="text-xs">{{ __('attachments.uploading') }}</flux:text>
            <progress max="100" x-bind:value="progress" class="h-1.5 w-full"></progress>
        </div>

        @error('files')
            <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
        @enderror

        @if ($files !== [])
            <ul class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @foreach ($files as $index => $file)
                    <li wire:key="pending-file-{{ $index }}" class="flex items-center justify-between gap-3 py-2 text-sm">
                        <span class="truncate">{{ $file->getClientOriginalName() }}</span>

                        <flux:button type="button" size="sm" variant="ghost" wire:click="remove({{ $index }})">
                            {{ __('attachments.delete') }}
                        </flux:button>
                    </li>
                @endforeach
            </ul>

            <flux:button type="button" variant="primary" size="sm" wire:click="upload" wire:loading.attr="disabled">
                {{ __('attachments.upload_action') }}
            </flux:button>
        @endif

        <flux:text class="text-xs text-neutral-500">
            {{ __('attachments.upload_hint', ['size' => $maxFileMb.' MB', 'count' => $remaining]) }}
        </flux:text>
    </div>
</div>
