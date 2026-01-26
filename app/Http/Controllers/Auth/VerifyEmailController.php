<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    protected EmailVerificationService $emailVerificationService;

    public function __construct(EmailVerificationService $emailVerificationService)
    {
        $this->emailVerificationService = $emailVerificationService;
    }

    /**
     * Verify email by token
     */
    public function verifyByToken(Request $request)
    {
        Log::info('=== VERIFY BY TOKEN CALLED ===');
        Log::info('Request data:', $request->all());

        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string'
            ]);

            if ($validator->fails()) {
                Log::warning('Token validation failed:', ['errors' => $validator->errors()->toArray()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid request data',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->emailVerificationService->verifyByToken(
                $request->input('token')
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'user' => [
                        'id' => $result['user']->id,
                        'first_name' => $result['user']->first_name,
                        'last_name' => $result['user']->last_name,
                        'email' => $result['user']->email,
                        'email_verified_at' => $result['user']->email_verified_at,
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);

        } catch (\Exception $e) {
            Log::error('Token verification failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Verification failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Verify email by code
     */
    public function verifyByCode(Request $request)
    {

        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer',
                'email' => 'required|email',
                'code' => 'required|string|size:6'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid request data',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->emailVerificationService->verifyByCode(
                $request->input('user_id'),
                $request->input('email'),
                $request->input('code')
            );
            

            if ($result['success']) {
                
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'user' => [
                        'id' => $result['user']->id,
                        'first_name' => $result['user']->first_name,
                        'last_name' => $result['user']->last_name,
                        'email' => $result['user']->email,
                        'email_verified_at' => $result['user']->email_verified_at,
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Verification failed'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Verify by code exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Verification failed. Please try again.',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Resend verification email
     */
    public function resendVerification(Request $request)
    {
        Log::info('=== RESEND VERIFICATION CALLED ===');
        Log::info('Request data:', $request->all());

        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer',
                'email' => 'required|email'
            ]);

            if ($validator->fails()) {
                Log::warning('Resend validation failed:', ['errors' => $validator->errors()->toArray()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid request data',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->emailVerificationService->resendVerification(
                $request->input('user_id'),
                $request->input('email')
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'New verification email sent!',
                    'verification' => [
                        'id' => $result['verification']->id ?? null,
                        'method' => $result['verification']->method ?? null,
                        'expires_at' => $result['verification']->expires_at ?? null,
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to resend verification'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Resend verification failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend verification email. Please try again.',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Check verification status
     */
    public function verificationStatus($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $result = $this->emailVerificationService->checkVerificationStatus(
                $user->id,
                $user->email
            );

            return response()->json([
                'success' => true,
                'has_pending' => $result['has_pending'],
                'verified' => $result['verified'],
                'expired' => $result['expired'],
                'expires_at' => $result['expires_at'] ?? null,
                'method' => $result['method'] ?? null,
                'created_at' => $result['created_at'] ?? null,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'is_active' => $user->is_active,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Verification status check failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to check verification status'
            ], 500);
        }
    }

    /**
     * Original Laravel email verification (link-based)
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended('/home?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended('/home?verified=1');
    }
}