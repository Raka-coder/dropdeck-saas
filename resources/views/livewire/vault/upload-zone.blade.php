<div class="space-y-3">
    <div
        x-data="{ dragging: false, uploading: false, progress: 0 }"
        x-on:dragenter.prevent="dragging = true"
        x-on:dragover.prevent
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="
            dragging = false;
            if ($event.dataTransfer.files.length > 0) {
                $wire.uploadMultiple('files', $event.dataTransfer.files);
            }
        "
        x-on:livewire-upload-start="uploading = true"
        x-on:livewire-upload-finish="uploading = false"
        x-on:livewire-upload-error="uploading = false"
        x-on:livewire-upload-cancel="uploading = false"
        x-on:livewire-upload-progress="progress = $event.detail.progress"
        class="rounded-xl border-2 border-dashed p-6 text-center transition-colors"
        x-bind:class="dragging
            ? 'border-indigo-500 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-950/30'
            : 'border-neutral-300 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900/40'"
    >
        <svg class="mx-auto size-10 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>

        <p class="mt-2 text-sm font-medium text-neutral-700 dark:text-neutral-200">
            {{ __('attachments.drop_hint') }}
        </p>
        <p class="mt-1 text-xs text-neutral-500">
            {{ __('attachments.upload_hint', ['size' => $maxFileMb.' MB', 'count' => $remaining]) }}
        </p>

        <input
            id="attachment-files"
            type="file"
            wire:model="files"
            multiple
            class="sr-only"
        />

        <label
            for="attachment-files"
            class="mt-3 inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-neutral-900"
        >
            <flux:icon.upload class="size-4" />
            {{ __('attachments.select_files') }}
        </label>

        <p class="mt-2 text-xs text-neutral-500">{{ __('attachments.auto_upload_hint') }}</p>
    </div>

    <div x-show="uploading" x-cloak class="space-y-1">
        <div class="flex items-center justify-between text-xs text-neutral-500">
            <span>{{ __('attachments.uploading') }}</span>
            <span x-text="`${progress}%`"></span>
        </div>

        <div class="h-1.5 w-full overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
            <div
                class="h-full rounded-full bg-indigo-600 transition-[width] duration-200"
                x-bind:style="`width: ${progress}%`"
            ></div>
        </div>
    </div>

    @error('files')
        <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
    @enderror

</div>
