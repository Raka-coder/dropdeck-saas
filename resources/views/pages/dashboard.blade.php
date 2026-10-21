<x-layouts::app :title="__('dashboard.title')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('dashboard.title') }}</flux:heading>
            <flux:text class="mt-1">{{ __('dashboard.subtitle') }}</flux:text>
        </div>

        <livewire:dashboard.overview />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <livewire:dashboard.activity-log />
            </div>

            <div>
                <livewire:dashboard.storage-meter />
            </div>
        </div>
    </div>
</x-layouts::app>
