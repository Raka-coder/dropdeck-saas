<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @php
        $stats = [
            ['label' => __('dashboard.stats.boards'), 'value' => $this->stats['boards'], 'icon' => 'rectangle-group'],
            ['label' => __('dashboard.stats.cards'), 'value' => $this->stats['cards'], 'icon' => 'document-text'],
            ['label' => __('dashboard.stats.files'), 'value' => $this->stats['files'], 'icon' => 'paper-clip'],
            ['label' => __('dashboard.stats.active_links'), 'value' => $this->stats['activeLinks'], 'icon' => 'link'],
        ];
    @endphp

    @foreach ($stats as $stat)
        <flux:card class="flex items-center gap-4 p-5" wire:key="dashboard-stat-{{ $stat['icon'] }}">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-white/10">
                <flux:icon :name="$stat['icon']" class="size-5 text-zinc-500 dark:text-zinc-300" />
            </div>
            <div class="min-w-0">
                <flux:text class="truncate">{{ $stat['label'] }}</flux:text>
                <flux:heading size="lg">{{ number_format($stat['value'], 0, ',', '.') }}</flux:heading>
            </div>
        </flux:card>
    @endforeach
</div>
