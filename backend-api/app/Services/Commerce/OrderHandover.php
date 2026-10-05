<?php

namespace App\Services\Commerce;

use App\Models\User;
use App\Models\Vehicle;
use App\Services\PhoneNumbers;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrderHandover
{
    public function validate(array $input, User $user, Vehicle $vehicle): array
    {
        $data = Validator::make($input, [
            'mode' => 'required|in:self,proxy,delivery', 'scheduled_local' => 'required|date_format:Y-m-d\\TH:i',
            'contact_name' => 'required|string|max:120', 'contact_phone' => 'required|string|max:40',
            'address' => 'required_if:mode,delivery|nullable|string|max:500', 'city' => 'required_if:mode,delivery|nullable|string|max:120',
            'latitude' => 'nullable|required_with:longitude|numeric|between:-90,90', 'longitude' => 'nullable|required_with:latitude|numeric|between:-180,180',
            'notes' => 'nullable|string|max:1000',
        ])->validate();
        $at = CarbonImmutable::createFromFormat('!Y-m-d\\TH:i', $data['scheduled_local'], $vehicle->shop->timezone);
        if (! $at->isFuture()) {
            throw ValidationException::withMessages(['handover.scheduled_local' => ['Choisissez une date et une heure approximative futures.']]);
        }
        $phone = app(PhoneNumbers::class)->normalize($data['contact_phone'], str_starts_with($data['contact_phone'], '+') ? null : $user->country_code);
        if (! $phone) {
            throw ValidationException::withMessages(['handover.contact_phone' => ['Renseignez un numéro de téléphone valide avec son indicatif.']]);
        }

        return ['mode' => $data['mode'], 'scheduled_at' => $at->utc()->toISOString(), 'timezone' => $vehicle->shop->timezone, 'contact_name' => $data['contact_name'], 'contact_phone' => $phone,
            'address' => $data['mode'] === 'delivery' ? ($data['address'] ?? null) : null, 'city' => $data['mode'] === 'delivery' ? ($data['city'] ?? null) : null,
            'latitude' => $data['mode'] === 'delivery' ? ($data['latitude'] ?? null) : null, 'longitude' => $data['mode'] === 'delivery' ? ($data['longitude'] ?? null) : null, 'notes' => $data['notes'] ?? null];
    }
}
