<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Events;
use App\Models\Materielles;
use App\Models\Programme;
use App\Models\ProgrammeDeparture;
use App\Models\ProgrammeItem;
use App\Models\ProgrammeReservation;
use App\Models\ProfileCentre;
use App\Services\ProgrammeLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProgrammeController extends Controller
{
    public function __construct(private ProgrammeLedgerService $ledger)
    {
    }

    /* ── Programme CRUD ──────────────────────────────────────────────────── */

    // GET /admin/programmes
    public function index()
    {
        $programmes = Programme::with(['items', 'departures'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['programmes' => $programmes]);
    }

    // POST /admin/programmes
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|max:5120',
            'min_participants' => 'nullable|integer|min:1',
            'max_participants' => 'nullable|integer|min:1',
            'cancellation_policy_id' => 'nullable|exists:cancellation_policies,id',
        ]);

        if ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $request->file('cover_image')->store('programmes', 'public');
        } else {
            unset($validated['cover_image']);
        }

        $validated['slug'] = $this->uniqueSlug($validated['title']);
        $validated['created_by'] = $request->user()->id;
        $validated['status'] = 'draft';

        $programme = Programme::create($validated);

        return response()->json(['programme' => $programme], 201);
    }

    // GET /admin/programmes/{id}
    public function show(int $id)
    {
        $programme = Programme::with(['rules', 'items', 'departures', 'cancellationPolicy'])->findOrFail($id);

        return response()->json([
            'programme' => $programme,
            'items' => $programme->items->map(fn (ProgrammeItem $item) => $this->itemPayload($item)),
        ]);
    }

    // PUT /admin/programmes/{id}
    public function update(Request $request, int $id)
    {
        $programme = Programme::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|max:5120',
            'min_participants' => 'nullable|integer|min:1',
            'max_participants' => 'nullable|integer|min:1',
            'cancellation_policy_id' => 'nullable|exists:cancellation_policies,id',
        ]);

        if ($request->hasFile('cover_image')) {
            if ($programme->cover_image) {
                Storage::disk('public')->delete($programme->cover_image);
            }
            $validated['cover_image'] = $request->file('cover_image')->store('programmes', 'public');
        } else {
            unset($validated['cover_image']);
        }

        $programme->update($validated);

        return response()->json(['programme' => $programme]);
    }

    // DELETE /admin/programmes/{id}
    public function destroy(int $id)
    {
        $programme = Programme::findOrFail($id);

        if (ProgrammeReservation::whereHas('departure', fn ($q) => $q->where('programme_id', $id))->exists()) {
            return response()->json(['message' => 'Impossible de supprimer un programme ayant des réservations.'], 422);
        }

        $programme->delete();

        return response()->json(['message' => 'Programme supprimé.']);
    }

    /* ── Status transitions ──────────────────────────────────────────────── */

    // POST /admin/programmes/{id}/publish
    public function publish(int $id)
    {
        $programme = Programme::findOrFail($id);
        $programme->update(['status' => 'published', 'publish_at' => now()]);

        return response()->json(['programme' => $programme]);
    }

    // POST /admin/programmes/{id}/schedule
    public function schedule(Request $request, int $id)
    {
        $validated = $request->validate(['publish_at' => 'required|date|after:now']);
        $programme = Programme::findOrFail($id);
        $programme->update(['status' => 'scheduled', 'publish_at' => $validated['publish_at']]);

        return response()->json(['programme' => $programme]);
    }

    // POST /admin/programmes/{id}/archive
    public function archive(int $id)
    {
        $programme = Programme::findOrFail($id);
        $programme->update(['status' => 'archived']);

        return response()->json(['programme' => $programme]);
    }

    // POST /admin/programmes/{id}/duplicate
    public function duplicate(int $id)
    {
        $programme = Programme::with(['rules', 'items'])->findOrFail($id);

        $copy = DB::transaction(function () use ($programme) {
            $copy = Programme::create([
                'slug' => $this->uniqueSlug($programme->title.' copie'),
                'title' => $programme->title.' (copie)',
                'description' => $programme->description,
                'cover_image' => $programme->cover_image,
                'status' => 'draft',
                'min_participants' => $programme->min_participants,
                'max_participants' => $programme->max_participants,
                'cancellation_policy_id' => $programme->cancellation_policy_id,
                'created_by' => $programme->created_by,
            ]);

            foreach ($programme->rules as $rule) {
                $copy->rules()->create(['type' => $rule->type, 'content' => $rule->content, 'sort_order' => $rule->sort_order]);
            }

            foreach ($programme->items as $item) {
                $copy->items()->create($item->only([
                    'sort_order', 'day_offset', 'start_time', 'end_time', 'item_type', 'item_id', 'price', 'commission_rate',
                ]));
            }

            return $copy;
        });

        return response()->json(['programme' => $copy->load('items')], 201);
    }

    /* ── Lookup: search existing published listings to bundle ───────────── */

    // GET /admin/programmes/lookup?type=event|centre|materiel&q=...
    public function lookup(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:event,centre,materiel',
            'q' => 'nullable|string|max:100',
        ]);
        $q = $validated['q'] ?? '';

        $results = match ($validated['type']) {
            'event' => Events::where('is_active', true)
                ->when($q, fn ($query) => $query->where('title', 'like', "%{$q}%"))
                ->with('group:id,first_name,last_name')
                ->orderBy('title')
                ->limit(20)
                ->get()
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'suggested_price' => (float) $e->price,
                    'owner_name' => $e->group ? trim("{$e->group->first_name} {$e->group->last_name}") : null,
                ]),
            'centre' => ProfileCentre::with('user:id,first_name,last_name')
                ->when($q, fn ($query) => $query->where('name', 'like', "%{$q}%"))
                ->orderBy('name')
                ->limit(20)
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'title' => $c->name,
                    'suggested_price' => (float) ($c->price_per_night ?? 0),
                    'owner_name' => $c->user ? trim("{$c->user->first_name} {$c->user->last_name}") : null,
                ]),
            'materiel' => Materielles::where('status', 'up')
                ->when($q, fn ($query) => $query->where('nom', 'like', "%{$q}%"))
                ->with('fournisseur:id,first_name,last_name')
                ->orderBy('nom')
                ->limit(20)
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'title' => $m->nom,
                    'suggested_price' => (float) ($m->tarif_nuit ?? $m->tarif_heure ?? $m->prix_vente ?? 0),
                    'owner_name' => $m->fournisseur ? trim("{$m->fournisseur->first_name} {$m->fournisseur->last_name}") : null,
                ]),
        };

        return response()->json(['results' => $results]);
    }

    /* ── Items (flat list — replaces steps + step-partners) ──────────────── */

    // POST /admin/programmes/{id}/items
    public function storeItem(Request $request, int $id)
    {
        $programme = Programme::findOrFail($id);
        $validated = $request->validate([
            'item_type' => 'required|in:event,centre,materiel',
            'item_id' => 'required|integer',
            'price' => 'required|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'sort_order' => 'nullable|integer',
            'day_offset' => 'nullable|integer|min:0',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
        ]);

        if (!$this->listingExists($validated['item_type'], $validated['item_id'])) {
            return response()->json(['message' => 'Cette offre est introuvable ou n\'est plus publiée.'], 422);
        }

        $item = $programme->items()->create($validated);

        return response()->json(['item' => $this->itemPayload($item)], 201);
    }

    // PUT /admin/programmes/{id}/items/{itemId}
    public function updateItem(Request $request, int $id, int $itemId)
    {
        $item = ProgrammeItem::where('programme_id', $id)->findOrFail($itemId);
        $validated = $request->validate([
            'price' => 'sometimes|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'sort_order' => 'nullable|integer',
            'day_offset' => 'nullable|integer|min:0',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
        ]);

        $item->update($validated);

        return response()->json(['item' => $this->itemPayload($item->fresh())]);
    }

    // DELETE /admin/programmes/{id}/items/{itemId}
    public function destroyItem(int $id, int $itemId)
    {
        ProgrammeItem::where('programme_id', $id)->findOrFail($itemId)->delete();

        return response()->json(['message' => 'Élément retiré du programme.']);
    }

    /* ── Departures ───────────────────────────────────────────────────────── */

    // POST /admin/programmes/{id}/departures
    public function storeDeparture(Request $request, int $id)
    {
        $programme = Programme::findOrFail($id);
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'capacity_max' => 'required|integer|min:1',
            'price_override' => 'nullable|numeric|min:0',
        ]);

        $departure = $programme->departures()->create($validated);

        return response()->json(['departure' => $departure], 201);
    }

    // PUT /admin/programmes/{id}/departures/{departureId}
    public function updateDeparture(Request $request, int $id, int $departureId)
    {
        $departure = ProgrammeDeparture::where('programme_id', $id)->findOrFail($departureId);
        $validated = $request->validate([
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'capacity_max' => 'sometimes|integer|min:1',
            'price_override' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:open,full,cancelled,completed',
        ]);

        $departure->update($validated);

        return response()->json(['departure' => $departure]);
    }

    // DELETE /admin/programmes/{id}/departures/{departureId}
    public function destroyDeparture(int $id, int $departureId)
    {
        $departure = ProgrammeDeparture::where('programme_id', $id)->findOrFail($departureId);

        if ($departure->reservations()->exists()) {
            return response()->json(['message' => 'Impossible de supprimer un départ ayant des réservations.'], 422);
        }

        $departure->delete();

        return response()->json(['message' => 'Départ supprimé.']);
    }

    /* ── Reservations & payouts ───────────────────────────────────────────── */

    // GET /admin/programmes/{id}/reservations
    public function reservations(int $id)
    {
        $reservations = ProgrammeReservation::whereHas('departure', fn ($q) => $q->where('programme_id', $id))
            ->with(['user:id,first_name,last_name,email', 'departure', 'shares.item', 'shares.owner:id,first_name,last_name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['reservations' => $reservations]);
    }

    // POST /admin/programmes/reservations/{id}/confirm
    public function confirmReservation(int $id)
    {
        $reservation = ProgrammeReservation::findOrFail($id);

        if ($reservation->status !== 'pending') {
            return response()->json(['message' => 'Seules les réservations en attente peuvent être confirmées.'], 422);
        }

        $this->ledger->payoutShares($reservation);
        $reservation->update(['status' => 'confirmed']);

        return response()->json(['reservation' => $reservation->load('shares')]);
    }

    // GET /admin/programmes/{id}/revenue
    public function revenue(int $id)
    {
        $reservations = ProgrammeReservation::whereHas('departure', fn ($q) => $q->where('programme_id', $id))
            ->where('status', '!=', 'cancelled')
            ->with('shares')
            ->get();

        $totalCollected = $reservations->sum('amount_now');
        $shares = $reservations->flatMap->shares;

        return response()->json([
            'total_collected' => round($totalCollected, 2),
            'total_owner_net' => round($shares->sum('net_amount'), 2),
            'total_commission' => round($shares->sum('commission_amount'), 2),
            'reservations_count' => $reservations->count(),
            'by_owner' => $shares->groupBy('owner_user_id')->map(fn ($g) => [
                'owner_user_id' => $g->first()->owner_user_id,
                'gross' => round($g->sum('gross_amount'), 2),
                'commission' => round($g->sum('commission_amount'), 2),
                'net' => round($g->sum('net_amount'), 2),
            ])->values(),
        ]);
    }

    // GET /admin/programmes/{id}/export
    public function export(int $id)
    {
        $reservations = ProgrammeReservation::whereHas('departure', fn ($q) => $q->where('programme_id', $id))
            ->with(['user:id,first_name,last_name,email', 'departure', 'shares.item', 'shares.owner:id,first_name,last_name'])
            ->orderBy('created_at')
            ->get();

        $rows = ["Reservation ID,Client,Email,Depart,Participants,Total,Statut,Element,Part nette"];
        foreach ($reservations as $r) {
            $clientName = trim(($r->user->first_name ?? '').' '.($r->user->last_name ?? ''));
            if ($r->shares->isEmpty()) {
                $rows[] = "{$r->id},{$clientName},{$r->user->email},{$r->departure->start_date},{$r->participants_count},{$r->total_price},{$r->status},,";
                continue;
            }
            foreach ($r->shares as $share) {
                $label = $share->item?->displayTitle() ?? "#{$share->programme_item_id}";
                $rows[] = "{$r->id},{$clientName},{$r->user->email},{$r->departure->start_date},{$r->participants_count},{$r->total_price},{$r->status},{$label},{$share->net_amount}";
            }
        }

        return response(implode("\n", $rows), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=programme-{$id}-export.csv",
        ]);
    }

    private function listingExists(string $type, int $id): bool
    {
        return match ($type) {
            'event' => Events::where('id', $id)->where('is_active', true)->exists(),
            'centre' => ProfileCentre::where('id', $id)->exists(),
            'materiel' => Materielles::where('id', $id)->where('status', 'up')->exists(),
            default => false,
        };
    }

    private function itemPayload(ProgrammeItem $item): array
    {
        return array_merge($item->toArray(), [
            'display_title' => $item->displayTitle(),
            'owner_name' => optional(\App\Models\User::find($item->ownerUserId()))->first_name,
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 1;
        while (Programme::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
