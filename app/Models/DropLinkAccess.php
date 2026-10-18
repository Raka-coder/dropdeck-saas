<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccessEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DropLinkAccess extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'droplink_accesses';

    protected $fillable = [
        'droplink_id',
        'attachment_id',
        'event',
        'blocked_reason',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'event' => AccessEvent::class,
        ];
    }

    /**
     * @return BelongsTo<DropLink, $this>
     */
    public function droplink(): BelongsTo
    {
        return $this->belongsTo(DropLink::class, 'droplink_id');
    }

    /**
     * @return BelongsTo<Attachment, $this>
     */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }
}
