<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Registries\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Ensures the requested module slug is registered (and, in future, active
 * for the resolved workspace). For now we only check registration so that
 * routes from disabled modules return 404 cleanly.
 */
final class EnsureModuleActive
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    public function handle(Request $request, Closure $next, string $moduleSlug): Response
    {
        if ($this->registry->get($moduleSlug) === null) {
            throw new NotFoundHttpException("Module [{$moduleSlug}] is not installed.");
        }

        return $next($request);
    }
}
