<?php

declare(strict_types=1);

namespace App\Modules\Initiatives\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InitiativeMember extends Model
{
    protected $fillable = [
        'initiative_id',
        'user_id',
        'role',
        'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function initiative(): BelongsTo
    {
        return $this->belongsTo(Initiative::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
