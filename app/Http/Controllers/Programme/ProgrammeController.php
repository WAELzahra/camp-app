<?php

namespace App\Http\Controllers\Programme;

use App\Http\Controllers\Controller;
use App\Models\Programme;
use App\Models\ProgrammeItem;

class ProgrammeController extends Controller
{
    // GET /programmes
    public function index()
    {
        $programmes = Programme::where('status', 'published')
            ->with([
                'items',
                'departures' => fn ($q) => $q->where('status', 'open')->where('start_date', '>=', now()),
            ])
            ->orderBy('title')
            ->get();

        return response()->json(['programmes' => $programmes]);
    }

    // GET /programmes/{slug}
    public function show(string $slug)
    {
        $programme = Programme::where('slug', $slug)
            ->where('status', 'published')
            ->with(['rules', 'items'])
            ->firstOrFail();

        return response()->json([
            'programme' => $programme,
            'items' => $programme->items->map(fn (ProgrammeItem $item) => [
                'id' => $item->id,
                'item_type' => $item->item_type,
                'day_offset' => $item->day_offset,
                'start_time' => $item->start_time,
                'end_time' => $item->end_time,
                'price' => $item->price,
                'display_title' => $item->displayTitle(),
                'image' => $item->coverImageUrl(),
                'subtitle' => $item->subtitle(),
            ]),
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
