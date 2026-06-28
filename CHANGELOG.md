# Laravel Masquerade changelog

## 1.0.0 - 2026-06-28

- Renamed the package to `agednerd/laravel-masquerade` with the `AgedNerd\\Masquerade` namespace.
- Renamed the model trait to `AgedNerd\\Masquerade\\Concerns\\Masqueradable`.
- Laravel 13 and PHP 8.3 baseline.
- Secure deny-by-default authorization and POST/DELETE routes.
- Nested, remembered, multi-guard masquerades.
- Adopted Masquerade terminology across the public API, events, helpers, Blade directives, middleware, and documentation.
- Octane-safe request-scoped services.
- Dynamic safe redirects, richer events, helpers, and Blade conditions.
- Removed the global session-driver override.
