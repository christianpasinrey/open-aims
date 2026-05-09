<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Github\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic link between a GitHub source row (a branch or a pull
 * request) and a aims linkable (an Issue or a Project).
 *
 * Either auto-created by LinkPullRequestAction (when the matcher
 * resolves a PR head branch to an issue) or manually created from the
 * picker on the issue / project right rail.
 */
final class GithubLink extends Model
{
    protected $table = 'github_links';

    /** @var list<string> */
    protected $fillable = [
        'source_type',
        'source_id',
        'linkable_type',
        'linkable_id',
        'linked_by_user_id',
        'auto',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'source_id' => 'integer',
        'linkable_id' => 'integer',
        'auto' => 'boolean',
    ];

    /**
     * @return MorphTo<Model, GithubLink>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by_user_id');
    }

    /**
     * Resolve the polymorphic source row (branch or PR) on demand.
     */
    public function source(): GithubBranch|GithubPullRequest|null
    {
        return match ($this->source_type) {
            'branch' => GithubBranch::query()->find($this->source_id),
            'pull_request' => GithubPullRequest::query()->find($this->source_id),
            default => null,
        };
    }
}
