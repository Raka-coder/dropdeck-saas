<div>
    <flux:modal name="droplink-create" class="w-full md:max-w-md">
        @if ($createdUrl !== null)
            <div class="space-y-5">
                <flux:heading size="lg">{{ __('droplinks.created_title') }}</flux:heading>

                <flux:text>{{ __('droplinks.created_hint') }}</flux:text>

                <div class="flex items-start gap-2">
                    <flux:input readonly :value="$createdUrl" class="flex-1" />

                    <flux:button
                        variant="filled"
                        type="button"
                        x-on:click="navigator.clipboard.writeText(@js($createdUrl)); $flux.toast(@js(__('droplinks.copied')))"
                    >
                        {{ __('droplinks.copy') }}
                    </flux:button>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button variant="primary" type="button" wire:click="close">
                        {{ __('droplinks.close') }}
                    </flux:button>
                </div>
            </div>
        @else
            <form wire:submit="save" class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('droplinks.new') }}</flux:heading>
                    <flux:text class="mt-1">{{ $targetName }}</flux:text>
                </div>

                <flux:select wire:model="expiresInHours" :label="__('droplinks.expiry')">
                    <option value="1">{{ __('droplinks.expiry_hours', ['hours' => 1]) }}</option>
                    <option value="6">{{ __('droplinks.expiry_hours', ['hours' => 6]) }}</option>
                    <option value="24">{{ __('droplinks.expiry_hours', ['hours' => 24]) }}</option>
                    <option value="72">{{ __('droplinks.expiry_days', ['days' => 3]) }}</option>
                    <option value="168">{{ __('droplinks.expiry_days', ['days' => 7]) }}</option>
                    <option value="720">{{ __('droplinks.expiry_days', ['days' => 30]) }}</option>
                </flux:select>

                <flux:input
                    wire:model="maxDownloads"
                    type="number"
                    min="1"
                    max="{{ config('dropdeck.limits.link_max_downloads') }}"
                    :label="__('droplinks.max_downloads')"
                    :description="__('droplinks.max_downloads_hint', ['max' => config('dropdeck.limits.link_max_downloads')])"
                />

                <flux:input
                    wire:model="password"
                    type="password"
                    :label="__('droplinks.password')"
                    :description="__('droplinks.password_hint')"
                />

                <flux:input
                    wire:model="label"
                    :label="__('droplinks.label')"
                    :placeholder="__('droplinks.label_placeholder')"
                />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled" type="button">{{ __('droplinks.cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="primary" type="submit">{{ __('droplinks.save') }}</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
