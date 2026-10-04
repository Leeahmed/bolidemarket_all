<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return response()->json(['data' => ['message' => 'Demande prise en compte.']]);
    }

    public function verify(EmailVerificationRequest $request): JsonResponse
    {
        $request->fulfill();

        return response()->json(['data' => ['message' => 'Adresse e-mail vérifiée.']]);
    }
}
