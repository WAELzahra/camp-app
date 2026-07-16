<?php

namespace App\Http\Controllers\Programme;

use App\Http\Controllers\Controller;
use App\Models\ProgrammeReservationShare;
use Illuminate\Http\Request;

/**
 * Every actor bundled into a Programme (event organizer, centre owner,
 * equipment supplier) already has a platform account — this is simply
 * "my Programme income", scoped by the authenticated user's own id.
 * No separate Partner directory or signed-link mechanism is needed.
 */
class ProgrammeOwnerSharesController extends Controller
{
    // GET /programme/my-shares
    public function mine(Request $request)
    {
        $shares = ProgrammeReservationShare::where('owner_user_id', $request->user()->id)
            ->with(['reservation.departure.programme', 'reservation.user:id,first_name,last_name', 'item'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'shares' => $shares->map(fn ($s) => [
                'reservation_id' => $s->programme_reservation_id,
                'programme_title' => $s->reservation->departure->programme->title,
                'item_title' => $s->item?->displayTitle(),
                'departure_date' => $s->reservation->departure->start_date,
                'client_name' => trim(($s->reservation->user->first_name ?? '').' '.($s->reservation->user->last_name ?? '')),
                'gross_amount' => $s->gross_amount,
                'net_amount' => $s->net_amount,
                'status' => $s->reservation->status,
                'credited' => $s->credited,
                'released_at' => $s->released_at,
            ]),
        ]);
    }
}
