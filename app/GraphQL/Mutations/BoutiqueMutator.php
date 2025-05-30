<?php

namespace App\GraphQL\Mutations;

use App\Models\Annonce;
use App\Models\Photos;
use Illuminate\Support\Facades\DB;

class BoutiqueMutator
{
    public function add($_, array $args)
    {
        $user = auth()->user();
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        if (!in_array($user->role->name, ['fournisseur'])) {
            throw new \Exception('Unauthorized: Only fournisseurs can create a shop.');
        }

        $validated = validator($args, [
            'nom_boutique' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ])->validate();

        $userId = Auth::id();

        $boutique = Boutiques::create([
            'users_id' => $userId,
            'nom_boutique' => $args['nom_boutique'],
            'description' => $args['description'] ?? null,
            'status' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $boutique;
    }

    public function update($_, array $args)
    {
        
        $user = auth()->user();
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        if (!in_array($user->role->name, ['fournisseur'])) {
            throw new \Exception('Unauthorized: Only fournisseurs can update a shop.');
        }

        
        $userId = Auth::id();


        Boutiques::where('users_id', $userId)->update([
            'nom_boutique' => $args['nom_boutique'] ?? null,
            'description' => $args['description'] ?? null,
            'updated_at' => now(),
        ]);

        return 'boutique updated successfully.';
    }

    public function destroy($_, array $args)
    {

        $user = auth()->user();
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        if (!in_array($user->role->name, ['fournisseur','admin'])) {
            throw new \Exception('Unauthorized: Only fournisseurs can destroy a shop.');
        }

        $userId = Auth::id();

        Boutiques::where('users_id', $userId)->delete();

        return 'boutique removed successfully.';
    }
}