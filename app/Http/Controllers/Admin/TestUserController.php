<?php
// app/Http/Controllers/Admin/TestUserController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestUserController extends Controller
{
    public function index()
    {
        Log::info('🔵 TestUserController::index appelé');
        
        try {
            // Récupérer tous les utilisateurs sans relations complexes
            $users = User::select('id', 'first_name', 'last_name', 'email', 'is_active', 'avatar')
                        ->limit(5)
                        ->get();
            
            Log::info('🟢 Utilisateurs trouvés:', ['count' => $users->count()]);
            
            return response()->json([
                'success' => true,
                'message' => 'Test réussi',
                'data' => $users
            ]);
            
        } catch (\Exception $e) {
            Log::error('🔴 Erreur:', ['message' => 'An unexpected error occurred. Please try again.']);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.'
            ], 500);
        }
    }
}