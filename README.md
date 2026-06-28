# Laravel Masquerade

[![Tests](https://github.com/agednerd/laravel-masquerade/actions/workflows/run-tests.yml/badge.svg)](https://github.com/agednerd/laravel-masquerade/actions/workflows/run-tests.yml)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Securely assume another user's identity.**

Laravel Masquerade provides secure, nested, remembered, and multi-guard user switching for Laravel 13. It uses Laravel's normal session guards and does not replace the framework's authentication driver.

## Features

- Deny-by-default authorization with checks on both users.
- POST and DELETE routes protected by Laravel's `web` middleware and CSRF handling.
- Nested masquerades that unwind one level at a time.
- Same-guard and cross-guard switching with restoration of the source guard.
- Optional remember-me behavior with an encrypted, HTTP-only stack cookie.
- Octane-safe request-scoped services.
- Safe relative or named-route redirects; external redirects are disabled by default.
- Blade conditions, helper functions, lifecycle events, and sensitive-route middleware.

## Requirements

- PHP 8.3 or newer.
- Laravel 13.
- At least one session-backed guard implementing `StatefulGuard`.

Sanctum SPA authentication is supported through its underlying `web` guard. Personal access token masquerades are not synthesized: bearer-token exchange, revocation, and audit behavior should be implemented by an application-specific token broker.

## Installation

Install the package after its first stable release is published:

```bash
composer require agednerd/laravel-masquerade
```

Laravel discovers the service provider and `Masquerade` facade automatically. Publishing the configuration is optional:

```bash
php artisan vendor:publish --tag=masquerade-config
```

Before a stable tag exists, the development branch can be installed explicitly:

```bash
composer require agednerd/laravel-masquerade:dev-main
```

## Model setup and authorization

Add `Masqueradable` to every authenticatable model that can initiate or become the subject of a masquerade:

```php
use AgedNerd\Masquerade\Concerns\Masqueradable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Masqueradable;

    public function canMasquerade(?AuthenticatableContract $subject = null): bool
    {
        return $this->is_admin && ! $subject?->is_admin;
    }

    public function canBeMasqueraded(?AuthenticatableContract $masquerader = null): bool
    {
        return ! $this->is_admin;
    }
}
```

`canMasquerade()` returns `false` by default. `canBeMasqueraded()` returns `true` by default. The built-in controller and `masqueradeAs()` require both checks to pass. Keep authorization decisions on the server; never rely on hiding a button.

## Registering routes

Register the route macro in `routes/web.php`. The `web` middleware is required for sessions, encrypted cookies, and CSRF protection:

```php
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::masquerade();
});
```

This registers:

| Method | URI | Name | Purpose |
| --- | --- | --- | --- |
| `POST` | `/masquerade/{id}/{guardName?}` | `masquerade.take` | Start a masquerade |
| `DELETE` | `/masquerade` | `masquerade.leave` | Leave one level |

`id` must be the value returned by the subject's `getAuthIdentifier()`. It is not implicit route-model binding.

## Starting and leaving

```blade
<form method="POST" action="{{ route('masquerade.take', ['id' => $user->getAuthIdentifier()]) }}">
    @csrf
    <input type="hidden" name="redirect_to" value="/dashboard">
    <button type="submit">Masquerade</button>
</form>

<form method="POST" action="{{ route('masquerade.leave') }}">
    @csrf
    @method('DELETE')
    <button type="submit">Return to my account</button>
</form>
```

For another target guard, pass its name as the second route parameter:

```php
route('masquerade.take', [
    'id' => $user->getAuthIdentifier(),
    'guardName' => 'customer',
]);
```

The optional request fields are:

- `remember`: a boolean override for remembered login behavior.
- `redirect_to`: a relative path or route name used after the transition.

## Model API

The model API evaluates both authorization hooks:

```php
$started = $actor->masqueradeAs($subject, guardName: 'web', remember: true);

auth()->user()->isMasquerading();
auth()->user()->leaveMasquerade();
```

Nested calls add frames to the stack. `leaveMasquerade()` unwinds only the latest frame.

## Manager and facade

Resolve the request-scoped manager when you need stack or guard details:

```php
use AgedNerd\Masquerade\Services\MasqueradeManager;

$manager = app(MasqueradeManager::class);

$manager->isMasquerading();
$manager->depth();
$manager->getMasquerader();
$manager->getOriginalMasquerader();
$manager->getMasqueraderId();
$manager->getOriginalMasqueraderId();
$manager->getMasqueraderGuardName();
$manager->getMasqueradeGuardName();
$manager->leave();
$manager->clear();
```

The auto-discovered facade proxies the same manager:

```php
use AgedNerd\Masquerade\Masquerade;

Masquerade::isMasquerading();
Masquerade::depth();
```

`MasqueradeManager::take()` is a low-level transition primitive and does not evaluate model authorization hooks. Prefer `$actor->masqueradeAs($subject)` or the built-in controller for user-driven actions.

## Configuration

The published `config/masquerade.php` contains:

| Key | Default | Meaning |
| --- | --- | --- |
| `session_key` | `masquerade.stack` | Session key containing the nested stack |
| `cookie_key` | `masquerade_stack` | Encrypted stack-cookie name |
| `default_guard` | `web` | Default subject guard |
| `remember` | `inherit` | `false`, `true`, or inherit from a remembered source |
| `remember_cookie_minutes` | `43200` | Stack-cookie lifetime in minutes |
| `take_redirect_to` | `/` | Default redirect after starting |
| `leave_redirect_to` | `/` | Default redirect after leaving |
| `allow_external_redirects` | `false` | Whether absolute external redirect URLs are accepted |
| `legacy_get_routes` | `false` | Enables legacy state-changing GET routes |

Keep `legacy_get_routes` disabled. GET requests should not change authentication state.

### Redirects

Redirect values may be relative paths, `back`, or Laravel route names. Invalid route names and disallowed external URLs safely fall back to `/`.

Request-specific resolver callbacks can be installed on the current manager instance:

```php
$manager->setTakeRedirectResolver(
    fn (MasqueradeManager $manager, ?string $requested): string => '/dashboard',
);

$manager->setLeaveRedirectResolver(
    fn (MasqueradeManager $manager, ?string $requested): string => '/admin/users',
);
```

## Remembered and nested masquerades

With `remember` set to `inherit`, the subject receives a normal Laravel recaller when the source was restored via remember-me or has a remember token. The nested stack is also queued in an encrypted, HTTP-only, SameSite=Lax cookie.

Cookie recovery occurs only when Laravel's recaller restores the same subject represented by the top stack frame. Clearing or leaving the final frame removes both session and cookie state.

## Blade conditions and helpers

```blade
@masquerading
    <p>You are acting as another user.</p>
@endmasquerading

@notMasquerading
    <p>You are using your own account.</p>
@endnotMasquerading

@canMasquerade
    <button>Show masquerade controls</button>
@endcanMasquerade

@canBeMasqueraded($user)
    <button>Masquerade as {{ $user->name }}</button>
@endcanBeMasqueraded
```

Equivalent helpers are available:

```php
is_masquerading();
can_masquerade();
can_be_masqueraded($user);
get_masquerader();
```

## Events and audit logging

`MasqueradeStarted` and `MasqueradeEnded` expose:

- `$masquerader`
- `$subject`
- `$sourceGuard`
- `$targetGuard`
- `$depth` after the transition

Example listener:

```php
use AgedNerd\Masquerade\Events\MasqueradeStarted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

Event::listen(MasqueradeStarted::class, function (MasqueradeStarted $event): void {
    Log::notice('Masquerade started', [
        'masquerader_id' => $event->masquerader->getAuthIdentifier(),
        'subject_id' => $event->subject->getAuthIdentifier(),
        'source_guard' => $event->sourceGuard,
        'target_guard' => $event->targetGuard,
        'depth' => $event->depth,
    ]);
});
```

## Protecting sensitive routes

Apply `masquerade.protect` to billing, credentials, destructive operations, or other sensitive routes. It returns HTTP 403 while a masquerade is active:

```php
Route::middleware('masquerade.protect')->group(function (): void {
    Route::get('/billing', BillingController::class);
});
```

Recommended safeguards:

- Audit both lifecycle events.
- Protect password, MFA, billing, API-token, and destructive routes.
- Keep external redirects and legacy GET routes disabled.
- Use short session lifetimes for privileged operators.
- Apply rate limiting and normal administrative authorization to the take route.

## Local development

Use a Composer path repository before the package is available on Packagist:

```json
{
    "repositories": [
        {"type": "path", "url": "../laravel-masquerade"}
    ],
    "require": {
        "agednerd/laravel-masquerade": "@dev"
    }
}
```

Then run:

```bash
composer update agednerd/laravel-masquerade
```

## Testing

```bash
composer install
vendor/bin/phpunit
```

The CI matrix tests PHP 8.3, 8.4, and 8.5 against the lowest and current stable Laravel 13 dependency sets.

## Maintainer release checklist

1. Update `CHANGELOG.md`, run `composer validate --strict`, and run the test suite.
2. Commit and push the release-ready source.
3. Create and push a semantic version tag, for example:

   ```bash
   git tag -a v1.0.0 -m "Release v1.0.0"
   git push origin v1.0.0
   ```

4. The `Release` GitHub Actions workflow validates the full PHP/dependency matrix, confirms that the changelog contains a dated heading matching the tag, and creates the GitHub Release with generated notes. A `v1.0.0` tag therefore requires a heading such as `## 1.0.0 - 2026-06-28`.
5. Submit `https://github.com/agednerd/laravel-masquerade` at [Packagist](https://packagist.org/packages/submit).
6. Connect Packagist to GitHub or configure its webhook so pushes and new tags are synchronized automatically.
7. Verify the release:

   ```bash
   composer show agednerd/laravel-masquerade --all
   composer require agednerd/laravel-masquerade:^1.0
   ```

Do not add a `version` field to `composer.json`; Composer derives release versions from Git tags.

## License

Laravel Masquerade is open-source software licensed under the [MIT license](LICENSE).
