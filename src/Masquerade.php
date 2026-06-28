<?php

namespace AgedNerd\Masquerade;

use Illuminate\Support\Facades\Facade;
use AgedNerd\Masquerade\Services\MasqueradeManager;

class Masquerade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return MasqueradeManager::class;
    }
}
