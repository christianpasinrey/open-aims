<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Github\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GithubPullRequest extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'repo_id',
        'github_id',
        'node_id',
        'number',
        'title',
        'body',
        'state',
        'merged',
        'draft',
        'author_login',
        'author_id',
        'head_branch_name',
        'head_branch_id',
        'head_sha',
        'base_ref',
        'merge_commit_sha',
        'additions',
        'deletions',
        'changed_files',
        'commits_count',
        'html_url',
        'opened_at',
        'closed_at',
        'merged_at',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'merged' => 'boolean',
        'draft' => 'boolean',
        'additions' => 'integer',
        'deletions' => 'integer',
        'changed_files' => 'integer',
        'commits_count' => 'integer',
        'number' => 'integer',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'merged_at' => 'datetime',
    ];

    public function repo(): BelongsTo
    {
        return $this->belongsTo(GithubRepo::class, 'repo_id');
    }

    public function headBranch(): BelongsTo
    {
        return $this->belongsTo(GithubBranch::class, 'head_branch_id');
    }
}
