<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->value === 'admin' && $this->user()?->disabled_at === null;
    }

    public function rules(): array
    {
        $type = $this->route('type');
        $rules = ['page' => 'sometimes|integer|min:1|max:100000', 'per_page' => 'sometimes|integer|min:1|max:100', 'q' => 'sometimes|string|max:120'];
        if ($type !== 'activity') {
            $rules += ['country_code' => 'sometimes|exists:countries,code', 'city_id' => 'sometimes|integer|exists:cities,id'];
        }
        $statuses = match ($type) {
            'users' => 'active,suspended', 'merchants' => 'pending,approved,rejected,suspended',
            'shops' => 'draft,published,suspended', 'vehicles' => 'available,rented,sold,other',
            'reservations' => 'pending,confirmed,active,completed,rejected,cancelled,expired',
            'orders' => 'pending,confirmed,fulfilled,cancelled', 'payments' => 'initiated,pending,paid,failed,cancelled', default => null,
        };
        if ($statuses) {
            $rules['status'] = 'sometimes|in:'.$statuses;
        }
        if ($type === 'users') {
            $rules['role'] = 'sometimes|in:customer,merchant,admin';
        }
        if (in_array($type, ['vehicles', 'reservations', 'orders', 'receipts', 'payments'])) {
            $rules['shop_id'] = 'sometimes|integer|min:1';
        }
        if (in_array($type, ['shops', 'vehicles', 'reservations', 'orders'])) {
            $rules['merchant_id'] = 'sometimes|integer|min:1';
        }
        if (in_array($type, ['reservations', 'orders', 'receipts'])) {
            $rules += ['user_id' => 'sometimes|integer|min:1', 'vehicle_id' => 'sometimes|integer|min:1'];
        }
        if ($type === 'vehicles') {
            $rules += ['publication_status' => 'sometimes|in:draft,published,archived', 'moderation_status' => 'sometimes|in:clear,review,suspended',
                'brand_id' => 'sometimes|integer|exists:brands,id', 'category_id' => 'sometimes|integer|exists:categories,id'];
        }
        if (in_array($type, ['vehicles', 'receipts'])) {
            $rules['listing_type'] = 'sometimes|in:sale,rental';
        }

        return $rules;
    }
}
