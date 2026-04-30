<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Github\Models;

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A GitHub App installation tied to a workspace. One install per
 * (workspace, installation_id). The actual repository selection is
 * either 'all' (org-wide) or 'selected' (user picked specific repos).
 */
class GithubInstallation extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'installation_id',
        'account_login',
        'account_type',
        'repository_selection',
        'suspended_at',
    ];

    protected $casts = [
        'suspended_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function linkedPullRequests(): HasMany
    {
        return $this->hasMany(GithubLinkedPullRequest::class, 'installation_id');
    }

    public function isActive(): bool
    {
        return $this->suspended_at === null;
    }
}
