<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Github\Models;

use App\Modules\Issues\Models\Issue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pull request linked to an Issue, sourced from a GitHub App
 * installation. We persist enough to render the right rail without
 * a follow-up REST call. Re-syncs upsert by (installation_id, pr_node_id).
 */
class GithubLinkedPullRequest extends Model
{
    use HasFactory;

    protected $table = 'github_linked_pull_requests';

    protected $fillable = [
        'issue_id',
        'installation_id',
        'pr_number',
        'pr_node_id',
        'pr_title',
        'pr_state',
        'pr_url',
        'branch_name',
        'author_login',
        'opened_at',
        'closed_at',
        'merged_at',
    ];

    protected $casts = [
        'pr_number' => 'integer',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'merged_at' => 'datetime',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(GithubInstallation::class, 'installation_id');
    }
}
