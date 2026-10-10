<?php

namespace Database\Seeders;

use App\Actions\Auth\CreateMerchantProfile;
use App\Models\Category;
use App\Models\City;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

// Explicit, additive QA fixture. Never reset existing moderation or transaction records.
class AdminQaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('QA Admin interdite hors local/testing.');
        }
        DB::transaction(function () {
            $user = User::where('email', 'admin-qa-pro@bolidemarket.demo')->first();
            if (! $user) {
                $user = User::create(['first_name' => 'Professionnel', 'last_name' => 'QA Admin', 'email' => 'admin-qa-pro@bolidemarket.demo', 'phone' => '+2250700000000', 'country_code' => 'CI', 'password' => 'password']);
                $user->forceFill(['email_verified_at' => now()])->save();
                app(CreateMerchantProfile::class)->handle($user, 'Organisation QA Admin', 'Professionnel QA Admin');
            }
            $merchant = $user->ownedMerchant()->firstOrFail();
            $shop = Shop::where('slug', 'admin-qa-boutique')->first();
            if (! $shop) {
                $shop = new Shop;
                $shop->forceFill(['slug' => 'admin-qa-boutique', 'merchant_id' => $merchant->id, 'name' => 'Boutique QA Admin', 'country_code' => 'CI', 'city_id' => City::where('slug', 'abidjan')->firstOrFail()->id, 'currency_code' => 'XOF', 'timezone' => 'Africa/Abidjan', 'address' => 'Adresse fictive — QA Admin', 'status' => 'published', 'is_demo' => true])->save();
            }
            if (! Vehicle::withTrashed()->where('reference', 'BM-ADMIN-QA-RAV4')->exists()) {
                $v = new Vehicle;
                $v->forceFill(['shop_id' => $shop->id, 'reference' => 'BM-ADMIN-QA-RAV4', 'slug' => 'admin-qa-rav4', 'title' => 'Toyota RAV4 · QA Admin', 'vehicle_model_id' => VehicleModel::where('name', 'RAV4')->firstOrFail()->id, 'category_id' => Category::where('slug', 'suv')->firstOrFail()->id, 'year' => 2024, 'condition' => 'used', 'fuel' => 'petrol', 'transmission' => 'automatic', 'description' => 'Annonce exclusivement destinée aux contrôles de modération. Données fictives.', 'is_for_sale' => true, 'is_for_rent' => false, 'sale_price_minor' => 18500000, 'currency_code' => 'XOF', 'country_code' => 'CI', 'city_id' => $shop->city_id, 'publication_status' => 'draft', 'inventory_status' => 'available', 'is_demo' => true, 'version' => 1])->save();
                $key = 'vehicles/admin-qa-rav4.webp';
                Storage::disk('public')->put($key, file_get_contents(base_path('../merchant-dashboard/public/images/vehicle-rav4.webp')));
                $v->images()->create(['storage_key' => $key, 'position' => 0, 'alt_text' => 'Toyota RAV4 — photographie officielle de démonstration', 'is_placeholder' => false]);
            }
        });
    }
}
