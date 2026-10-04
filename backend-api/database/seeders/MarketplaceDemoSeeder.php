<?php

namespace Database\Seeders;

use App\Actions\Auth\CreateMerchantProfile;
use App\Enums\MerchantApproval;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\City;
use App\Models\District;
use App\Models\Feature;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MarketplaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Dataset démo interdit hors local/testing.');
        }
        $this->call([LocationSeeder::class, CatalogReferenceSeeder::class, DemoAccountsSeeder::class]);
        DB::transaction(function () {
            $shops = [];
            foreach (['Abidjan Prestige Motors', 'Cocody Auto Selection', 'Lagune Rent Cars', 'Ivoire Premium Auto', 'Paris Mobilité Démo'] as $index => $name) {
                $email = $index === 0 ? 'merchant@bolidemarket.demo' : 'merchant'.($index + 1).'@bolidemarket.demo';
                $owner = User::where('email', $email)->first();
                if (! $owner) {
                    $owner = new User(['first_name' => 'Professionnel', 'last_name' => 'Démo '.($index + 1),
                        'email' => $email, 'phone' => '+2250700000000', 'password' => 'password']);
                    $owner->role = UserRole::MERCHANT;
                    $owner->email_verified_at = now();
                    $owner->save();
                }
                if ($owner->role !== UserRole::MERCHANT) {
                    throw new RuntimeException('Compte démo incompatible.');
                }
                $owner->email_verified_at ??= now();
                $owner->save();
                $merchant = $owner->ownedMerchant ?? app(CreateMerchantProfile::class)->handle($owner, $name, $name);
                // Only these explicitly local demo identities are approved; never arbitrary merchants.
                $merchant->approval_status = MerchantApproval::APPROVED;
                $merchant->save();
                $country = $index === 4 ? 'FR' : 'CI';
                $city = City::where('country_code', $country)->where('slug', $index === 4 ? 'paris' : 'abidjan')->sole();
                $shop = Shop::withTrashed()->where('slug', Str::slug($name).'-demo')->first();
                if (! $shop) {
                    $shop = new Shop;
                    $shop->forceFill(['merchant_id' => $merchant->id, 'name' => $name, 'slug' => Str::slug($name).'-demo',
                        'description' => 'Boutique fictive — données de démonstration.', 'address' => 'Adresse de démonstration',
                        'country_code' => $country, 'city_id' => $city->id,
                        'district_id' => $country === 'CI' ? District::where('city_id', $city->id)->where('slug', $index === 2 ? 'marcory' : 'cocody')->value('id') : null,
                        'currency_code' => $country === 'CI' ? 'XOF' : 'EUR', 'timezone' => $country === 'CI' ? 'Africa/Abidjan' : 'Europe/Paris',
                        'status' => 'published', 'is_demo' => true])->save();
                }
                $shops[] = $shop;
            }
            $rows = [
                ['RAV4', 'suv', 'petrol', 'Blanc', 18500000, null],
                ['208', 'city', 'petrol', 'Rouge', null, 45000],
                ['C300', 'luxury', 'petrol', 'Champagne', 28900000, null],
                ['Kangoo', 'utility', 'diesel', 'Blanc', null, 35000],
                ['Model 3', 'sedan', 'electric', 'Bleu', 2590000, null],
                ['Sportage', 'suv', 'hybrid', 'Vert', 22500000, null],
                ['Corolla', 'sedan', 'hybrid', 'Bleu', 14000000, null],
                ['Clio', 'city', 'petrol', 'Orange', 8800000, 30000],
                ['Land Cruiser', 'four_by_four', 'diesel', 'Sable', null, 95000],
                ['Golf', 'city', 'petrol', 'Rouge', 1690000, null],
                ['Classe S', 'luxury', 'hybrid', 'Blanc', 42000000, null],
                ['Transit', 'utility', 'diesel', 'Blanc', null, 55000],
                ['Tucson', 'suv', 'hybrid', 'Bleu', 23000000, null],
                ['Cooper', 'city', 'petrol', 'Vert', 11000000, null],
                ['Zoe', 'city', 'electric', 'Bleu', null, 6500],
                ['Série 3', 'sedan', 'diesel', 'Bordeaux', 19500000, null],
                ['911', 'sport', 'petrol', 'Jaune', 55000000, null],
                ['308', 'sedan', 'diesel', 'Bleu', 9800000, null],
            ];
            foreach ($rows as $i => [$modelName, $category, $fuel, $color, $sale, $rent]) {
                $reference = 'BM-'.$shops[$i % 5]->country_code.'-DEMO-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
                if (Vehicle::withTrashed()->where('reference', $reference)->exists()) {
                    continue;
                }
                $shop = $shops[$i % 5];
                $model = VehicleModel::with('brand')->where('name', $modelName)->sole();
                $vehicle = new Vehicle;
                $vehicle->forceFill([
                    'shop_id' => $shop->id, 'reference' => $reference,
                    'slug' => Str::slug($model->brand->name.'-'.$modelName.'-2024-'.$reference),
                    'vehicle_model_id' => $model->id, 'category_id' => Category::where('slug', $category)->value('id'),
                    'title' => $model->brand->name.' '.$modelName, 'year' => 2024, 'condition' => 'used',
                    'is_for_sale' => $sale !== null, 'is_for_rent' => $rent !== null,
                    'sale_price_minor' => $sale, 'rent_daily_minor' => $rent, 'currency_code' => $shop->currency_code,
                    'mileage_km' => 10000 + $i * 1500, 'fuel' => $fuel, 'transmission' => 'automatic',
                    'doors' => 5, 'seats' => 5, 'color' => $color, 'description' => 'Véhicule fictif. Photos et prix de démonstration.',
                    'country_code' => $shop->country_code, 'city_id' => $shop->city_id, 'district_id' => $shop->district_id,
                    'publication_status' => $i === 17 ? 'draft' : 'published', 'published_at' => $i === 17 ? null : now(),
                    'inventory_status' => match ($i) {
                        15 => 'sold', 16 => 'rented', 13 => 'other', default => 'available'
                    },
                    'is_demo' => true,
                ])->save();
                $vehicle->features()->sync(Feature::orderBy('id')->limit($i % 3 + 2)->pluck('id'));
                $key = 'vehicles/'.$vehicle->id.'/demo-placeholder.svg';
                // Original local SVG placeholder; never a downloaded vehicle photograph.
                Storage::disk('public')->put($key, '<svg xmlns="http://www.w3.org/2000/svg" width="960" height="600" viewBox="0 0 960 600"><rect width="960" height="600" fill="#25282D"/><rect x="48" y="48" width="864" height="504" rx="16" fill="#0B0D0F" stroke="#555"/><text x="480" y="280" fill="#F5F3EE" font-family="Arial,sans-serif" font-size="32" text-anchor="middle">PHOTO À VENIR</text><text x="480" y="340" fill="#F5F3EE" font-family="Arial,sans-serif" font-size="20" text-anchor="middle">DONNÉES DE DÉMONSTRATION</text></svg>');
                $vehicle->images()->create(['storage_key' => $key, 'position' => 0, 'alt_text' => 'Placeholder de démonstration, aucune photographie disponible.', 'is_placeholder' => true]);
            }
        });
    }
}
