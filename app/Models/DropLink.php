<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DropLinkTarget;
use Database\Factories\DropLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
