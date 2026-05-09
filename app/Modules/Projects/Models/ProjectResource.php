<?php

declare(strict_types=1);

namespace App\Modules\Projects\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A project "resource" is either an uploaded document (type=file, with a
 * Spatie media row in the 'attachment' collection) or a saved external link
 * (type=link, with the URL in the `url` column).
 */
class ProjectResource extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'project_id',
        'type',
        'is_plan',
        'name',
        'url',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_plan' => 'bool',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachment')
            ->singleFile()
            ->useDisk('public');
    }
}
