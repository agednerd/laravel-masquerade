<?php

namespace AgedNerd\Masquerade\Services;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Cookie\Factory as CookieFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use InvalidArgumentException;
use AgedNerd\Masquerade\Events\MasqueradeEnded;
use AgedNerd\Masquerade\Events\MasqueradeStarted;
use AgedNerd\Masquerade\Exceptions\InvalidUserProvider;
use AgedNerd\Masquerade\Exceptions\MissingUserProvider;
use RuntimeException;

final class MasqueradeManager
{
    private ?Closure $takeRedirectResolver = null;
    private ?Closure $leaveRedirectResolver = null;

    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Store $session,
        private readonly Repository $config,
        private readonly Dispatcher $events,
        private readonly CookieFactory $cookies,
        private readonly Request $request,
    ) {
    }

    public function findUserById(int|string $id, ?string $guardName = null): Authenticatable
    {
        $guardName ??= $this->defaultGuard();
        $providerName = $this->config->get("auth.guards.{$guardName}.provider");

        if (! is_string($providerName) || $providerName === '') {
            throw new MissingUserProvider($guardName);
        }

        try {
            /** @var UserProvider|null $provider */
            $provider = $this->auth->createUserProvider($providerName);
        } catch (InvalidArgumentException) {
            throw new InvalidUserProvider($guardName);
        }

        if (! $provider || ! $user = $provider->retrieveById($id)) {
            $model = $this->config->get("auth.providers.{$providerName}.model", Authenticatable::class);
            throw (new ModelNotFoundException())->setModel($model, $id);
        }

        return $user;
    }

    public function take(
        Authenticatable $from,
        Authenticatable $to,
        ?string $targetGuard = null,
        ?bool $remember = null,
        ?string $sourceGuard = null,
    ): bool {
        $sourceGuard ??= $this->getCurrentAuthGuardName();
        $targetGuard ??= $sourceGuard ?? $this->defaultGuard();

        if (! $sourceGuard) {
            throw new RuntimeException('No authenticated stateful guard was found.');
        }

        $source = $this->statefulGuard($sourceGuard);
        $target = $this->statefulGuard($targetGuard);
        $remember ??= $this->shouldRemember($source, $from);

        $frames = $this->frames();
        $frames[] = [
            'source_id' => $from->getAuthIdentifier(),
            'source_guard' => $sourceGuard,
            'target_id' => $to->getAuthIdentifier(),
            'target_guard' => $targetGuard,
            'remember' => $remember,
        ];

        if ($sourceGuard !== $targetGuard) {
            $this->quietLogout($source);
        }

        $target->login($to, $remember);
        $this->storeFrames($frames);
        $this->events->dispatch(new MasqueradeStarted($from, $to, $sourceGuard, $targetGuard, count($frames)));

        return true;
    }

    public function leave(): bool
    {
        $frames = $this->frames();
        $frame = array_pop($frames);

        if (! is_array($frame)) {
            return false;
        }

        $target = $this->statefulGuard($frame['target_guard']);
        $subject = $target->user();
        $masquerader = $this->findUserById($frame['source_id'], $frame['source_guard']);

        if ($frame['source_guard'] !== $frame['target_guard']) {
            $this->quietLogout($target);
        }

        $this->statefulGuard($frame['source_guard'])->login($masquerader, (bool) $frame['remember']);
        $this->storeFrames($frames);

        if ($subject instanceof Authenticatable) {
            $this->events->dispatch(new MasqueradeEnded(
                $masquerader,
                $subject,
                $frame['source_guard'],
                $frame['target_guard'],
                count($frames),
            ));
        }

        return true;
    }

    public function clear(): void
    {
        $this->storeFrames([]);
    }

    public function isMasquerading(): bool
    {
        return $this->depth() > 0;
    }

    public function depth(): int
    {
        return count($this->frames());
    }

    public function getMasqueraderId(): int|string|null
    {
        $frame = $this->frames()[array_key_last($this->frames())] ?? null;
        return $frame['source_id'] ?? null;
    }

    public function getOriginalMasqueraderId(): int|string|null
    {
        return $this->frames()[0]['source_id'] ?? null;
    }

    public function getMasquerader(): ?Authenticatable
    {
        $frame = $this->frames()[array_key_last($this->frames())] ?? null;
        return $frame ? $this->findUserById($frame['source_id'], $frame['source_guard']) : null;
    }

    public function getOriginalMasquerader(): ?Authenticatable
    {
        $frame = $this->frames()[0] ?? null;
        return $frame ? $this->findUserById($frame['source_id'], $frame['source_guard']) : null;
    }

    public function getMasqueraderGuardName(): ?string
    {
        $frame = $this->frames()[array_key_last($this->frames())] ?? null;
        return $frame['source_guard'] ?? null;
    }

    public function getMasqueradeGuardName(): ?string
    {
        $frame = $this->frames()[array_key_last($this->frames())] ?? null;
        return $frame['target_guard'] ?? null;
    }

    public function getCurrentAuthGuardName(): ?string
    {
        foreach (array_keys($this->config->get('auth.guards', [])) as $name) {
            $guard = $this->auth->guard($name);
            if ($guard instanceof StatefulGuard && $guard->check()) {
                return $name;
            }
        }

        return null;
    }

    public function setTakeRedirectResolver(Closure $resolver): self
    {
        $this->takeRedirectResolver = $resolver;
        return $this;
    }

    public function setLeaveRedirectResolver(Closure $resolver): self
    {
        $this->leaveRedirectResolver = $resolver;
        return $this;
    }

    public function getTakeRedirectTo(?string $requested = null): string
    {
        $value = $this->takeRedirectResolver
            ? ($this->takeRedirectResolver)($this, $requested)
            : ($requested ?? $this->config->get('masquerade.take_redirect_to', '/'));

        return $this->safeRedirect((string) $value);
    }

    public function getLeaveRedirectTo(?string $requested = null): string
    {
        $value = $this->leaveRedirectResolver
            ? ($this->leaveRedirectResolver)($this, $requested)
            : ($requested ?? $this->config->get('masquerade.leave_redirect_to', '/'));

        return $this->safeRedirect((string) $value);
    }

    public function getSessionKey(): string
    {
        return (string) $this->config->get('masquerade.session_key');
    }

    public function getDefaultSessionGuard(): string
    {
        return $this->defaultGuard();
    }

    /** @return list<array{source_id:int|string,source_guard:string,target_id:int|string,target_guard:string,remember:bool}> */
    private function frames(): array
    {
        $frames = $this->session->get($this->getSessionKey(), []);

        if ($frames === [] && is_string($persisted = $this->request->cookie($this->cookieKey()))) {
            $decoded = json_decode($persisted, true);
            if (is_array($decoded) && $this->validFrames($decoded) && $this->matchesRememberedTarget($decoded)) {
                $frames = $decoded;
                $this->session->put($this->getSessionKey(), $frames);
            }
        }

        return is_array($frames) && $this->validFrames($frames) ? array_values($frames) : [];
    }

    private function storeFrames(array $frames): void
    {
        if ($frames === []) {
            $this->session->forget($this->getSessionKey());
            $this->cookies->queue($this->cookies->forget($this->cookieKey()));
            return;
        }

        $this->session->put($this->getSessionKey(), $frames);
        $this->cookies->queue(
            $this->cookieKey(),
            json_encode($frames, JSON_THROW_ON_ERROR),
            (int) $this->config->get('masquerade.remember_cookie_minutes', 43_200),
            '/', null, null, true, false, 'lax',
        );
    }

    private function validFrames(array $frames): bool
    {
        foreach ($frames as $frame) {
            if (! is_array($frame) || ! isset($frame['source_id'], $frame['source_guard'], $frame['target_id'], $frame['target_guard'])) {
                return false;
            }
        }
        return true;
    }

    private function matchesRememberedTarget(array $frames): bool
    {
        $frame = $frames[array_key_last($frames)] ?? null;
        if (! is_array($frame)) {
            return false;
        }

        $guard = $this->auth->guard($frame['target_guard']);

        return $guard instanceof StatefulGuard
            && $guard->viaRemember()
            && $guard->user()?->getAuthIdentifier() == $frame['target_id'];
    }

    private function statefulGuard(string $name): StatefulGuard
    {
        $guard = $this->auth->guard($name);
        if (! $guard instanceof StatefulGuard) {
            throw new RuntimeException("Guard [{$name}] is not stateful. Use a session guard; Sanctum SPA authentication is supported through its web guard.");
        }
        return $guard;
    }

    private function quietLogout(StatefulGuard $guard): void
    {
        if (method_exists($guard, 'logoutCurrentDevice')) {
            $guard->logoutCurrentDevice();
            return;
        }
        $guard->logout();
    }

    private function shouldRemember(StatefulGuard $guard, Authenticatable $from): bool
    {
        return match ($this->config->get('masquerade.remember', 'inherit')) {
            true => true,
            'inherit' => $guard->viaRemember() || filled($from->getRememberToken()),
            default => false,
        };
    }

    private function safeRedirect(string $value): string
    {
        if ($value === 'back' || str_starts_with($value, '/')) {
            return $value;
        }
        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            try {
                return route($value);
            } catch (InvalidArgumentException) {
                return '/';
            }
        }
        if ($this->config->get('masquerade.allow_external_redirects', false)) {
            return $value;
        }
        return '/';
    }

    private function cookieKey(): string
    {
        return (string) $this->config->get('masquerade.cookie_key');
    }

    private function defaultGuard(): string
    {
        return (string) $this->config->get('masquerade.default_guard', $this->config->get('auth.defaults.guard', 'web'));
    }
}
