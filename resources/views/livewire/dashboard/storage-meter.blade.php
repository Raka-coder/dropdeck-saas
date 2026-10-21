<flux:card class="space-y-4 p-5">
    <div class="flex items-start justify-between gap-3">
        <div>
            <flux:heading size="lg">{{ __('dashboard.storage.title') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('dashboard.storage.used_of', ['used' => $this->usage['usedLabel'], 'quota' => $this->usage['quotaLabel']]) }}
            </flux:text>
        </div>

        <flux:badge
            size="sm"
            :color="match ($this->usage['level']) { 'full' => 'red', 'warning' => 'amber', default => 'green' }"
        >
            {{ __('dashboard.storage.levels.'.$this->usage['level']) }}
        </flux:badge>
    </div>

    <div
        class="h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-white/10"
        role="progressbar"
        aria-valuemin="0"
        aria-valuemax="100"
        aria-valuenow="{{ $this->usage['percent'] }}"
        aria-label="{{ __('dashboard.storage.title') }}"
    >
        <div
            class="h-full rounded-full transition-[width] duration-300 ease-out {{ match ($this->usage['level']) { 'full' => 'bg-red-600', 'warning' => 'bg-amber-500', default => 'bg-emerald-600' } }}"
            style="width: {{ $this->usage['barPercent'] }}%"
        ></div>
    </div>

    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
        <span>{{ __('dashboard.storage.remaining', ['remaining' => $this->usage['remainingLabel']]) }}</span>
        <span>{{ $this->usage['percentLabel'] }}</span>
    </div>

    @if ($this->usage['level'] === 'full')
        <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
            {{ __('dashboard.storage.full', ['percent' => config('dropdeck.dashboard.quota_critical_percent')]) }}
        </div>
    @elseif ($this->usage['level'] === 'warning')
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
            {{ __('dashboard.storage.warning', ['percent' => config('dropdeck.dashboard.quota_warning_percent')]) }}
        </div>
    @endif
</flux:card>
