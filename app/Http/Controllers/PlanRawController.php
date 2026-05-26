<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class PlanRawController
{
    public function __invoke(Plan $plan): Response
    {
        $planable = $plan->planable;
        abort_if($planable === null, SymfonyResponse::HTTP_NOT_FOUND);

        $isMember = WorkspaceMember::query()
            ->where('workspace_id', $planable->workspace_id)
            ->where('user_id', auth()->id())
            ->exists();
        abort_unless($isMember, SymfonyResponse::HTTP_FORBIDDEN);

        $contentType = $plan->format === 'html' ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8';

        return response($plan->content, SymfonyResponse::HTTP_OK, [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
