<?php

namespace App\GraphQL\Mutations;

use App\Models\Reservations_centre;
use Illuminate\Support\Facades\DB;

class ReservationCenterMutator{
    public function create($_, array $args)
    {
        $user = auth()->user();
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        if (!in_array($user->role->name, ['campeur'])) {
            throw new \Exception('Unauthorized: Only campeur can create annonces.');
        }
        DB::beginTransaction();
        try {
            $reservations_centre = Reservations_centre::create([
                'user_id' => $user->id,
                'centre_id' => $args['centre_id'],
                'date_debut' => $args['date_debut'],
                'date_fin' => $args['date_fin'],
                'nbr_place' => $args['nbr_place'],
                'note' => $args['note'],
                'type' => $args['type'],
            ]);

            DB::commit();
            return $reservations_centre;

        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error occurred: ' . $e->getMessage());
        }

    }
    public function destroy($_, array $args)
    {
        $user = auth()->user();
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        if (!in_array($user->role->name, ['campeur'])) {
            throw new \Exception('Unauthorized: Only campeur can cancel annonces.');
        }
        $reservation = Reservations_centre::where('user_id', $args['user_id'])
            ->where('centre_id', $args['centre_id'])
            ->where('date_debut', $args['date_debut'])
            ->firstOrFail();
    
        $reservation->update([
            'status' => 'canceled',
        ]);
    
        return $reservation;
    }
    public function reject($_, array $args)
    {
        $user = auth()->user();
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        if (!in_array($user->role->name, ['centre'])) {
            throw new \Exception('Unauthorized: Only centre can reject annonces.');
        }
        $updated = Reservations_centre::where('user_id', $args['user_id'])
            ->where('centre_id', $args['centre_id'])
            ->where('date_debut', $args['date_debut'])
            ->update(['status' => 'rejected']);

        return [
            'success' => $updated > 0,
            'message' => $updated > 0 ? 'Reservation rejected successfully.' : 'Reservation not found.',
        ];
    }

    public function confirm($_, array $args)
    {
        $user = auth()->user();
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        if (!in_array($user->role->name, ['centre'])) {
            throw new \Exception('Unauthorized: Only centre can comfirm annonces.');
        }
        $updated = Reservations_centre::where('user_id', $args['user_id'])
            ->where('centre_id', $args['centre_id'])
            ->where('date_debut', $args['date_debut'])
            ->update(['status' => 'approved']);

        return [
            'success' => $updated > 0,
            'message' => $updated > 0 ? 'Reservation approved successfully.' : 'Reservation not found.',
        ];
    }


}