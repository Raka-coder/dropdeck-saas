> **Catatan (22 Okt 2026):** dokumen ini adalah diagnosis lama. Dashboard sekarang memakai query live
> (`app/Livewire/Dashboard/*`), bukan data hardcoded. Jika dashboard kosong, penyebabnya data belum di-seed —
> jalankan `podman compose exec app php artisan db:seed --class=DemoSeeder`. Lihat `README.md` untuk build ulang.

Kemungkinan dashboard tidak update bisa dari beberapa hal. Saya kasih **diagnostic checklist** dan cara perbaikinya.

## 🔍 Diagnostic Checklist

Cek satu-satu:

### 1. **Database ada data?**
```bash
podman compose exec app php artisan tinker

# Cek apakah ada boards/cards/attachments/droplinks
>>> auth()->user()->boards->count()     // harus > 0
>>> auth()->user()->cards()->count()     // harus > 0
>>> auth()->user()->attachments->count() // harus > 0
>>> auth()->user()->droplinks->count()   // harus > 0

# Jika semua 0, problem adalah data belum ada, bukan dashboard
```

### 2. **Livewire component loading?**
Buka browser DevTools (F12) → Network tab → cek:
- Halaman `/dashboard` loaded? ✅
- Ada request Livewire? (biasanya `api/livewire/call` atau `livewire/message`)
- Response ada error? (cek tab Console)

### 3. **Cache Laravel?**
Dashboard mungkin di-cache. Hapus:
```bash
podman compose exec app php artisan cache:clear
podman compose exec app php artisan config:clear
podman compose exec app php artisan view:clear
```

### 4. **Livewire component benar-benar di-update?**
Cek file `app/Livewire/Dashboard/Overview.php` (atau nama componennya):

```php
// ❌ SALAH — data hardcoded
class Overview extends Component
{
    public function render()
    {
        return view('livewire.dashboard.overview', [
            'boards' => 5,  // ← hardcoded, tidak dinamis
            'cards' => 42,
        ]);
    }
}

// ✅ BENAR — data live dari DB
class Overview extends Component
{
    public function render()
    {
        return view('livewire.dashboard.overview', [
            'boards' => auth()->user()->boards()->count(),
            'cards' => auth()->user()->cards()->count(),
            'used_storage' => auth()->user()->storage_used_bytes,
            'total_quota' => auth()->user()->storage_quota_bytes,
            'activities' => auth()->user()->droplinks()
                ->with('attachment:id,original_name')
                ->with('card:id,title')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ]);
    }
}
```

---

## 🛠️ Perbaikan Cepat

Jika dashboard masih kosong/hardcoded, lakukan ini:

### Step 1: Update `app/Livewire/Dashboard/Overview.php`

```php
<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use Illuminate\Support\Facades\DB;

class Overview extends Component
{
    public function render()
    {
        $user = auth()->user();
        
        return view('livewire.dashboard.overview', [
            'boards_count' => $user->boards()->count(),
            'cards_count' => $user->cards()->count(),
            'attachments_count' => $user->attachments()->count(),
            'links_active' => $user->droplinks()
                ->where('revoked_at', null)
                ->whereDate('expires_at', '>', now())
                ->count(),
            'storage_used' => $user->storage_used_bytes,
            'storage_quota' => $user->storage_quota_bytes,
            'storage_percent' => intval(($user->storage_used_bytes / $user->storage_quota_bytes) * 100),
            'storage_warning' => ($user->storage_used_bytes / $user->storage_quota_bytes) >= 0.8,
            'activities' => $user->droplinks()
                ->with(['attachment:id,original_name', 'card:id,title'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ]);
    }
}
```

### Step 2: Update View `resources/views/livewire/dashboard/overview.blade.php`

