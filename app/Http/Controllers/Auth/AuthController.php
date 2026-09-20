<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{

    public function __construct(private readonly OtpService $otpService) {}

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

        try {
            $this->otpService->generateAndSend($user);
        } catch (Throwable $e) {
            Log::warning('Failed to send registration OTP to {$user->phone}: {$e->gentMessage()}');
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            Log::warning("Failed to send verification email to {$user->email}: {$e->getMessage()}");
        }

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
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

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Always respond the same way whether or not the email exists,
        // so this endpoint can't be used to enumerate registered accounts.
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'success' => true,
            'message' => 'If an account exists for that email, a password reset link has been sent.',
        ], 200);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                // Invalidate existing sessions on this account after a reset.
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully. Please log in with your new password.',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => __($status),
        ], 400);
    }


    public function verifyPhone(Request $request): JsonResponse
    {
        $request->validate(['otp' => ['required', 'digits:6']]);
        $user = $request->user();

        if ($user->hasVerifiedPhone()) {
            return response()->json(['success' => true, 'message' => 'Phone number is already verified'], 200);
        }

        if ($this->otpService->verify($user, $request->otp)) {
            throw ValidationException::withMessages(['otp' => ['That code is invalid or is expired']]);
        }

        return response()->json(['success' => true, 'message' => 'Phone number verified successfully'], 200);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedPhone()) {
            return response()->json(['success' => true, 'message' => 'This phone number is already verified'], 400);
        }

        $this->otpService->generateAndSend($user);

        return response()->json(['success' => true, 'message' => 'A new verification code has been sent'], 200);
    }

    public function verifyEmail(Request $request, int $id, string $hash): View
    {
        $user = User::find($id);

        if (! $user || ! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return view('auth.email-verify-result', [
                'success' => false,
                'heading' => 'Link invalid',
                'message' => 'This verification link is invalid or has expired. Please request a new one from the app.',
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return view('auth.email-verify-result', [
                'success' => true,
                'heading' => 'Already verified',
                'message' => 'Your email is already verified. You can return to the app.',
            ]);
        }

        $user->markEmailAsVerified();

        return view('auth.email-verify-result', [
            'success' => true,
            'heading' => 'Email verified',
            'message' => 'Your email has been verified. You can return to the app and continue.',
        ]);
    }

    public function resendEmailVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Your email is already verified.',
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent. Please check your inbox.',
        ], 200);
    }
}
