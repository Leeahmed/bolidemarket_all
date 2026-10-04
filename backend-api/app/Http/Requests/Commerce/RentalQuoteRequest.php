<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;

class RentalQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->canUseCommerce();
    }

    public function rules(): array
    {
        return ['vehicle_id' => ['required', 'integer', 'min:1'], 'quote_id' => ['prohibited']] + self::periodRules();
    }

    public static function periodRules(): array
    {
        return [
            'start_date' => ['required_without_all:quote_id,starts_at', 'prohibits:starts_at,quote_id', 'date_format:Y-m-d', 'required_with:end_date'],
            'end_date' => ['required_without_all:quote_id,ends_at', 'prohibits:ends_at,quote_id', 'date_format:Y-m-d', 'required_with:start_date'],
            'starts_at' => ['required_without_all:quote_id,start_date', 'prohibits:start_date,quote_id', 'date', 'regex:/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(Z|[+-]\\d{2}:\\d{2})$/', 'required_with:ends_at'],
            'ends_at' => ['required_without_all:quote_id,end_date', 'prohibits:end_date,quote_id', 'date', 'regex:/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(Z|[+-]\\d{2}:\\d{2})$/', 'required_with:starts_at'],
        ];
    }
}
