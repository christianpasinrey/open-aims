<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Github\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class GithubRepo extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'installation_id',
        'github_id',
        'node_id',
        'full_name',
        'default_branch',
        'language',
        'private',
        'archived',
        'html_url',
        'description',
        'last_pushed_at',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'private' => 'boolean',
        'archived' => 'boolean',
        'last_pushed_at' => 'datetime',
    ];

    public function installation(): BelongsTo
    {
        return $this->belongsTo(GithubInstallation::class, 'installation_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(GithubBranch::class, 'repo_id');
    }

    public function pullRequests(): HasMany
    {
        return $this->hasMany(GithubPullRequest::class, 'repo_id');
    }
}
