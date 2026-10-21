<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\User;
use App\Support\Bytes;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class StorageMeter extends Component
{
    /**
     * @return array{
     *     used: int,
     *     quota: int,
     *     percent: float,
     *     barPercent: float,
     *     level: 'ok'|'warning'|'full',
     *     usedLabel: string,
     *     quotaLabel: string,
     *     remainingLabel: string,
     *     percentLabel: string
     * }
     */
    #[Computed]
    public function usage(): array
    {
        $user = Auth::user();

        $used = $user instanceof User ? max(0, (int) $user->storage_used_bytes) : 0;
        $quota = $user instanceof User ? max(0, (int) $user->storage_quota_bytes) : 0;

        if ($quota === 0) {
            $quota = Bytes::megabytes((int) config('dropdeck.limits.quota_mb'));
        }

        $percent = $quota > 0 ? round($used / $quota * 100, 1) : 0.0;

        $warning = (int) config('dropdeck.dashboard.quota_warning_percent');
        $critical = (int) config('dropdeck.dashboard.quota_critical_percent');

        $level = match (true) {
            $percent >= $critical => 'full',
            $percent >= $warning => 'warning',
            default => 'ok',
        };

        return [
            'used' => $used,
            'quota' => $quota,
            'percent' => $percent,
            'barPercent' => min(100.0, $percent),
            'level' => $level,
            'usedLabel' => Bytes::human($used),
            'quotaLabel' => Bytes::human($quota),
            'remainingLabel' => Bytes::human(max(0, $quota - $used)),
            'percentLabel' => number_format($percent, 1, ',', '.').'%',
        ];
    }

    public function render(): View
    {
        return view('livewire.dashboard.storage-meter');
    }
}
