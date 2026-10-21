<flux:card class="space-y-4 p-5">
    <div class="flex flex-col gap-1">
        <flux:heading size="lg">{{ __('dashboard.activity.title') }}</flux:heading>
        <flux:text>{{ __('dashboard.activity.subtitle') }}</flux:text>
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <flux:select wire:model.live="event" :label="__('dashboard.activity.event')" class="max-w-48">
            <flux:select.option value="">{{ __('dashboard.activity.all_events') }}</flux:select.option>
            @foreach ($this->eventOptions as $value => $label)
                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input
            type="date"
            wire:model.live="date"
            :label="__('dashboard.activity.date')"
            class="max-w-48"
        />

        <flux:button type="button" size="sm" wire:click="clearFilters">
            {{ __('dashboard.activity.reset') }}
        </flux:button>
    </div>

    @if ($this->accesses->isEmpty())
        <flux:text class="py-6 text-center">{{ __('dashboard.activity.empty') }}</flux:text>
    @else
        @php
            $eventColors = [
                'view' => 'zinc',
                'password_ok' => 'green',
                'password_failed' => 'amber',
                'download' => 'blue',
                'blocked' => 'red',
            ];
        @endphp

        <flux:table :paginate="$this->accesses">
            <flux:table.columns>
                <flux:table.column>{{ __('dashboard.activity.time') }}</flux:table.column>
                <flux:table.column>{{ __('dashboard.activity.event') }}</flux:table.column>
                <flux:table.column>{{ __('dashboard.activity.link') }}</flux:table.column>
                <flux:table.column>{{ __('dashboard.activity.ip') }}</flux:table.column>
                <flux:table.column>{{ __('dashboard.activity.user_agent') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->accesses as $access)
                    <flux:table.row :key="$access->id">
                        <flux:table.cell variant="strong">
                            {{ $access->created_at?->translatedFormat('d M Y H:i') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$eventColors[$access->event->value]">
                                {{ __('dashboard.events.'.$access->event->value) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $access->droplink->label ?: \Illuminate\Support\Str::limit($access->droplink->token, 12) }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $access->ip_address ?? '—' }}</flux:table.cell>
                        <flux:table.cell
                            class="max-w-56 truncate"
                            :title="$access->user_agent"
                        >
                            {{ $access->user_agent ? \Illuminate\Support\Str::limit($access->user_agent, 40) : '—' }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</flux:card>
