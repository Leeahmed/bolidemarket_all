<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\PhoneNumbers;
use App\Services\ProfileImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function update(ProfileRequest $request): UserResource
    {
        $data = $request->validated();
        $data['phone'] = app(PhoneNumbers::class)->normalize($data['phone'], $data['country_code']);
        $data += ['city_id' => null, 'district_id' => null];
        $user = DB::transaction(function () use ($request, $data) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            $user->fill($data)->save();

            return $user;
        });

        return new UserResource($user);
    }

    public function avatar(Request $request, ProfileImages $images): UserResource
    {
        $request->validate(['avatar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:max_width=4096,max_height=4096']]);

        return new UserResource($images->avatar($request->user(), $request->file('avatar')));
    }
}
