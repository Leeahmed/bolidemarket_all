<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->value === 'admin' && $this->user()?->disabled_at === null;
    }

    public function rules(): array
    {
        $actions = match ($this->route('type')) {
            'users', 'shops' => 'suspend,reactivate', 'merchants' => 'approve,suspend,reactivate',
            'vehicles' => 'unpublish,publish,suspend,review',
        };

        return ['action' => 'required|in:'.$actions, 'reason' => 'required|string|min:5|max:500'];
    }

    public function after(): array
    {
        return [function ($validator) {
            foreach (array_diff(array_keys($this->all()), ['action', 'reason']) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être modifié depuis l’administration.');
            }
        }];
    }
}
