<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="referrer" content="no-referrer">

        <title>{{ $link->label ?? __('droplinks.public.title') }} — {{ config('app.name') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <meta name="theme-color" content="#4F46E5">

        @fonts
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-neutral-100 text-neutral-900 antialiased">
        <main class="flex min-h-screen items-center justify-center p-4">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-sm ring-1 ring-neutral-200 sm:p-8">
                <div class="flex items-center gap-2.5">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-neutral-900 text-white">
                        <x-app-logo-icon class="size-5" />
                    </span>
                    <span class="text-sm font-semibold tracking-tight text-neutral-900">{{ config('app.name') }}</span>
                </div>

                @if ($state === 'revoked')
                    <h1 class="mt-4 text-xl font-semibold">{{ __('droplinks.public.revoked_title') }}</h1>
                    <p class="mt-2 text-sm text-neutral-600">{{ __('droplinks.public.revoked_hint') }}</p>
                @elseif ($state === 'expired')
                    <h1 class="mt-4 text-xl font-semibold">{{ __('droplinks.public.expired_title') }}</h1>
                    <p class="mt-2 text-sm text-neutral-600">{{ __('droplinks.public.expired_hint') }}</p>
                @elseif ($state === 'exhausted')
                    <h1 class="mt-4 text-xl font-semibold">{{ __('droplinks.public.exhausted_title') }}</h1>
                    <p class="mt-2 text-sm text-neutral-600">{{ __('droplinks.public.exhausted_hint') }}</p>
                @elseif ($state === 'password')
                    <h1 class="mt-4 text-xl font-semibold">{{ __('droplinks.public.password_title') }}</h1>
                    <p class="mt-2 text-sm text-neutral-600">{{ __('droplinks.public.password_hint') }}</p>

                    <form method="POST" action="{{ route('droplink.unlock', $link->token) }}" class="mt-6 space-y-3">
                        @csrf

                        <div>
                            <label for="password" class="block text-sm font-medium">
                                {{ __('droplinks.password') }}
                            </label>

                            <input
                                id="password"
                                name="password"
                                type="password"
                                required
                                autofocus
                                autocomplete="current-password"
                                class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm shadow-sm focus:border-neutral-500 focus:outline-none"
                            >

                            @error('password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800"
                        >
                            {{ __('droplinks.public.password_action') }}
                        </button>
                    </form>
                @else
                    @if ($link->label)
                        <h1 class="mt-4 text-xl font-semibold">{{ $link->label }}</h1>
                    @endif

                    @if ($file !== null)
                        <div class="mt-4 rounded-lg border border-neutral-200 p-4">
                            <p class="text-xs uppercase tracking-wide text-neutral-500">{{ __('droplinks.public.file') }}</p>
                            <p class="mt-1 truncate font-medium">{{ $file->original_name }}</p>
                            <p class="text-sm text-neutral-500">{{ \App\Support\Bytes::human($file->size_bytes) }}</p>
                        </div>

                        <form method="POST" action="{{ route('droplink.download', $link->token) }}" class="mt-4">
                            @csrf

                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800"
                            >
                                {{ __('droplinks.public.download') }}
                            </button>
                        </form>
                    @else
                        <p class="mt-4 text-sm font-medium">{{ __('droplinks.public.files') }}</p>

                        <ul class="mt-2 divide-y divide-neutral-200 rounded-lg border border-neutral-200">
                            @foreach ($files as $item)
                                <li class="flex items-center gap-3 p-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">{{ $item->original_name }}</p>
                                        <p class="text-xs text-neutral-500">{{ \App\Support\Bytes::human($item->size_bytes) }}</p>
                                    </div>

                                    <form method="POST" action="{{ route('droplink.download.attachment', [$link->token, $item->id]) }}">
                                        @csrf

                                        <button
                                            type="submit"
                                            class="rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-800"
                                        >
                                            {{ __('droplinks.public.download') }}
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-6 space-y-1 text-xs text-neutral-500">
                        <p>{{ __('droplinks.public.expires_at', ['date' => $link->expires_at->translatedFormat('d M Y H:i')]) }}</p>

                        @if ($remaining === null)
                            <p>{{ __('droplinks.public.unlimited') }}</p>
                        @else
                            <p>{{ __('droplinks.public.remaining', ['count' => $remaining]) }}</p>
                        @endif

                        <p>{{ __('droplinks.public.download_hint') }}</p>
                    </div>
                @endif

                <p class="mt-8 text-xs text-neutral-400">
                    {{ __('droplinks.public.footer', ['app' => config('app.name')]) }}
                </p>
            </div>
        </main>
    </body>
</html>
