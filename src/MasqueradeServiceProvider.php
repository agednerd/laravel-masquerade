<?php

namespace AgedNerd\Masquerade;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use AgedNerd\Masquerade\Controllers\MasqueradeController;
use AgedNerd\Masquerade\Middleware\ProtectFromMasquerade;
use AgedNerd\Masquerade\Services\MasqueradeManager;

final class MasqueradeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/masquerade.php', 'masquerade');

        // Request-scoped prevents stale container/request references under Octane.
        $this->app->scoped(MasqueradeManager::class);
        $this->app->alias(MasqueradeManager::class, 'masquerade');
    }

    public function boot(Router $router): void
    {
        $this->publishes([
            __DIR__.'/../config/masquerade.php' => config_path('masquerade.php'),
        ], 'masquerade-config');

        $router->aliasMiddleware('masquerade.protect', ProtectFromMasquerade::class);
        $this->registerRoutes($router);
        $this->registerBlade();
    }

    private function registerRoutes(Router $router): void
    {
        $router->macro('masquerade', function () use ($router): void {
            $router->post('/masquerade/{id}/{guardName?}', [MasqueradeController::class, 'take'])
                ->name('masquerade.take');
            $router->delete('/masquerade', [MasqueradeController::class, 'leave'])
                ->name('masquerade.leave');

            if (config('masquerade.legacy_get_routes')) {
                $router->get('/masquerade/take/{id}/{guardName?}', [MasqueradeController::class, 'take'])
                    ->name('masquerade');
                $router->get('/masquerade/leave', [MasqueradeController::class, 'leave'])
                    ->name('masquerade.leave.legacy');
            }
        });
    }

    private function registerBlade(): void
    {
        Blade::if('masquerading', fn (?string $guard = null): bool => is_masquerading($guard));
        Blade::if('notMasquerading', fn (?string $guard = null): bool => ! is_masquerading($guard));
        Blade::if('canMasquerade', fn (?string $guard = null): bool => can_masquerade($guard));
        Blade::if('canBeMasqueraded', fn ($user, ?string $guard = null): bool => can_be_masqueraded($user, $guard));
    }
}
