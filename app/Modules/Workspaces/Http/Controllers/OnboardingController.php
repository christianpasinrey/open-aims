<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

final class OnboardingController
{
    public function index(): Response
    {
        return Inertia::render('workspace/Onboarding');
    }
}
