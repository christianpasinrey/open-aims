<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Github\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Raw, immutable record of a webhook delivery from GitHub. The full body
 * is preserved verbatim in `payload`. Use this as the source of truth for
 * any state derived later (branches, PRs, links) — derivation can be
 * replayed by clearing `processed_at` and re-running the ingester.
 */
final class GithubWebhookEvent extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'delivery_id',
        'installation_id',
        'installation_external_id',
        'event_type',
        'action',
        'repository_full_name',
        'sender_login',
        'signature_ok',
        'payload',
        'processed_at',
        'processing_error',
        'received_at',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'payload' => 'array',
        'signature_ok' => 'boolean',
        'processed_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function installation(): BelongsTo
    {
        return $this->belongsTo(GithubInstallation::class, 'installation_id');
    }
}
