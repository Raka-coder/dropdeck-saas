<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ThumbnailStatus;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'card_id',
        'disk',
        'path',
        'original_name',
        'extension',
        'mime_type',
        'size_bytes',
        'checksum_sha256',
        'thumbnail_path',
        'thumbnail_status',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'thumbnail_status' => ThumbnailStatus::class,
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
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
