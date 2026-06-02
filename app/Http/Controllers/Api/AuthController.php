<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\MagicLinkRequest;
use App\Http\Requests\Api\VerifyMagicLinkRequest;
use App\Http\Resources\UserResource;
use App\Mail\MagicLinkMail;
use App\Models\MagicLinkToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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

        $user->load('club', 'managedTeams', 'member.teams');
        $token = $user->createToken('flutterflow-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token'      => $token,
                'token_type' => 'Bearer',
                'user'       => new UserResource($user),
            ],
            'message' => 'Succesvol ingelogd.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'data'    => null,
            'message' => 'Succesvol uitgelogd.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('club', 'managedTeams', 'member.teams');

        return response()->json([
            'success' => true,
            'data'    => new UserResource($user),
            'message' => '',
        ]);
    }

    /**
     * Send a magic login link to the given email address.
     *
     * POST /api/v1/auth/magic-link
     * Body: { "email": "user@example.com" }
     *
     * Always returns 200 — we never reveal whether the email exists.
     */
    public function sendMagicLink(MagicLinkRequest $request): JsonResponse
    {
        $email  = strtolower($request->validated('email'));
        $user   = User::where('email', $email)->where('is_active', true)->first();
        $member = \App\Models\Member::where('email', $email)->where('is_active', true)->first();

        \Log::info('[MagicLink] sendMagicLink called', [
            'email'        => $email,
            'user_found'   => (bool) $user,
            'member_found' => (bool) $member,
        ]);

        if ($user || $member) {
            MagicLinkToken::where('email', $email)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->delete();

            $token = Str::random(64);

            MagicLinkToken::create([
                'email'      => $email,
                'token'      => $token,
                'expires_at' => now()->addMinutes(30),
            ]);

            $name  = $user?->name ?? $member?->name ?? 'Lid';
            $club  = $user?->club
                ?? ($member ? \App\Models\Club::find($member->teams()->first()?->club_id) : null);

            try {
                Mail::to($email)->send(new MagicLinkMail($token, $name, $club));
                \Log::info('[MagicLink] mail sent', ['email' => $email]);
            } catch (\Throwable $e) {
                \Log::error('[MagicLink] mail failed', ['email' => $email, 'error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'data'    => null,
            'message' => 'Als dit e-mailadres bekend is, ontvang je een inloglink.',
        ]);
    }

    /**
     * Verify a magic link token and return a long-lived Sanctum token.
     *
     * POST /api/v1/auth/verify-magic-link
     * Body: { "token": "<64-char token>" }
     */
    public function verifyMagicLink(VerifyMagicLinkRequest $request): JsonResponse
    {
        $record = MagicLinkToken::where('token', $request->validated('token'))->first();

        if (!$record || !$record->isValid()) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Deze inloglink is ongeldig of verlopen.',
            ], 401);
        }

        $user = User::where('email', $record->email)->where('is_active', true)->first();

        if (!$user) {
            // No user account yet — try to find an active member and auto-create one.
            $member = \App\Models\Member::where('email', $record->email)
                ->where('is_active', true)
                ->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'data'    => null,
                    'message' => 'Geen actief account gevonden.',
                ], 401);
            }

            $clubId = $member->teams()->first()?->club_id;

            $user = User::create([
                'name'      => $member->name,
                'email'     => $member->email,
                'phone'     => $member->phone,
                'is_active' => true,
                'club_id'   => $clubId,
                'password'  => Str::random(32),
            ]);

            $member->update(['user_id' => $user->id]);

            \Log::info('[MagicLink] auto-created user from member', [
                'email'   => $record->email,
                'user_id' => $user->id,
            ]);
        }

        // Mark token as used — single-use only.
        $record->update(['used_at' => now()]);

        $user->load('club', 'managedTeams', 'member.teams');

        // Long-lived token: 90 days.
        $sanctumToken = $user->createToken(
            'magic-link',
            ['*'],
            now()->addDays(90),
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => [
                'token'      => $sanctumToken,
                'token_type' => 'Bearer',
                'expires_in' => 90 * 24 * 60 * 60,
                'user'       => new UserResource($user),
            ],
            'message' => 'Succesvol ingelogd.',
        ]);
    }
}
