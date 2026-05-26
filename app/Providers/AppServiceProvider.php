<?php

namespace App\Providers;

use App\Listeners\CaptureMcpClientMetadata;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Laravel\Passport\Events\AccessTokenCreated;
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

        // Tag every issued OAuth access token with the originating
        // device's User-Agent + IP so /settings/developer can render
        // 'Claude Desktop · macOS' vs '… · Windows' separately.
        Event::listen(AccessTokenCreated::class, CaptureMcpClientMetadata::class);
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

        // Use the consent screen the laravel/mcp package ships with —
        // it already styles the OAuth approval UI for MCP flows. Without
        // this binding, Passport's AuthorizationViewResponse is not
        // instantiable and /oauth/authorize 500s.
        Passport::authorizationView(fn (array $parameters): View => view(
            'mcp::authorize',
            $parameters,
        ));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Stop Laravel emitting the Vite asset-preload `Link` response header.
        // On full page loads it lists every chunk and overflows nginx's proxy
        // header buffer behind Plesk (which we can't reliably enlarge), 502'ing
        // deep links to asset-heavy pages. Async components shrink it but don't
        // guarantee it fits; dropping the header removes the failure mode for
        // good. Assets still load normally — we just lose the preload hint.
        Vite::usePreloadTagAttributes(false);

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
