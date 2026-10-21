<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DropLinkStatus;
use App\Enums\DropLinkTarget;
use Carbon\CarbonImmutable;
use Database\Factories\DropLinkFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $user_id
 * @property DropLinkTarget $target_type
 * @property int|null $attachment_id
 * @property int|null $card_id
 * @property string $token
 * @property string|null $label
 * @property string|null $password_hash
 * @property CarbonImmutable $expires_at
 * @property int|null $max_downloads
 * @property int $download_count
 * @property CarbonImmutable|null $last_accessed_at
 * @property CarbonImmutable|null $revoked_at
 */
class DropLink extends Model
{
    /** @use HasFactory<DropLinkFactory> */
    use HasFactory;

    protected $table = 'droplinks';

    protected $fillable = [
        'user_id',
        'target_type',
        'attachment_id',
        'card_id',
        'token',
        'label',
        'password_hash',
        'expires_at',
        'max_downloads',
        'download_count',
        'last_accessed_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'target_type' => DropLinkTarget::class,
            'expires_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'revoked_at' => 'datetime',
            'download_count' => 'integer',
            'max_downloads' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Attachment, $this>
     */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }

    /**
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * @return HasMany<DropLinkAccess, $this>
     */
    public function accesses(): HasMany
    {
        return $this->hasMany(DropLinkAccess::class, 'droplink_id')->orderByDesc('created_at');
    }

    public function status(): DropLinkStatus
    {
        if ($this->revoked_at !== null) {
            return DropLinkStatus::Revoked;
        }

        if ($this->expires_at->isPast()) {
            return DropLinkStatus::Expired;
        }

        if ($this->max_downloads !== null && $this->download_count >= $this->max_downloads) {
            return DropLinkStatus::Exhausted;
        }

        return DropLinkStatus::Active;
    }

    public function isActive(): bool
    {
        return $this->status() === DropLinkStatus::Active;
    }

    public function requiresPassword(): bool
    {
        return $this->password_hash !== null;
    }

    public function remainingDownloads(): ?int
    {
        if ($this->max_downloads === null) {
            return null;
        }

        return max(0, $this->max_downloads - $this->download_count);
    }

    /**
     * Atomically consume one download slot. Returns false when the link is
     * revoked, expired, or exhausted at the moment of the update.
     */
    public function consumeDownload(): bool
    {
        $affected = static::query()
            ->whereKey($this->getKey())
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->where(function (Builder $query): void {
                $query->whereNull('max_downloads')
                    ->orWhereColumn('download_count', '<', 'max_downloads');
            })
            ->update([
                'download_count' => DB::raw('download_count + 1'),
                'last_accessed_at' => now(),
            ]);

        if ($affected !== 1) {
            return false;
        }

        $this->refresh();

        return true;
    }

    /**
     * @param  Builder<DropLink>  $query
     * @return Builder<DropLink>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->where(function (Builder $inner): void {
                $inner->whereNull('max_downloads')
                    ->orWhereColumn('download_count', '<', 'max_downloads');
            });
    }
}
