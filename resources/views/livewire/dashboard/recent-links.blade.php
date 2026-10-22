<flux:card class="space-y-4 p-5">
    <div class="flex items-start justify-between gap-3">
        <flux:heading size="lg">{{ __('dashboard.links.title') }}</flux:heading>

        <flux:button size="sm" variant="ghost" icon="external-link" :href="route('links.index')" wire:navigate>
            {{ __('dashboard.links.manage') }}
        </flux:button>
    </div>

    @if ($this->links->isEmpty())
        <flux:text class="py-4 text-center">{{ __('dashboard.links.empty') }}</flux:text>
    @else
        @php
            $statusColors = [
                'active' => 'green',
                'expired' => 'zinc',
                'exhausted' => 'amber',
                'revoked' => 'red',
            ];
        @endphp

        <ul class="divide-y divide-zinc-100 dark:divide-white/10">
            @foreach ($this->links as $link)
                @php($status = $link->status())
                <li class="flex items-center justify-between gap-3 py-3" wire:key="recent-link-{{ $link->id }}">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-medium">
                            {{ $link->label ?: ($link->attachment?->original_name ?? $link->card?->title ?? __('dashboard.links.untitled')) }}
                        </div>
                        <flux:text class="mt-0.5 text-xs">
                            {{ __('dashboard.links.expires', ['date' => $link->expires_at->translatedFormat('d M Y H:i')]) }}
                        </flux:text>
                    </div>

                    <flux:badge size="sm" :color="$statusColors[$status->value]">
                        {{ __('droplinks.statuses.'.$status->value) }}
                    </flux:badge>
                </li>
            @endforeach
        </ul>
    @endif
</flux:card>
