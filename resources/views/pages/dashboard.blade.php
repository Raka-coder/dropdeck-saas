<x-layouts::app :title="__('dashboard.title')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('dashboard.title') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('dashboard.greeting', ['name' => auth()->user()->name]) }}
                </flux:text>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button :href="route('links.index')" wire:navigate>
                    {{ __('droplinks.title') }}
                </flux:button>

                <flux:button
                    variant="primary"
                    icon="plus"
                    :href="route('boards.index', ['create' => 1])"
                    wire:navigate
                >
                    {{ __('boards.new') }}
                </flux:button>
            </div>
        </div>

        <livewire:dashboard.overview />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <livewire:dashboard.activity-log />
            </div>

            <div class="space-y-6">
                <livewire:dashboard.storage-meter />
                <livewire:dashboard.recent-links />
            </div>
        </div>
    </div>
</x-layouts::app>