Ganti bagian metric cards:
```blade
<div class="grid-4">
    <div class="card metric">
        <div class="metric-label">Board</div>
        <div class="metric-value">{{ $boards_count }}</div>
        <div class="metric-subtext">aktif</div>
    </div>
    <div class="card metric">
        <div class="metric-label">Kartu</div>
        <div class="metric-value">{{ $cards_count }}</div>
        <div class="metric-subtext">total</div>
    </div>
    <div class="card metric">
        <div class="metric-label">File</div>
        <div class="metric-value">{{ $attachments_count }}</div>
        <div class="metric-subtext">tersimpan</div>
    </div>
    <div class="card metric">
        <div class="metric-label">DropLink</div>
        <div class="metric-value">{{ $links_active }}</div>
        <div class="metric-subtext">aktif</div>
    </div>
</div>
```

Ganti storage meter:
```blade
<div class="storage-meter-label">
    <span>Pemakaian</span>
    <span>{{ number_format($storage_used / 1024 / 1024, 1) }} GB / {{ number_format($storage_quota / 1024 / 1024, 1) }} GB</span>
</div>
<div class="storage-meter-bar">
    <div class="storage-meter-fill @if($storage_warning) warning @endif" style="width: {{ $storage_percent }}%;"></div>
</div>
```

Ganti activity log:
```blade
@if($activities->count() > 0)
    <div class="activity-list">
        @foreach($activities as $activity)
            <div class="activity-item">
                <div class="activity-item-left">
                    @if($activity->target_type === 'file')
                        <div class="activity-item-title">
                            Akses <strong>{{ $activity->attachment?->original_name }}</strong>
                        </div>
                    @else
                        <div class="activity-item-title">
                            Akses <strong>{{ $activity->card?->title }}</strong>
                        </div>
                    @endif
                    <div class="activity-item-meta">
                        {{ $activity->created_at->diffForHumans() }}
                        • {{ $activity->download_count }} unduhan
                    </div>
                </div>
                <div class="activity-item-status @if($activity->revoked_at) status-blocked @elseif($activity->expires_at < now()) status-blocked @else status-success @endif">
                    @if($activity->revoked_at)
                        Dicabut
                    @elseif($activity->expires_at < now())
                        Kedaluwarsa
                    @else
                        Aktif
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="empty">
        <p>Belum ada aktivitas.</p>
    </div>
@endif
```

### Step 3: Jalankan ulang
```bash
podman compose down -v    # reset semuanya (termasuk database)
podman compose up -d --build

podman compose exec app php artisan migrate
podman compose exec app php artisan tinker
>>> User::factory()->create();  # buat 1 user demo
>>> Board::factory()->for(User::first())->create();  # buat board
```

Buka browser ke `http://localhost:8000/dashboard` → harus ada data sekarang.

---

## 🔧 Jika Masih Tidak Muncul

Cek **3 hal terakhir**:

| Pertanyaan | Cek caranya | Kemungkinan |
|:---|:---|:---|
| Component route benar? | Apakah URL menunjukkan `/dashboard`? | Route mungkin salah, ubah ke `Route::get('/dashboard', ...::`\`  |
| Blade file location benar? | `resources/views/livewire/dashboard/overview.blade.php` ada? | File mungkin di tempat lain, cari `find resources/views -name "*overview*"` |
| Component dipanggil di layout? | Cek `resources/views/layouts/app.blade.php` apakah ada `<livewire:dashboard.overview />` atau `@livewire('dashboard.overview')`? | Component tidak di-render |

---

## 📋 Sebelum test ulang, pastikan:

- ✅ File yang di-edit sudah tersimpan
- ✅ Container di-rebuild (jika perubahan Dockerfile): `podman compose build`
- ✅ Database sudah punya data (minimal 1 user + 1 board + 1 card)
- ✅ Browser cache dihapus (Ctrl+Shift+Delete atau incognito)
- ✅ Livewire cache: `php artisan livewire:publish`

**Tanya ke saya jika masih ada**:
1. Screenshot error atau page kosong
2. Output dari `php artisan tinker` (data ada?)
3. Output dari browser console error (F12 → Console)

Tapi kemungkinan besar issue adalah **komponen query belum di-update ke live data**, jadi ikuti Step 1–3 di atas dulu. 👍