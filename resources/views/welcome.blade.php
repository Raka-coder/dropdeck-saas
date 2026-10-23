<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('partials.head', [
            'title' => null,
            'description' => config('seo.description'),
            'robots' => 'index, follow',
            'canonical' => route('home'),
        ])
    </head>
    <body class="min-h-screen bg-neutral-950 text-neutral-100 antialiased selection:bg-violet-500/30 selection:text-white">
        <header class="sticky top-0 z-50 border-b border-white/10 bg-neutral-950/80 backdrop-blur-md">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5" aria-label="{{ config('app.name', 'DropDeck') }} — beranda">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-violet-600 text-white shadow-lg shadow-violet-600/20">
                        <x-app-logo-icon class="size-5" />
                    </span>
                    <span class="text-lg font-semibold tracking-tight">{{ config('app.name', 'DropDeck') }}</span>
                </a>

                <nav class="hidden items-center gap-8 text-sm text-neutral-400 md:flex" aria-label="Navigasi utama">
                    <a href="#fitur" class="transition hover:text-white">Fitur</a>
                    <a href="#cara-kerja" class="transition hover:text-white">Cara kerja</a>
                </nav>

                <div class="flex items-center gap-2 sm:gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-950 transition hover:bg-neutral-200">
                            Buka dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-neutral-300 transition hover:text-white">
                            Masuk
                        </a>
                        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-950 transition hover:bg-neutral-200">
                            Mulai gratis
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <main>
            <section class="relative overflow-hidden">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(124,58,237,0.22),transparent_55%),radial-gradient(ellipse_at_bottom_right,rgba(79,70,229,0.12),transparent_50%)]"></div>

                <div class="relative mx-auto grid max-w-6xl items-center gap-14 px-6 py-20 lg:grid-cols-2 lg:py-28">
                    <div>
                        <span class="inline-flex items-center gap-2 rounded-full border border-violet-400/20 bg-violet-400/10 px-3 py-1 text-xs font-medium text-violet-300">
                            <span class="size-1.5 rounded-full bg-violet-400"></span>
                            Kanban + Vault dalam satu tempat
                        </span>

                        <h1 class="mt-6 text-4xl font-semibold leading-[1.05] tracking-tight sm:text-5xl lg:text-6xl">
                            Atur pekerjaan.<br>
                            <span class="text-violet-400">Bagikan berkas.</span><br>
                            Tanpa berantakan.
                        </h1>

                        <p class="mt-6 max-w-lg text-base leading-relaxed text-neutral-400 sm:text-lg">
                            DropDeck menggabungkan papan kanban, checklist, label, dan berbagi berkas lewat DropLink yang aman — semuanya dalam satu ruang kerja yang rapi.
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <a href="{{ auth()->check() ? route('dashboard') : route('register') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-violet-600/25 transition hover:bg-violet-500">
                                {{ auth()->check() ? 'Buka dashboard' : 'Mulai gratis' }}
                                <flux:icon.arrow-right class="size-4" />
                            </a>
                            <a href="#fitur" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-neutral-200 transition hover:border-white/30 hover:bg-white/5">
                                Lihat fitur
                            </a>
                        </div>

                        <dl class="mt-10 grid max-w-lg grid-cols-3 gap-6 border-t border-white/10 pt-6">
                            <div>
                                <dt class="text-xs uppercase tracking-wider text-neutral-500">Papan</dt>
                                <dd class="mt-1 text-2xl font-semibold">Tak terbatas</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-wider text-neutral-500">Berkas</dt>
                                <dd class="mt-1 text-2xl font-semibold">Aman</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-wider text-neutral-500">DropLink</dt>
                                <dd class="mt-1 text-2xl font-semibold">Terkontrol</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="relative">
                        <div class="absolute -inset-6 rounded-[2rem] bg-violet-500/10 blur-3xl"></div>

                        <div class="relative rounded-2xl border border-white/10 bg-white/[0.03] p-4 shadow-2xl shadow-black/40 backdrop-blur">
                            <div class="mb-4 flex items-center justify-between">
                                <div class="flex items-center gap-1.5" aria-hidden="true">
                                    <span class="size-2 rounded-full bg-red-400/70"></span>
                                    <span class="size-2 rounded-full bg-amber-400/70"></span>
                                    <span class="size-2 rounded-full bg-emerald-400/70"></span>
                                </div>
                                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-medium text-neutral-400">
                                    Alur Konten
                                </span>
                            </div>

                            <div class="grid grid-cols-3 gap-3">
                                <div class="rounded-xl bg-neutral-900/80 p-3 ring-1 ring-white/5">
                                    <div class="mb-3 flex items-center justify-between">
                                        <span class="text-xs font-medium text-neutral-300">Ide</span>
                                        <span class="text-[10px] text-neutral-500">2</span>
                                    </div>
                                    <div class="space-y-2">
                                        <div class="rounded-lg border border-white/5 bg-neutral-800/80 p-3">
                                            <div class="mb-2 flex gap-1">
                                                <span class="h-1.5 w-6 rounded-full bg-violet-400"></span>
                                                <span class="h-1.5 w-4 rounded-full bg-sky-400"></span>
                                            </div>
                                            <p class="text-[11px] leading-snug text-neutral-300">Riset referensi desain</p>
                                        </div>
                                        <div class="rounded-lg border border-white/5 bg-neutral-800/80 p-3">
                                            <div class="mb-2 flex gap-1">
                                                <span class="h-1.5 w-5 rounded-full bg-amber-400"></span>
                                            </div>
                                            <p class="text-[11px] leading-snug text-neutral-300">Tulis outline artikel</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-xl bg-neutral-900/80 p-3 ring-1 ring-white/5">
                                    <div class="mb-3 flex items-center justify-between">
                                        <span class="text-xs font-medium text-neutral-300">Dikerjakan</span>
                                        <span class="text-[10px] text-neutral-500">1</span>
                                    </div>
                                    <div class="space-y-2">
                                        <div class="rounded-lg border border-violet-400/20 bg-violet-500/10 p-3 ring-1 ring-violet-400/10">
                                            <div class="mb-2 flex gap-1">
                                                <span class="h-1.5 w-7 rounded-full bg-emerald-400"></span>
                                                <span class="h-1.5 w-3 rounded-full bg-rose-400"></span>
                                            </div>
                                            <p class="text-[11px] leading-snug text-neutral-200">Desain halaman utama</p>
                                            <div class="mt-2 flex items-center gap-1.5 text-[10px] text-neutral-500">
                                                <flux:icon.paperclip class="size-3" />
                                                3 berkas
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-xl bg-neutral-900/80 p-3 ring-1 ring-white/5">
                                    <div class="mb-3 flex items-center justify-between">
                                        <span class="text-xs font-medium text-neutral-300">Selesai</span>
                                        <span class="text-[10px] text-neutral-500">1</span>
                                    </div>
                                    <div class="space-y-2">
                                        <div class="rounded-lg border border-white/5 bg-neutral-800/80 p-3">
                                            <div class="mb-2 flex gap-1">
                                                <span class="h-1.5 w-4 rounded-full bg-emerald-400"></span>
                                            </div>
                                            <p class="text-[11px] leading-snug text-neutral-300">Publikasi konten</p>
                                            <div class="mt-2 flex items-center gap-1.5 text-[10px] text-emerald-400/80">
                                                <flux:icon.check class="size-3" />
                                                Selesai
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="fitur" class="border-t border-white/10 bg-neutral-900/30">
                <div class="mx-auto max-w-6xl px-6 py-20 lg:py-24">
                    <div class="max-w-2xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-400">Fitur</p>
                        <h2 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Semua yang dibutuhkan untuk merapikan pekerjaan.</h2>
                        <p class="mt-4 text-neutral-400">Dibuat untuk tim kecil dan individu yang ingin fokus pada hasil, bukan pada mengelola alat.</p>
                    </div>

                    <div class="mt-12 grid gap-6 md:grid-cols-3">
                        <article class="rounded-2xl border border-white/10 bg-white/[0.02] p-6">
                            <span class="flex size-11 items-center justify-center rounded-xl bg-violet-500/10 text-violet-400 ring-1 ring-violet-400/20">
                                <flux:icon.square-kanban class="size-5" />
                            </span>
                            <h3 class="mt-5 text-lg font-semibold">Kanban yang fleksibel</h3>
                            <p class="mt-2 text-sm leading-relaxed text-neutral-400">Susun kolom dan kartu, pindahkan dengan drag-and-drop, lalu tambahkan label serta checklist agar prioritas selalu terlihat.</p>
                        </article>

                        <article class="rounded-2xl border border-white/10 bg-white/[0.02] p-6">
                            <span class="flex size-11 items-center justify-center rounded-xl bg-sky-500/10 text-sky-400 ring-1 ring-sky-400/20">
                                <flux:icon.paperclip class="size-5" />
                            </span>
                            <h3 class="mt-5 text-lg font-semibold">Vault berkas terpusat</h3>
                            <p class="mt-2 text-sm leading-relaxed text-neutral-400">Unggah lampiran langsung ke kartu, pantau kuota penyimpanan, dan kelola semua berkas tanpa berpindah aplikasi.</p>
                        </article>

                        <article class="rounded-2xl border border-white/10 bg-white/[0.02] p-6">
                            <span class="flex size-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-400 ring-1 ring-emerald-400/20">
                                <flux:icon.link class="size-5" />
                            </span>
                            <h3 class="mt-5 text-lg font-semibold">DropLink terkontrol</h3>
                            <p class="mt-2 text-sm leading-relaxed text-neutral-400">Bagikan berkas atau kartu lewat tautan dengan kata sandi, batas unduhan, dan masa berlaku yang bisa kamu atur.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="cara-kerja" class="border-t border-white/10">
                <div class="mx-auto grid max-w-6xl gap-12 px-6 py-20 lg:grid-cols-2 lg:items-center lg:py-24">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-400">Cara kerja</p>
                        <h2 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Dari ide menjadi tautan dalam hitungan menit.</h2>
                        <p class="mt-4 text-neutral-400">Buat papan, isi kartu dengan detail pekerjaan, lampirkan berkas, lalu bagikan lewat DropLink. Tidak perlu instalasi atau konfigurasi rumit.</p>
                    </div>

                    <ol class="space-y-4">
                        <li class="flex gap-4 rounded-xl border border-white/10 bg-white/[0.02] p-4">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-violet-600 text-sm font-semibold text-white">1</span>
                            <div>
                                <h3 class="font-medium">Buat papan dan kolom</h3>
                                <p class="mt-1 text-sm text-neutral-400">Mulai dari struktur kerja yang paling masuk akal untuk timmu.</p>
                            </div>
                        </li>
                        <li class="flex gap-4 rounded-xl border border-white/10 bg-white/[0.02] p-4">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-violet-600 text-sm font-semibold text-white">2</span>
                            <div>
                                <h3 class="font-medium">Isi kartu dan lampirkan berkas</h3>
                                <p class="mt-1 text-sm text-neutral-400">Tambahkan deskripsi, checklist, label, dan lampiran dalam satu tempat.</p>
                            </div>
                        </li>
                        <li class="flex gap-4 rounded-xl border border-white/10 bg-white/[0.02] p-4">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-violet-600 text-sm font-semibold text-white">3</span>
                            <div>
                                <h3 class="font-medium">Bagikan dengan aman</h3>
                                <p class="mt-1 text-sm text-neutral-400">Buat DropLink dengan kata sandi dan batas unduhan sesuai kebutuhan.</p>
                            </div>
                        </li>
                    </ol>
                </div>
            </section>

            <section class="border-t border-white/10 bg-violet-600/10">
                <div class="mx-auto max-w-4xl px-6 py-20 text-center lg:py-24">
                    <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">Siap merapikan alur kerja?</h2>
                    <p class="mx-auto mt-4 max-w-xl text-neutral-400">Mulai gratis, atur papan pertamamu, dan bagikan berkas tanpa ribet.</p>
                    <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        <a href="{{ auth()->check() ? route('dashboard') : route('register') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-semibold text-neutral-950 transition hover:bg-neutral-200">
                            {{ auth()->check() ? 'Buka dashboard' : 'Buat akun gratis' }}
                            <flux:icon.arrow-right class="size-4" />
                        </a>
                        @guest
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-neutral-200 transition hover:border-white/30 hover:bg-white/5">
                                Sudah punya akun? Masuk
                            </a>
                        @endguest
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-white/10">
            <div class="mx-auto flex max-w-6xl flex-col gap-4 px-6 py-8 text-sm text-neutral-500 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="flex size-7 items-center justify-center rounded-lg bg-violet-600 text-white">
                        <x-app-logo-icon class="size-4" />
                    </span>
                    <span>{{ config('app.name', 'DropDeck') }} &copy; {{ date('Y') }}</span>
                </div>

                <nav class="flex flex-wrap items-center gap-5" aria-label="Navigasi footer">
                    <a href="{{ route('login') }}" class="transition hover:text-neutral-300">Masuk</a>
                    <a href="{{ route('register') }}" class="transition hover:text-neutral-300">Daftar</a>
                    <a href="/sitemap.xml" class="transition hover:text-neutral-300">Sitemap</a>
                </nav>
            </div>
        </footer>

        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'WebApplication',
                'name' => config('app.name', 'DropDeck'),
                'url' => route('home'),
                'description' => config('seo.description'),
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web',
                'inLanguage' => 'id-ID',
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0',
                    'priceCurrency' => 'IDR',
                ],
                'featureList' => [
                    'Papan kanban dengan drag-and-drop',
                    'Label dan checklist pada kartu',
                    'Penyimpanan berkas terpusat',
                    'DropLink dengan kata sandi dan batas unduhan',
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
        </script>

        @fluxScripts
    </body>
</html>
