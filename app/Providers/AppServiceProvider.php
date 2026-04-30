<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePassportScopes();
    }

    /**
     * Define OAuth scopes Passport will issue. The `mcp` scope (and the
     * `mcp:use` alias the laravel/mcp package advertises) is the single
     * permission an MCP client (Claude Desktop, Claude Code) needs to
     * operate this workspace via the /mcp endpoint.
     */
    protected function configurePassportScopes(): void
    {
        Passport::tokensCan([
            'mcp' => 'Operate aims via Model Context Protocol',
            'mcp:use' => 'Operate aims via Model Context Protocol',
        ]);
        Passport::setDefaultScope(['mcp']);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
