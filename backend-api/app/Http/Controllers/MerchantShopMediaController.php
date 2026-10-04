<?php

namespace App\Http\Controllers;

use App\Http\Resources\ShopResource;
use App\Models\Shop;
use App\Services\ProfileImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class MerchantShopMediaController extends Controller
{
    public function store(Request $request, Shop $shop, ProfileImages $images)
    {
        Gate::authorize('update', $shop);
        $data = $request->validate(['kind' => ['required', Rule::in(['logo', 'cover'])],
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:max_width=4096,max_height=4096']]);
        $key = $images->store($request->file('image'), 'shops/'.$shop->id);
        try {
            DB::transaction(function () use ($shop, $data, $key) {
                $record = Shop::lockForUpdate()->findOrFail($shop->id);
                Gate::authorize('update', $record);
                $column = $data['kind'].'_path';
                $old = $record->$column;
                $record->$column = $key;
                $record->save();
                if ($old) {
                    DB::afterCommit(function () use ($old) {
                        try {
                            Storage::disk('public')->delete($old);
                        } catch (Throwable $e) {
                            report($e);
                        }
                    });
                }
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($key);
            throw $e;
        }

        return new ShopResource($shop->fresh()->load(Shop::PUBLIC_RELATIONS));
    }
}
