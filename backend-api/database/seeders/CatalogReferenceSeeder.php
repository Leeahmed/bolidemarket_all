<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Feature;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogReferenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            CategoryType::SUV->value => 'SUV', CategoryType::SEDAN->value => 'Berline',
            CategoryType::CITY->value => 'Citadine', CategoryType::FOUR_BY_FOUR->value => '4×4',
            CategoryType::SPORT->value => 'Sport', CategoryType::LUXURY->value => 'Luxe', CategoryType::UTILITY->value => 'Utilitaire',
        ] as $slug => $label) {
            Category::firstOrCreate(['slug' => $slug], ['label' => $label]);
        }
        foreach (['Toyota' => ['RAV4', 'Corolla', 'Land Cruiser'], 'Peugeot' => ['208', '308'],
            'Mercedes-Benz' => ['C300', 'Classe S'], 'Renault' => ['Kangoo', 'Clio', 'Zoe'],
            'Tesla' => ['Model 3'], 'Kia' => ['Sportage'], 'Volkswagen' => ['Golf'],
            'BMW' => ['Série 3'], 'Ford' => ['Transit'], 'Hyundai' => ['Tucson'],
            'Mini' => ['Cooper'], 'Porsche' => ['911']] as $brandName => $models) {
            $brand = Brand::firstOrCreate(['slug' => Str::slug($brandName)], ['name' => $brandName]);
            foreach ($models as $name) {
                VehicleModel::firstOrCreate(['brand_id' => $brand->id, 'name' => $name]);
            }
        }
        foreach (['Climatisation', 'Bluetooth', 'Caméra de recul', 'GPS', 'Toit panoramique', 'Sièges cuir', 'CarPlay', 'Android Auto'] as $label) {
            Feature::firstOrCreate(['slug' => Str::slug($label)], ['label' => $label]);
        }
    }
}
