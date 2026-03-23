<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {


        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'user_type' => $request->user_type,
            'phone' => $request->phone,
            'is_active' => true,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;


        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);

    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provide credentials are incorrect'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact support.',
            ], 403);
        }

        $user->update([
            'last_active_at' => now(),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);

    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ], 200);

    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new UserResource($request->user()),
        ], 200);
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        $token = $request->user()->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);

    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users']);

        $status = Password::sendResetLink($request->only('email'));

        return $status=== Password::RESET_LINK_SENT ? response()->json(['message' => 'Link sent'], 200) :  response()->json(['message' => 'Could not send link'], 400);


    }

    public function resetPassword(Request $request)
    {
        $request->validate(['']);

        $status = Password::reset(
            $request->only('email','password','password_confirmation','token'), function($user,$password){
                $user->forceFill([ 'password' => bcrypt('password')])->save();
            }
        );

        return response()->json(['message' => $status]);
    }
}
