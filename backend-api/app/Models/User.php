<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Support\DemoMode;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'password', 'country_code', 'city_id', 'district_id',
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
            'password' => 'hashed',
            'role' => UserRole::class,
            'disabled_at' => 'datetime',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    public function canUseCommerce(): bool
    {
        return $this->hasVerifiedEmail() || DemoMode::enabled();
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function ownedMerchant(): HasOne
    {
        return $this->hasOne(MerchantProfile::class, 'owner_user_id');
    }

    public function merchants(): BelongsToMany
    {
        return $this->belongsToMany(MerchantProfile::class, 'merchant_memberships', 'user_id', 'merchant_id')
            ->withPivot('role')->withTimestamps();
    }
}
