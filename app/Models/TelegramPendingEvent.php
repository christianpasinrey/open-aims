<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramPendingEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'telegram_batch_id',
        'html',
        'mention',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TelegramBatch::class, 'telegram_batch_id');
    }
}
