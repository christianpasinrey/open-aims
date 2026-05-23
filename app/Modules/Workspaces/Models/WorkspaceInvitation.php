<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceInvitation extends Model
{
    protected $fillable = [
        'workspace_id',
        'email',
        'role',
        'token',
        'invited_by_user_id',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value) => strtolower($value));
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isAcceptable(): bool
    {
        return $this->accepted_at === null && $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
