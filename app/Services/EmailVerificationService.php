<?php

namespace App\Services;

use App\Models\User;
use App\Models\EmailVerification;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EmailVerificationService
{
    protected $verificationMethod = 'both';
    protected $expirationMinutes = 15;
    
    public function __construct($method = 'both')
    {
        $this->verificationMethod = $method;
    }
    
    /**
     * Send email verification to user
     */
    public function sendVerification(User $user, $method = null)
    {
        $method = $method ?? $this->verificationMethod;
        
        // Clean up old verifications for this user
        $this->cleanupOldVerifications($user->id);
        
        // Generate verification data
        $verificationData = $this->generateVerificationData($user, $method);
        $verification = EmailVerification::create($verificationData);
        
        // Send the email
        $this->sendVerificationEmail($user, $verification, $method);
        
        return $verification;
    }
    
    /**
     * Generate verification data based on method
     */
    private function generateVerificationData(User $user, string $method): array
    {
        $data = [
            'user_id' => $user->id,
            'email' => $user->email,
            'expires_at' => Carbon::now()->addMinutes($this->expirationMinutes),
            'method' => $method === 'both' ? 'code' : $method,
        ];
        
        if ($method === 'code' || $method === 'both') {
            $data['code'] = $this->generateVerificationCode();
        }
        
        if ($method === 'link' || $method === 'both') {
            $data['token'] = Str::random(60);
        }
        
        return $data;
    }
    
    /**
     * Generate a 6-digit verification code
     */
    private function generateVerificationCode(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Send the actual verification email
     */
    private function sendVerificationEmail(User $user, EmailVerification $verification, string $method): void
    {
        try {
            switch ($method) {
                case 'both':
                    $this->sendCombinedVerificationEmail($user, $verification);
                    break;
                    
                case 'code':
                    $this->sendCodeOnlyEmail($user, $verification);
                    break;
                    
                case 'link':
                    $this->sendLinkOnlyEmail($user, $verification);
                    break;
            }
            
            Log::info('Verification email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'method' => $method
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send verification email: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'email' => $user->email,
                'method' => $method,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Send email with both code and link
     */
    private function sendCombinedVerificationEmail(User $user, EmailVerification $verification): void
    {
        $verificationLink = url('/verify-email/' . $verification->token);
        $frontendVerificationUrl = config('app.frontend_url') . '/verify-email';
        
        Mail::to($user->email)->send(new \App\Mail\EmailVerificationMail(
            $verification->code,
            $verificationLink,
            $frontendVerificationUrl,
            $user,
            $verification->expires_at
        ));
    }
    
    /**
     * Send email with only verification code
     */
    private function sendCodeOnlyEmail(User $user, EmailVerification $verification): void
    {
        $frontendVerificationUrl = config('app.frontend_url') . '/verify-email';
        
        Mail::to($user->email)->send(new \App\Mail\EmailVerificationCodeMail(
            $verification->code,
            $frontendVerificationUrl,
            $user,
            $verification->expires_at
        ));
    }
    
    /**
     * Send email with only verification link
     */
    private function sendLinkOnlyEmail(User $user, EmailVerification $verification): void
    {
        $verificationLink = url('/verify-email/' . $verification->token);
        
        Mail::to($user->email)->send(new \App\Mail\EmailVerificationLinkMail(
            $verificationLink,
            $user,
            $verification->expires_at
        ));
    }
    
    /**
     * Clean up old verifications for a user
     */
    private function cleanupOldVerifications(int $userId): void
    {
        EmailVerification::where('user_id', $userId)->delete();
    }
    
    /**
     * Verify user by code
     */
    public function verifyByCode(int $userId, string $email, string $code)
    {
        
        $verification = EmailVerification::where('user_id', $userId)
            ->where('email', $email)
            ->where('code', $code)
            ->first();
        
        return $this->processVerification($verification);
    }
    
    /**
     * Verify user by token
     */
    public function verifyByToken(string $token)
    {
        Log::info('EmailVerificationService::verifyByToken called', ['token' => $token]);
        
        $verification = EmailVerification::where('token', $token)->first();
        
        return $this->processVerification($verification);
    }
    
    /**
     * Resend verification email
     */
    public function resendVerification(int $userId, string $email, string $method = 'both')
    {
        Log::info('EmailVerificationService::resendVerification called', [
            'userId' => $userId,
            'email' => $email,
            'method' => $method
        ]);
        
        $user = User::where('id', $userId)
            ->where('email', $email)
            ->first();
        
        if (!$user) {
            Log::warning('User not found for resend', ['userId' => $userId, 'email' => $email]);
            return [
                'success' => false,
                'message' => 'User not found'
            ];
        }
        
        if ($user->hasVerifiedEmail()) {
            Log::warning('Email already verified', ['userId' => $userId]);
            return [
                'success' => false,
                'message' => 'Email already verified'
            ];
        }
        
        try {
            $verification = $this->sendVerification($user, $method);
            
            return [
                'success' => true,
                'verification' => $verification
            ];
        } catch (\Exception $e) {
            Log::error('Failed to resend verification: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to send verification email: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if verification is valid
     */
    public function checkVerificationStatus(int $userId, string $email)
    {
        $verification = EmailVerification::where('user_id', $userId)
            ->where('email', $email)
            ->first();
        
        if (!$verification) {
            return [
                'has_pending' => false,
                'verified' => false,
                'expired' => false
            ];
        }
        
        return [
            'has_pending' => true,
            'verified' => !is_null($verification->verified_at),
            'expired' => $verification->expires_at->isPast(),
            'expires_at' => $verification->expires_at,
            'method' => $verification->method,
            'created_at' => $verification->created_at
        ];
    }
    
    /**
     * Process verification result
     */

    private function processVerification(?EmailVerification $verification)
    {
        if (!$verification) {
            Log::warning('No verification record found');
            return [
                'success' => false,
                'message' => 'Invalid verification code or link'
            ];
        }
        
        Log::info('Processing verification', [
            'id' => $verification->id,
            'expires_at' => $verification->expires_at,
            'verified_at' => $verification->verified_at,
            'is_expired' => $verification->expires_at->isPast()
        ]);
        
        if ($verification->expires_at->isPast()) {
            Log::warning('Verification expired', ['expires_at' => $verification->expires_at]);
            return [
                'success' => false,
                'message' => 'Verification has expired. Please request a new one.'
            ];
        }
        
        if (!is_null($verification->verified_at)) {
            Log::warning('Email already verified', ['verified_at' => $verification->verified_at]);
            return [
                'success' => false,
                'message' => 'Email already verified'
            ];
        }
        
        try {
            // Start a database transaction to ensure both updates succeed
            DB::beginTransaction();
            
            // 1. Mark the verification record as verified
            $verification->update([
                'verified_at' => Carbon::now(),
                'attempts' => $verification->attempts + 1
            ]);
            
            // 2. Update the user's email_verified_at
            $user = User::find($verification->user_id);
            
            if (!$user) {
                DB::rollBack();
                Log::error('User not found for verification', ['user_id' => $verification->user_id]);
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }
            
            // Update the user's email_verified_at
            $user->email_verified_at = Carbon::now();
            $user->is_active = true; // If you have an is_active field
            $user->save();
            
            // Commit the transaction
            DB::commit();
            
            // Refresh the user model to get the updated data
            $user->refresh();
            
            Log::info('Email verified successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'verification_id' => $verification->id
            ]);
            
            return [
                'success' => true,
                'message' => 'Email verified successfully! Your account is now active.',
                'user' => $user
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process verification: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'verification_id' => $verification->id ?? null
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to verify email. Please try again.'
            ];
        }
    }
    /**
     * Clean up expired verifications (run via scheduler)
     */
    public function cleanupExpiredVerifications(): int
    {
        $expiredCount = EmailVerification::where('expires_at', '<', Carbon::now())
            ->whereNull('verified_at')
            ->count();
            
        EmailVerification::where('expires_at', '<', Carbon::now())
            ->whereNull('verified_at')
            ->delete();
            
        Log::info('Cleaned up expired verifications', ['count' => $expiredCount]);
        
        return $expiredCount;
    }
    
    /**
     * Get expiration minutes
     */
    public function getExpirationMinutes(): int
    {
        return $this->expirationMinutes;
    }
    
    /**
     * Set expiration minutes
     */
    public function setExpirationMinutes(int $minutes): self
    {
        $this->expirationMinutes = $minutes;
        return $this;
    }
}