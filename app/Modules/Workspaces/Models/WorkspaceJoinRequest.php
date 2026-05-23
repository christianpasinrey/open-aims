<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceJoinRequest extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id', 'status', 'message',
        'responded_by_user_id', 'responded_at',
    ];

    protected $casts = ['responded_at' => 'datetime'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
