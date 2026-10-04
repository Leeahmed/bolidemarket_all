<?php

namespace App\Http\Controllers;

use App\Actions\Auth\RegisterMerchant;
use App\Http\Requests\Auth\MerchantRegisterRequest;
use App\Http\Resources\UserResource;

class MerchantRegistrationController extends Controller
{
    public function store(MerchantRegisterRequest $request, RegisterMerchant $action)
    {
        return (new UserResource($action->handle($request->validated())))->response()->setStatusCode(201);
    }
}
