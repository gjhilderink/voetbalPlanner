<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['De opgegeven inloggegevens zijn onjuist.'],
            ]);
        }

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            return response()->json([
                'success' => false,
                'message' => 'Uw account is gedeactiveerd.',
            ], 403);
        }

        $user->load('club', 'managedTeams');
        $token = $user->createToken('flutterflow-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
            'message' => 'Succesvol ingelogd.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Succesvol uitgelogd.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('club', 'managedTeams');

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
            'message' => '',
        ]);
    }
}
