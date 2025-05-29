<?php

namespace App\GraphQL\Mutations;

use App\Models\Annonce;
use App\Models\Photos;
use Illuminate\Support\Facades\DB;

class AnnonceMutator
{
    public function create($_, array $args)
    {
        $user = auth()->user();
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        if (!in_array($user->role->name, ['fournisseur', 'centre'])) {
            throw new \Exception('Unauthorized: Only fournisseurs can create annonces.');
        }

        DB::beginTransaction();
        try {
            $annonce = Annonce::create([
                'description' => $args['description'],
                'user_id' => $user->id,
            ]);

            Photos::create([
                'annonce_id' => $annonce->id,
                'path_to_img' => $args["path_to_image"],
            ]);

            DB::commit();
            return $annonce;

        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error occurred: ' . $e->getMessage());
        }
    }

    public function update($_, array $args)
    {
        $user = auth()->user();
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        if (!in_array($user->role->name, ['fournisseur', 'centre'])) {
            throw new \Exception('Unauthorized: Only fournisseurs can update annonces.');
        }

        DB::beginTransaction();
        try {
            $annonce = Annonce::findOrFail($args["annonce_id"]);

            $annonce->update([
                'description' => $args['description'],
                'user_id' => $user->id,
                // Optional: 'status' => $args['status'],
            ]);

            $photo = Photos::where('annonce_id', $annonce->id)->first();
            if ($photo && isset($args['path_to_image'])) {
                $photo->update([
                    'path_to_img' => $args['path_to_image'],
                ]);
            }

            DB::commit();
            return $annonce;

        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error occurred: ' . $e->getMessage());
        }
    }
    public function delete($_, array $args)
    {
        $user = auth()->user();
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        $annonce = Annonce::findOrFail($args['annonce_id']);

        if ($annonce->user_id !== $user->id) {
            throw new \Exception('Unauthorized: Cannot delete this annonce.');
        }

        DB::beginTransaction();
        try {
            // Delete associated photo first if exists
            Photos::where('annonce_id', $annonce->id)->delete();
            $annonce->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error occurred: ' . $e->getMessage());
        }
    }

}
