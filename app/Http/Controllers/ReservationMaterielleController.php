<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Reservations_materielles;
use App\Models\User;

class ReservationMaterielleController extends Controller
{
    /**
     * Display a list of reservations for a specific materielle owned by the fournisseur.
     */
    public function index(int $idMaterielle)
    {
        $idFournisseur = Auth::id();

        $reservations = Reservations_materielles::where('materielle_id', $idMaterielle)
            ->where('fournisseur_id', $idFournisseur)
            ->get();

        if ($reservations->isEmpty()) {
            return response()->json([
                'message' => 'No reservations found for this materielle.',
            ], 404);
        }

        return response()->json([
            'message' => 'Reservations retrieved successfully.',
            'reservations' => $reservations,
        ], 200);
    }

    /**
     * Display the list of reservations made by the currently authenticated user.
     */
    public function index_user()
    {
        $idUser = Auth::id();

        $reservations = Reservations_materielles::where('user_id', $idUser)->get();

        if ($reservations->isEmpty()) {
            return response()->json([
                'message' => 'No reservations found for this user.',
            ], 404);
        }

        return response()->json([
            'message' => 'User reservations retrieved successfully.',
            'reservations' => $reservations,
        ], 200);
    }

    /**
     * Show all reservations that belong to the current fournisseur.
     */
    public function show()
    {
        $fournisseurId = Auth::id();

        $reservations = Reservations_materielles::where('fournisseur_id', $fournisseurId)->get();

        if ($reservations->isEmpty()) {
            return response()->json([
                'message' => 'No reservations found for this fournisseur.',
            ], 404);
        }

        return response()->json([
            'message' => 'Fournisseur reservations retrieved successfully.',
            'reservations' => $reservations,
        ], 200);
    }

    /**
     * Display the reservation form (placeholder).
     */
    public function create()
    {
        return response()->json([
            'message' => 'Reservation creation form.',
        ], 200);
    }

    /**
     * Store a new reservation.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'materielle_id' => 'required|exists:materielles,id',
            'fournisseur_id' => 'required|exists:users,id',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'quantite' => 'required|integer|min:1',
            'montant_payer' => 'required|numeric|min:0',
        ], [
            'materielle_id.required' => 'Le matériel est requis.',
            'materielle_id.exists' => 'Le matériel sélectionné est invalide.',
            'fournisseur_id.required' => 'Le fournisseur est requis.',
            'fournisseur_id.exists' => 'Le fournisseur sélectionné est invalide.',
            'date_debut.required' => 'La date de début est requise.',
            'date_fin.required' => 'La date de fin est requise.',
            'date_fin.after_or_equal' => 'La date de fin doit être égale ou postérieure à la date de début.',
            'quantite.required' => 'La quantité est requise.',
            'quantite.min' => 'La quantité doit être d\'au moins 1.',
            'montant_payer.required' => 'Le montant à payer est requis.',
        ]);

        $userId = Auth::id();

        $fournisseurUser = User::where('id', $request->fournisseur_id)
            ->where('role_id', 4)
            ->first();

        if (!$fournisseurUser) {
            return response()->json([
                'message' => 'Le fournisseur sélectionné n\'est pas valide.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $reservation = Reservations_materielles::create([
                'materielle_id' => $request->materielle_id,
                'user_id' => $userId,
                'fournisseur_id' => $request->fournisseur_id,
                'date_debut' => $request->date_debut,
                'date_fin' => $request->date_fin,
                'quantite' => $request->quantite,
                'montant_payer' => $request->montant_payer,
                'status' => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Réservation ajoutée avec succès.',
                'reservation' => $reservation,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Une erreur s\'est produite : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a reservation.
     */
    public function destroy(int $id)
    {
        $reservation = Reservations_materielles::find($id);

        if (!$reservation) {
            return response()->json([
                'message' => 'Réservation introuvable.',
            ], 404);
        }

        $reservation->update([
            'status' => 'canceled'
        ]);

        return response()->json([
            'message' => 'Réservation annulée avec succès.',
            'reservation' => $reservation,
        ], 200);
    }

    /**
     * Confirm a reservation.
     */
    public function confirm(int $id)
    {
        $reservation = Reservations_materielles::find($id);

        if (!$reservation) {
            return response()->json([
                'message' => 'Réservation introuvable.',
            ], 404);
        }

        $reservation->update([
            'status' => 'confirmed'
        ]);

        return response()->json([
            'message' => 'Réservation confirmée avec succès.',
            'reservation' => $reservation,
        ], 200);
    }

    /**
     * Reject a reservation.
     */
    public function reject(int $id)
    {
        $reservation = Reservations_materielles::find($id);

        if (!$reservation) {
            return response()->json([
                'message' => 'Réservation introuvable.',
            ], 404);
        }

        $reservation->update([
            'status' => 'rejected'
        ]);

        return response()->json([
            'message' => 'Réservation rejetée avec succès.',
            'reservation' => $reservation,
        ], 200);
    }
}
