<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

use App\Models\Reservations_materielles;

class ReservationMaterielleController extends Controller
{
    /**
     * Display a list of reservation for a materielle of a fournisseur.
     */
    public function index(int $idMaterielle)
    {
        $idFournisseur = Auth::id();
    }

    // display the list of a reservation that belong to a user
    public function index_user()
    {
        $idUser = Auth::id();
    }
    // show all the reservations that belong to a fournisseur
    public function show()
    {
        $fournisseurId = Auth::id();

        $reservations = Reservations_materielles::where('fournisseur_id',fournisseurId)->first();
    
        return response()->json($reservations);
    }

    // display the form to make a reservation
    public function create(){

    } 

    public function store(Request $request)
    {
        $validated = $request->validate([
            'materielle_id' => 'required|exists:materielles,id',
            'fournisseur_id' => 'required|exists:users,id',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'quantite' => 'required|integer',
            'montant_payer' => 'required|numeric'
        ]);

        $userId = Auth::id();
    
        // Extra verification: check if fournisseur_id is a user with role "fournisseur"
        $fournisseurUser = \App\Models\User::where('id', $request->fournisseur_id)
            ->where('role_id', 4)
            ->first();
    
        if (!$fournisseurUser) {
            return response()->json([
                'message' => 'The selected fournisseur_id does not belong to a user with the role "fournisseur".'
            ], 422);
        }

        DB::beginTransaction();

        try {
            
            $reservation = Reservations_materielles::create([
                'materielle_id' => $request->materielle_id,
                'user_id' => $userId,
                'fournisseur_id' => $request->fournisseur_id,
                'date_debut' => $request->date_debut,
                'date_fin' =>  $request->date_fin,
                'quantite' => $request->quantite,
                'montant_payer' => $request->montant_payer,
                'status' => 'pending'
            ]);

            DB::commit();

            return response()->json([
                'message' => 'reservation added successfully.',
                'reservation' => $reservation,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    // cancel a reservation
    public function destroy(int $id)
    {
        $reservation = Reservations_materielles::findOrFail($id);
        $reservation->update([
            'status' => 'canceled'
        ]);
        return response()->json([
            'message' => 'reservation destroyed successfully.',
            'reservation' => $reservation
        ], 201);
    }

    public function confirm(int $id)
    {

        $reservation = Reservations_materielles::findOrFail($id);
        $reservation->update([
            'status' => 'confirmed'
        ]);
        return response()->json([
            'message' => 'reservation confirmed successfully.',
            'reservation' => $reservation
        ], 201);
    }

    public function reject(int $id)
    {
        $reservation = Reservations_materielles::findOrFail($id);
        $reservation->update([
            'status' => 'rejected'
        ]);
        return response()->json([
            'message' => 'reservation rejected successfully.',
            'reservation' => $reservation
        ], 201);

    }

}
