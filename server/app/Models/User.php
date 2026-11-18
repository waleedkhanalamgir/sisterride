<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_PASSENGER = 'passenger';
    public const ROLE_PROVIDER = 'provider';
    public const ROLE_ADMIN = 'admin';

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'gender', 'role', 'active_role',
        'provider_type', 'verification_status', 'trust_score', 'is_suspended',
        'language', 'fcm_token',
    ];

    protected $hidden = ['password', 'remember_token', 'fcm_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_suspended' => 'boolean',
            'trust_score' => 'decimal:2',
        ];
    }

    public function verification(): HasOne
    {
        return $this->hasOne(Verification::class)->latestOfMany();
    }

    public function vehicle(): HasOne
    {
        return $this->hasOne(Vehicle::class)->latestOfMany();
    }

    public function trustedContacts(): HasMany
    {
        return $this->hasMany(TrustedContact::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function isApproved(): bool
    {
        return $this->verification_status === 'approved';
    }

    public function isProvider(): bool
    {
        return $this->role === self::ROLE_PROVIDER;
    }
}
