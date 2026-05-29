<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramBatch extends Model
{
    protected $fillable = [
        'workspace_id',
        'chat_id',
        'first_event_at',
        'flush_at',
    ];

    protected $casts = [
        'first_event_at' => 'datetime',
        'flush_at' => 'datetime',
    ];

    public function pendingEvents(): HasMany
    {
        return $this->hasMany(TelegramPendingEvent::class);
    }
}
