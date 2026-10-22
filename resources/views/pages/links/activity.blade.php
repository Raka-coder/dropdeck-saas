<x-layouts::app :title="__('dashboard.activity.title')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('dashboard.title') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('links.index')" wire:navigate>{{ __('droplinks.title') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('dashboard.activity.title') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('dashboard.activity.title') }}</flux:heading>
                <flux:text class="mt-1">{{ __('dashboard.activity.subtitle') }}</flux:text>
            </div>

            <flux:button variant="ghost" icon="arrow-left" :href="route('links.index')" wire:navigate>
                {{ __('droplinks.title') }}
            </flux:button>
        </div>

        <livewire:dashboard.activity-log />
    </div>
</x-layouts::app>
