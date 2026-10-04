<?php

namespace App\Services;

use App\Models\City;
use App\Models\District;

final readonly class LocationContext
{
    public function __construct(
        public ?string $countryCode,
        public ?int $cityId,
        public ?int $districtId,
        public ?float $latitude,
        public ?float $longitude,
    ) {}

    public static function fromFilters(array $filters): self
    {
        $district = isset($filters['district_id']) ? District::find($filters['district_id']) : null;
        $cityId = $filters['city_id'] ?? $district?->city_id;
        $city = $cityId ? City::find($cityId) : null;

        return new self($filters['country_code'] ?? $city?->country_code, $city?->id, $district?->id,
            isset($filters['latitude']) ? (float) $filters['latitude'] : null,
            isset($filters['longitude']) ? (float) $filters['longitude'] : null);
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
