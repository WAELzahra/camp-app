<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

use App\Models\Reservations_centre;

class ReservationsCentreController extends Controller
{
    /**
     * Display the list of reservations for a centre.
     */
    public function index()
    {
        $idCentre = Auth::id();

        $reservations = Reservations_centre::where('centre_id', $idCentre)->get();

        return response()->json([
            'message' => 'Centre reservations retrieved successfully.',
            'reservations' => $reservations
        ], 200);
    }

    /**
     * Display the list of reservations that belong to the authenticated user.
     */
    public function index_user()
    {
        $idUser = Auth::id();

        $reservations = Reservations_centre::where('user_id', $idUser)->get();

        return response()->json([
            'message' => 'User reservations retrieved successfully.',
            'reservations' => $reservations
        ], 200);
    }

    /**
     * Display a specific reservation by its ID.
     */
    public function show(int $idReservation)
    {
        $reservation = Reservations_centre::find($idReservation);

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found.'], 404);
        }

        return response()->json([
            'message' => 'Reservation retrieved successfully.',
            'reservation' => $reservation
        ], 200);
    }

    /**
     * Show the form for creating a reservation (not used in API).
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created reservation in the database.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'centre_id' => 'required|exists:users,id',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'type' => 'required|string',
            'nbr_place' => 'required|integer',
            'note' => 'nullable|string',
        ], [
            'centre_id.required' => 'The centre ID is required.',
            'centre_id.exists' => 'The selected centre does not exist.',
            'date_debut.required' => 'Start date is required.',
            'date_fin.required' => 'End date is required.',
            'date_fin.after_or_equal' => 'The end date must be after or equal to the start date.',
            'type.required' => 'The type of reservation is required.',
            'nbr_place.required' => 'The number of places is required.',
        ]);

        $userId = Auth::id();

        $centreUser = \App\Models\User::where('id', $request->centre_id)
            ->where('role_id', 3)
            ->first();

        if (!$centreUser) {
            return response()->json([
                'message' => 'The selected centre_id does not belong to a user with the role "centre".'
            ], 422);
        }

        DB::beginTransaction();

        try {
            $reservationCentre = Reservations_centre::create([
                'user_id' => $userId,
                'centre_id' => $request->centre_id,
                'date_debut' => $request->date_debut,
                'date_fin' => $request->date_fin,
                'note' => $request->note,
                'type' => $request->type,
                'nbr_place' => $request->nbr_place,
                'status' => 'pending'
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Reservation added successfully.',
                'reservationCentre' => $reservationCentre
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a reservation (change status to 'canceled').
     */
    public function destroy(int $id)
    {
        $reservation = Reservations_centre::findOrFail($id);

        $reservation->status = 'canceled';
        $updated = $reservation->save();

        if (!$updated) {
            return response()->json(['message' => 'Failed to cancel reservation.'], 500);
        }

        return response()->json([
            'message' => 'Reservation cancelled successfully.',
            'reservation' => $reservation
        ], 200);
    }

    /**
     * Confirm (approve) a reservation.
     */
    public function confirm(int $id)
    {
        $reservation = Reservations_centre::findOrFail($id);

        $reservation->status = 'approved';
        $updated = $reservation->save();

        if (!$updated) {
            return response()->json(['message' => 'Failed to approve reservation.'], 500);
        }

        return response()->json([
            'message' => 'Reservation approved successfully.',
            'reservation' => $reservation
        ], 200);
    }

    /**
     * Reject a reservation.
     */
    public function reject(int $id)
    {
        $reservation = Reservations_centre::findOrFail($id);

        $reservation->status = 'rejected';
        $updated = $reservation->save();

        if (!$updated) {
            return response()->json(['message' => 'Failed to reject reservation.'], 500);
        }

        return response()->json([
            'message' => 'Reservation rejected successfully.',
            'reservation' => $reservation
        ], 200);
    }
}
