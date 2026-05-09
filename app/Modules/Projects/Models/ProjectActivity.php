<?php

declare(strict_types=1);

namespace App\Modules\Projects\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProjectActivity extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'project_id',
        'actor_user_id',
        'kind',
        'payload',
        'occurred_at',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
