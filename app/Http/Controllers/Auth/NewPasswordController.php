<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class NewPasswordController extends Controller
{
    protected PasswordResetService $passwordResetService;
    
    public function __construct(PasswordResetService $passwordResetService)
    {
        $this->passwordResetService = $passwordResetService;
    }
    
    /**
     * Send password reset email with code
     */
    public function sendResetEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide a valid email address',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $result = $this->passwordResetService->createResetRequest($request->email);
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('Failed to send reset email: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reset email. Please try again.'
            ], 500);
        }
    }
    
    /**
     * Verify reset code
     */
    public function verifyResetCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string|size:6'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid data',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $result = $this->passwordResetService->verifyResetCode(
                $request->email,
                $request->code
            );
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('Failed to verify reset code: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify code. Please try again.'
            ], 500);
        }
    }
    
    /**
     * Reset password after code verification
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid data',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $result = $this->passwordResetService->resetPassword(
                $request->email,
                $request->code,
                $request->password
            );
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('Failed to reset password: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password. Please try again.'
            ], 500);
        }
    }
}