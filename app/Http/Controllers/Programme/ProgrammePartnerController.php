<?php

namespace App\Http\Controllers\Programme;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\ProgrammeReservationShare;
use Illuminate\Http\Request;

class ProgrammePartnerController extends Controller
{
    // GET /partner/programme-shares — for partners with a platform account
    public function mine(Request $request)
    {
        $partner = Partner::where('user_id', $request->user()->id)->first();

        if (!$partner) {
            return response()->json(['message' => 'Aucun profil partenaire associé à ce compte.'], 404);
        }

        return response()->json($this->sharesPayload($partner));
    }

    // GET /partner-view/{partner} — signed URL, for partners without a platform account.
    // The 'signed' middleware rejects the request before this runs if the signature
    // is missing, expired, or tampered with — no further auth check needed here.
    public function signedView(int $partner)
    {
        $partner = Partner::findOrFail($partner);

        return response()->json($this->sharesPayload($partner));
    }

    private function sharesPayload(Partner $partner): array
    {
        $shares = ProgrammeReservationShare::where('partner_id', $partner->id)
            ->with(['reservation.departure.programme', 'reservation.user:id,first_name,last_name'])
            ->orderByDesc('created_at')
            ->get();

        return [
            'partner' => $partner->only(['id', 'name', 'status']),
            'shares' => $shares->map(fn ($s) => [
                'reservation_id' => $s->programme_reservation_id,
                'programme_title' => $s->reservation->departure->programme->title,
                'departure_date' => $s->reservation->departure->start_date,
                'client_name' => trim(($s->reservation->user->first_name ?? '').' '.($s->reservation->user->last_name ?? '')),
                'gross_amount' => $s->gross_amount,
                'net_amount' => $s->net_amount,
                'status' => $s->reservation->status,
                'credited' => $s->partner_credited,
                'released_at' => $s->released_at,
            ]),
        ];
    }
}
