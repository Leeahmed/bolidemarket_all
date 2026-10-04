<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\District;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['XOF' => 0, 'EUR' => 2, 'CAD' => 2, 'USD' => 2] as $code => $unit) {
            Currency::firstOrCreate(['code' => $code], ['minor_unit' => $unit, 'active' => true]);
        }
        $countries = [
            ['CI', 'Côte d’Ivoire', 'XOF', 'FCFA', '+225', ['Abidjan', 'Bouaké', 'Yamoussoukro', 'San-Pédro']],
            ['FR', 'France', 'EUR', '€', '+33', ['Paris', 'Lyon', 'Marseille', 'Nice', 'Bordeaux']],
            ['SN', 'Sénégal', 'XOF', 'FCFA', '+221', ['Dakar']],
            ['BE', 'Belgique', 'EUR', '€', '+32', ['Bruxelles']],
            ['US', 'États-Unis', 'USD', '$', '+1', ['New York', 'Los Angeles']],
            ['CA', 'Canada', 'CAD', 'CA$', '+1', ['Montréal']],
        ];
        foreach ($countries as [$code, $name, $currency, $symbol, $phone, $cities]) {
            Country::firstOrCreate(['code' => $code], ['name' => $name, 'currency_code' => $currency, 'currency_symbol' => $symbol, 'phone_code' => $phone, 'active' => true]);
            foreach ($cities as $city) {
                City::firstOrCreate(['country_code' => $code, 'slug' => Str::slug($city)], ['name' => $city, 'active' => true]);
            }
        }
        $abidjan = City::where('country_code', 'CI')->where('slug', 'abidjan')->sole();
        foreach (['Cocody', 'Marcory', 'Treichville', 'Plateau', 'Yopougon', 'Koumassi', 'Port-Bouët', 'Abobo', 'Bingerville'] as $name) {
            District::firstOrCreate(['city_id' => $abidjan->id, 'slug' => Str::slug($name)], ['name' => $name, 'active' => true]);
        }
    }
}
