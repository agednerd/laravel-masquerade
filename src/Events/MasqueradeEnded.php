<?php

namespace AgedNerd\Masquerade\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MasqueradeEnded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Authenticatable $masquerader,
        public readonly Authenticatable $subject,
        public readonly string $sourceGuard,
        public readonly string $targetGuard,
        public readonly int $depth,
    ) {
    }
}
