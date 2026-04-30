<?php

declare(strict_types=1);

namespace App\Modules\Issues\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IssueRelation extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'source_issue_id',
        'target_issue_id',
        'type',
        'created_by_user_id',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'source_issue_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'target_issue_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
