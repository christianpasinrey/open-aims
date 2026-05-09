<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Github\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class GithubBranch extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'repo_id',
        'name',
        'head_sha',
        'last_pusher_login',
        'last_pushed_at',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'last_pushed_at' => 'datetime',
    ];

    public function repo(): BelongsTo
    {
        return $this->belongsTo(GithubRepo::class, 'repo_id');
    }
}
