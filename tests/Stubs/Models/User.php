<?php

namespace AgedNerd\Masquerade\Tests\Stubs\Models;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use AgedNerd\Masquerade\Concerns\Masqueradable;

class User extends Authenticatable
{
    use Notifiable, Masqueradable;

    /**
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * @return  bool
     */
    public function canMasquerade(?AuthenticatableContract $subject = null): bool
    {
        return $this->attributes['is_admin'] == 1;
    }

    /*
     * @return bool
     */
    public function canBeMasqueraded(?AuthenticatableContract $masquerader = null): bool
    {
        return $this->attributes['can_be_masqueraded'] == 1;
    }


    public function getAuthIdentifierName()
    {
        return 'email';
    }
}
