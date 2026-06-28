# Laravel Masquerade

**Securely assume another user's identity.**

Laravel Masquerade is a security-focused Laravel 13 package for temporarily authenticating as another user. It is published as `agednerd/laravel-masquerade` under the `AgedNerd\\Masquerade` namespace.

## Requirements

- PHP 8.3+
- Laravel 13
- A session-backed authentication guard

Sanctum SPA authentication works through its underlying `web` guard. Personal-access-token masquerades are deliberately not synthesized: bearer tokens are credentials, and silently exchanging them creates revocation and audit semantics that belong in an application-specific token broker.

## Install

After publishing, install it from Packagist:

```bash
composer require agednerd/laravel-masquerade
php artisan vendor:publish --tag=masquerade-config
```

While developing locally, use a Composer path repository:

```json
{
  "repositories": [{"type": "path", "url": "../laravel-masquerade"}],
  "require": {"agednerd/laravel-masquerade": "@dev"}
}
```

Then run `composer update agednerd/laravel-masquerade`.

Add the trait and explicitly authorize actors. Authorization now denies by default.

```php
use Illuminate\Contracts\Auth\Authenticatable;
use AgedNerd\Masquerade\Concerns\Masqueradable;

class User extends Authenticatable
{
    use Masqueradable;

    public function canMasquerade(?Authenticatable $subject = null): bool
    {
        return $this->is_admin && ! $subject?->is_admin;
    }

    public function canBeMasqueraded(?Authenticatable $masquerader = null): bool
    {
        return ! $this->is_admin;
    }
}
```

Register the routes inside the `web` middleware group:

```php
Route::middleware('web')->group(fn () => Route::masquerade());
```

Use forms for the state-changing endpoints:

```blade
<form method="POST" action="{{ route('masquerade.take', $user) }}">
    @csrf
    <input type="hidden" name="redirect_to" value="/dashboard">
    <button>Masquerade</button>
</form>

<form method="POST" action="{{ route('masquerade.leave') }}">
    @csrf
    @method('DELETE')
    <button>Return to my account</button>
</form>
```

## Core API

```php
$manager = app(\AgedNerd\Masquerade\Services\MasqueradeManager::class);

$manager->take($actor, $target, targetGuard: 'web', remember: true);
$manager->isMasquerading();
$manager->depth();
$manager->getMasquerader();          // immediate parent
$manager->getOriginalMasquerader();  // bottom of a nested stack
$manager->leave();                    // unwinds one level
```

The manager is request-scoped for Laravel Octane. Cross-guard transitions restore the original guard when leaving. Remembered masquerades issue a normal Laravel recaller for the subject and keep the encrypted masquerade stack in an HTTP-only, SameSite=Lax cookie. Cookie recovery only occurs when Laravel actually restored the matching subject through "remember me".

Dynamic redirects can be supplied as relative paths or route names. External URLs are rejected unless explicitly enabled.

```php
$manager->setTakeRedirectResolver(
    fn ($manager, $requested) => auth()->user()->is_admin ? '/admin' : '/dashboard'
);
```

## Blade and helpers

```blade
@masquerading ... @endmasquerading
@notMasquerading ... @endnotMasquerading
@canMasquerade ... @endcanMasquerade
@canBeMasqueraded($user) ... @endcanBeMasqueraded
```

Helpers: `is_masquerading()`, `can_masquerade()`, `can_be_masqueraded($user)`, and `get_masquerader()`.

## Events and route protection

`MasqueradeStarted` and `MasqueradeEnded` include masquerader, subject, source guard, target guard, and resulting stack depth for audit logging. Apply `masquerade.protect` to billing, credential, destructive, and other sensitive routes; it returns HTTP 403 during a masquerade.

## Compatibility notes

- Default authorization changed from allow to deny.
- The default routes are `POST /masquerade/{id}/{guardName?}` and `DELETE /masquerade`.
- Legacy GET routes can be enabled with `legacy_get_routes`, but are not recommended.
- The package no longer replaces Laravel's global `session` auth driver.
- Only Laravel 13/PHP 8.3+ are supported in this branch; use the upstream package for older applications.

## Test

```bash
composer install
vendor/bin/phpunit
```
