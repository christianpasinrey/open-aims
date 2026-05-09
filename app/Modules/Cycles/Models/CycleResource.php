<?php

declare(strict_types=1);

namespace App\Modules\Cycles\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CycleResource extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'cycle_id',
        'type',
        'name',
        'url',
        'created_by_user_id',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
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
