<?php

use AgedNerd\Masquerade\Services\MasqueradeManager;
use Illuminate\Contracts\Auth\Authenticatable;

if (! function_exists('can_masquerade')) {
    function can_masquerade(?string $guard = null): bool
    {
        $manager = app(MasqueradeManager::class);
        $guard ??= $manager->getCurrentAuthGuardName();
        $user = $guard ? auth()->guard($guard)->user() : null;

        return $user instanceof Authenticatable
            && method_exists($user, 'canMasquerade')
            && $user->canMasquerade();
    }
}

if (! function_exists('can_be_masqueraded')) {
    function can_be_masqueraded(Authenticatable $user, ?string $guard = null): bool
    {
        $manager = app(MasqueradeManager::class);
        $guard ??= $manager->getCurrentAuthGuardName();
        $actor = $guard ? auth()->guard($guard)->user() : null;

        return $actor instanceof Authenticatable
            && $actor->getAuthIdentifier() != $user->getAuthIdentifier()
            && method_exists($actor, 'canMasquerade')
            && $actor->canMasquerade($user)
            && method_exists($user, 'canBeMasqueraded')
            && $user->canBeMasqueraded($actor);
    }
}

if (! function_exists('is_masquerading')) {
    function is_masquerading(?string $guard = null): bool
    {
        return app(MasqueradeManager::class)->isMasquerading();
    }
}

if (! function_exists('get_masquerader')) {
    function get_masquerader(): ?Authenticatable
    {
        return app(MasqueradeManager::class)->getMasquerader();
    }
}
