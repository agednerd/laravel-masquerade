<?php

return [
    'session_key' => 'masquerade.stack',
    'cookie_key' => 'masquerade_stack',
    'default_guard' => 'web',

    // false, true, or "inherit" (remember the target when the source has a token).
    'remember' => 'inherit',
    'remember_cookie_minutes' => 43_200,

    'take_redirect_to' => '/',
    'leave_redirect_to' => '/',
    'allow_external_redirects' => false,

    // GET state-changing routes are deliberately disabled. Enable only for legacy apps.
    'legacy_get_routes' => false,
];
