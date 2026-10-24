<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'seller_type',
        'city',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'seller_onboarded_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null && is_string($this->phone)
            && preg_match('/^\+998[0-9]{9}$/D', $this->phone) === 1;
    }

    public function hasCompletedSellerProfile(): bool
    {
        return $this->hasVerifiedPhone() && $this->seller_onboarded_at !== null
            && in_array($this->seller_type, ['individual', 'shop'], true)
            && filled($this->name) && filled($this->city);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(VerificationRecord::class);
    }
}
