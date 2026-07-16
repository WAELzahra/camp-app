<?php

namespace App\Http\Controllers\Programme;

use App\Http\Controllers\Controller;
use App\Models\Programme;

class ProgrammeController extends Controller
{
    // GET /programmes
    public function index()
    {
        $programmes = Programme::where('status', 'published')
            ->with(['departures' => fn ($q) => $q->where('status', 'open')->where('start_date', '>=', now())])
            ->orderBy('title')
            ->get();

        return response()->json(['programmes' => $programmes]);
    }

    // GET /programmes/{slug}
    public function show(string $slug)
    {
        $programme = Programme::where('slug', $slug)
            ->where('status', 'published')
            ->with(['rules', 'steps.stepPartners.partner:id,name,partner_type_id', 'steps.stepPartners.partner.partnerType'])
            ->firstOrFail();

        return response()->json([
            'programme' => $programme,
            'base_price' => $programme->basePrice(),
        ]);
    }

    // GET /programmes/{slug}/departures
    public function departures(string $slug)
    {
        $programme = Programme::where('slug', $slug)->where('status', 'published')->firstOrFail();

        $departures = $programme->departures()
            ->where('status', 'open')
            ->where('start_date', '>=', now())
            ->orderBy('start_date')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'start_date' => $d->start_date,
                'end_date' => $d->end_date,
                'seats_remaining' => $d->seatsRemaining(),
                'price_per_participant' => $d->pricePerParticipant(),
            ]);

        return response()->json(['departures' => $departures]);
    }
}
