<?php

namespace AgedNerd\Masquerade\Concerns;

use AgedNerd\Masquerade\Services\MasqueradeManager;
use Illuminate\Contracts\Auth\Authenticatable;

trait Masqueradable
{
    public function canMasquerade(?Authenticatable $subject = null): bool
    {
        return false;
    }

    public function canBeMasqueraded(?Authenticatable $masquerader = null): bool
    {
        return true;
    }

    public function masqueradeAs(Authenticatable $user, ?string $guardName = null, ?bool $remember = null): bool
    {
        if (! $this->canMasquerade($user)) {
            return false;
        }

        if (! method_exists($user, 'canBeMasqueraded') || ! $user->canBeMasqueraded($this)) {
            return false;
        }

        return app(MasqueradeManager::class)->take($this, $user, $guardName, $remember);
    }

    public function isMasquerading(): bool
    {
        return app(MasqueradeManager::class)->isMasquerading();
    }

    public function leaveMasquerade(): bool
    {
        return app(MasqueradeManager::class)->leave();
    }
}
