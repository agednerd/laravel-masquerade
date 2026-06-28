# Laravel Masquerade: upstream audit and feature selection

Audit date: 2026-06-27. Source: `404labfr/laravel-impersonate` master at `0008a39` (1.7.8), plus all public GitHub issues and open pull requests visible on that date.

## Findings

The upstream package had already added Laravel 13 to its Composer constraint, but the implementation still supported PHP 7.2 and Laravel 6–13 in one code path. Its service provider replaced Laravel's global `session` auth driver, held the application in a singleton, and cached the manager in a controller constructor. Those choices explain recurring Sanctum/custom-guard and Octane reports. The built-in GET endpoints mutate authentication state. The bundled trait also permits every user to assume another identity by default.

## Demand-based scope

| Demand signal | Implemented response |
|---|---|
| #137 (12 +1) and #208 (4 +1): remember-me behavior | Native Laravel remembered login for the target and source; encrypted stack cookie supports session recovery |
| #147 (7 +1), #204, #230 and redirect PRs | Per-request relative redirects, route names, request-scoped resolver callbacks, and external-redirect blocking |
| #61 (6 +1), #143 and #156: API/Sanctum | Removed global auth-driver override; Sanctum SPA works through `web`; bearer-token exchange intentionally left to an explicit broker |
| #164 and PR #171: Octane | Request-scoped manager and method injection; no request/container captured in long-lived objects |
| #23/#45/#118 and PR #223: guards | Explicit source/target guard frames and correct restoration |
| #76/#180 and PR #220: Blade | Positive and negative conditionals |
| PR #196: original-actor helper | `get_masquerader()` plus immediate/original manager APIs |
| PR #238 and repeated nesting reports | Arbitrary-depth stack with one-level unwind |
| PRs #193/#206/#207 and PHP 8.4 reports | PHP 8.3+ types, Laravel 13-only dependency surface, no deprecated signatures |

## Additional hardening

- Authorization denies by default and receives both actor and target context.
- State changes use POST/DELETE by default.
- External redirect input is rejected by default.
- Persisted stack cookies are HTTP-only/SameSite and are accepted only when a matching target was restored by Laravel's recaller.
- Sensitive-route middleware returns 403 instead of redirecting back.
- Audit events contain guards and nesting depth.

## Verification

The Laravel 13/Testbench 11 suite covers normal take/leave, nesting, multi-guard restoration, remember tokens and cookie persistence, event context, redirect safety, route verbs, and authorization denial.
