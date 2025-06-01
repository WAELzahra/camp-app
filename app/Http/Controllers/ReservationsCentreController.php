<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

use App\Models\Reservations_centre;

class ReservationsCentreController extends Controller
{
    /**
     * Display the list of reservation for a center.
     */
    public function index()
    {
        $idCentre = Auth::id();
    }
    // display the list of reservation that belong to a user
    public function index_user()
    {
        $idUser = Auth::id();
    }

    // display a specific reservation
    public function show(int $idReservation)
    {

    }
    // display the form to make a reservation
    public function create(){

    } 

    // create a new reservation
    public function store(Request $request)
    {
        $validated = $request->validate([
            'centre_id' => 'required|exists:users,id',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'type' => 'required|string',
            'nbr_place' => 'required|integer',
            'note' => 'string',
        ]);
    
        $userId = Auth::id();
    
        // Extra verification: check if centre_id is a user with role "centre"
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
    

    // cancel a reservation
    public function destroy(int $id)
    {
        $reservation = Reservations_centre::findOrFail($id);
    
        $reservation->status = 'canceled';
        $updated = $reservation->save();
    
        if (!$updated) {
            return redirect()->back()->withErrors('Failed to cancel reservation.');
        }
    
        return response()->json(['message' => 'Reservation cancelled successfully.'], 200);
    }
    
    // confirm a reservatoin
    public function confirm(int $id)
    {
        $reservation = Reservations_centre::findOrFail($id);

        $reservation->status = 'approved';
        $updated = $reservation->save();
    
        if (!$updated) {
            return redirect()->back()->withErrors('Failed to approved reservation.');
        }
    
        return response()->json(['message' => 'Reservation approved successfully.'], 200);

    }

    // reject a reservatoin
    public function reject(int $id)
    {
        $reservation = Reservations_centre::findOrFail($id);

        $reservation->status = 'rejected';
        $updated = $reservation->save();
    
        if (!$updated) {
            return redirect()->back()->withErrors('Failed to rejected reservation.');
        }
    
        return response()->json(['message' => 'Reservation rejected successfully.'], 200);
    
    }
    


}
