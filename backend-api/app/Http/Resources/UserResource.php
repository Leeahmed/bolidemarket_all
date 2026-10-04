<?php

namespace App\Http\Resources;

use App\Support\DemoMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country_code' => $this->country_code,
            'country' => $this->country?->only(['code', 'name', 'currency_code', 'currency_symbol']),
            'city_id' => $this->city_id === null ? null : (string) $this->city_id, 'city' => $this->city ? ['id' => (string) $this->city->id, 'name' => $this->city->name] : null,
            'district_id' => $this->district_id === null ? null : (string) $this->district_id, 'district' => $this->district ? ['id' => (string) $this->district->id, 'name' => $this->district->name] : null,
            'avatar_url' => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
            'demo_mode' => DemoMode::enabled(),
            'email_verification_required' => ! $this->canUseCommerce(),
            'merchant' => $this->ownedMerchant ? ['id' => (string) $this->ownedMerchant->id, 'approval_status' => $this->ownedMerchant->approval_status->value, 'shops' => $this->ownedMerchant->shops->map(fn ($s) => ['id' => (string) $s->id, 'name' => $s->name, 'country_code' => $s->country_code, 'currency_code' => $s->currency_code, 'status' => $s->status->value])] : null,
            'role' => $this->role->value,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
