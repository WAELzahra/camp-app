<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

use App\Models\Reservations_centre;

class ReservationsCentreController extends Controller
{
    public function index()
    {
        return Reservations_centre::all();
    }
    // Show a single reservation
    public function show($id)
    {
        return Reservations_centre::findOrFail($id);
    }
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
                'message' => 'reservation added successfully.',
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
    public function destroy(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'centre_id' => 'required|integer',
            'date_debut' => 'required|date',
        ]);
    
        $updated = Reservations_centre::where('user_id', $request->user_id)
        ->where('centre_id', $request->centre_id)
        ->where('date_debut', $request->date_debut)
        ->update(['status' => 'canceled']);
    
        if (!$updated) {
            return redirect()->back()->withErrors('Reservation not found.');
        }
        
        return response()->json(['message' => 'Reservation cancelled successfully.'], 200);
    }
    
    public function confirm(Request $request)
    {
        $updated = Reservations_centre::where('user_id', $request->user_id)
        ->where('centre_id', $request->centre_id)
        ->where('date_debut', $request->date_debut)
        ->update(['status' => 'approved']);
        return response()->json([
            'message' => 'reservation approved successfully.'], 201);

    }

    public function reject(Request $request)
    {
        $updated = Reservations_centre::where('user_id', $request->user_id)
        ->where('centre_id', $request->centre_id)
        ->where('date_debut', $request->date_debut)
        ->update(['status' => 'rejected']);
         return response()->json([
            'message' => 'reservation rejected successfully.'], 201);

    }
    


}
