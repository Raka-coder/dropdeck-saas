<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @php
        $stats = [
            ['label' => __('dashboard.stats.boards'), 'value' => $this->stats['boards'], 'icon' => 'rectangle-group', 'href' => route('boards.index')],
            ['label' => __('dashboard.stats.cards'), 'value' => $this->stats['cards'], 'icon' => 'document-text', 'href' => route('boards.index')],
            ['label' => __('dashboard.stats.files'), 'value' => $this->stats['files'], 'icon' => 'paper-clip', 'href' => route('links.index')],
            ['label' => __('dashboard.stats.active_links'), 'value' => $this->stats['activeLinks'], 'icon' => 'link', 'href' => route('links.index')],
        ];
    @endphp

    @foreach ($stats as $stat)
        <a
            href="{{ $stat['href'] }}"
            wire:navigate
            wire:key="dashboard-stat-{{ $stat['icon'] }}"
            class="block rounded-xl transition hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
        >
            <flux:card class="flex h-full items-center gap-4 p-5">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-white/10">
                    <flux:icon :name="$stat['icon']" class="size-5 text-zinc-500 dark:text-zinc-300" />
                </div>
                <div class="min-w-0">
                    <flux:text class="truncate">{{ $stat['label'] }}</flux:text>
                    <flux:heading size="lg">{{ number_format($stat['value'], 0, ',', '.') }}</flux:heading>
                </div>
            </flux:card>
        </a>
    @endforeach
</div>
