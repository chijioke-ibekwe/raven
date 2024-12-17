<?php

namespace ChijiokeIbekwe\Raven\Tests\Utilities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'password',
    ];

    public function routeNotificationForVonage()
    {
        return $this->phone_number;
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
