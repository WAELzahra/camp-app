<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PasswordResetService
{
    protected $expirationMinutes = 30;
    protected $table = 'password_reset_tokens';
    
    /**
     * Create password reset request with verification code
     */
    public function createResetRequest(string $email): array
    {
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'No account found with this email address.'
            ];
        }
        
        // Delete any existing reset requests for this email
        $this->deleteExistingResets($email);
        
        // Generate reset data
        $token = Str::random(60);
        $code = $this->generateVerificationCode();
        $expiresAt = Carbon::now()->addMinutes($this->expirationMinutes);
        
        // Store in database
        DB::table($this->table)->insert([
            'email' => $email,
            'token' => hash('sha256', $token),
            'code' => $code,
            'created_at' => now(),
            'expires_at' => $expiresAt
        ]);
        
        // Send email with code
        $this->sendResetEmail($user, $code, $token);
        
        Log::info('Password reset request created', [
            'email' => $email,
            'code' => $code
        ]);
        
        return [
            'success' => true,
            'message' => 'Password reset email sent!',
            'email' => $email // Return email for frontend to use
        ];
    }
    
    /**
     * Generate 6-digit verification code
     */
    private function generateVerificationCode(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Send reset email with verification code
     */
    private function sendResetEmail(User $user, string $code, string $token): void
    {
        try {
            $resetUrl = url(config('app.frontend_url') . '/reset-password/' . $token);
            $frontendUrl = config('app.frontend_url') . '/reset-password';
            
            Mail::to($user->email)->send(new \App\Mail\PasswordResetMail(
                $code,
                $resetUrl,
                $frontendUrl,
                $user,
                Carbon::now()->addMinutes($this->expirationMinutes)
            ));
            
            Log::info('Password reset email sent', [
                'email' => $user->email,
                'user_id' => $user->id
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send password reset email: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Verify reset code
     */
    public function verifyResetCode(string $email, string $code): array
    {
        $record = DB::table($this->table)
            ->where('email', $email)
            ->where('code', $code)
            ->first();
        
        if (!$record) {
            Log::warning('Invalid reset code', ['email' => $email, 'code' => $code]);
            return [
                'success' => false,
                'message' => 'Invalid verification code'
            ];
        }
        
        if (Carbon::parse($record->expires_at)->isPast()) {
            Log::warning('Reset code expired', ['email' => $email]);
            return [
                'success' => false,
                'message' => 'Verification code has expired'
            ];
        }
        
        Log::info('Reset code verified successfully', ['email' => $email]);
        
        return [
            'success' => true,
            'message' => 'Code verified successfully',
            'token' => $record->token // Return hashed token for password reset
        ];
    }
    
    /**
     * Reset password after code verification
     */
    public function resetPassword(string $email, string $code, string $password): array
    {
        // First verify the code
        $verification = $this->verifyResetCode($email, $code);
        
        if (!$verification['success']) {
            return $verification;
        }
        
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found'
            ];
        }
        
        // Update password
        $user->password = Hash::make($password);
        $user->save();
        
        // Delete used reset record
        $this->deleteExistingResets($email);
        
        Log::info('Password reset successful', [
            'user_id' => $user->id,
            'email' => $email
        ]);
        
        return [
            'success' => true,
            'message' => 'Password reset successfully!'
        ];
    }
    
    /**
     * Delete existing reset requests for email
     */
    private function deleteExistingResets(string $email): void
    {
        DB::table($this->table)->where('email', $email)->delete();
    }
    
    /**
     * Clean up expired reset requests (for scheduler)
     */
    public function cleanupExpiredResets(): int
    {
        $count = DB::table($this->table)
            ->where('expires_at', '<', Carbon::now())
            ->delete();
            
        Log::info('Cleaned up expired password resets', ['count' => $count]);
        
        return $count;
    }
}