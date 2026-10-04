<?php

namespace App\Models;

use App\Enums\MerchantApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MerchantProfile extends Model
{
    protected $fillable = ['legal_name', 'display_name'];

    protected function casts(): array
    {
        return ['approval_status' => MerchantApproval::class, 'verified_at' => 'datetime'];
    }

    public function shops()
    {
        return $this->hasMany(Shop::class, 'merchant_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'merchant_memberships', 'merchant_id', 'user_id')
            ->withPivot('role')->withTimestamps();
    }
}
