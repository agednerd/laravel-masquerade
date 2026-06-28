<?php

namespace AgedNerd\Masquerade\Middleware;

use AgedNerd\Masquerade\Services\MasqueradeManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProtectFromMasquerade
{
    public function __construct(private readonly MasqueradeManager $manager)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->manager->isMasquerading(), 403, 'This action is unavailable during a masquerade.');
        return $next($request);
    }
}
