<?php

declare(strict_types=1);

namespace App\Modules\Teams\Models;

use App\Modules\Issues\Models\Issue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Label extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'color',
        'description',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function issues(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class, 'issue_labels');
    }
}
