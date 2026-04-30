<?php

declare(strict_types=1);

namespace App\Modules\Issues\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IssueActivity extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'issue_id',
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

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
