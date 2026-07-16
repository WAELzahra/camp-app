<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Programme;
use App\Models\ProgrammeDeparture;
use App\Models\ProgrammeReservation;
use App\Models\ProgrammeStep;
use App\Models\ProgrammeStepPartner;
use App\Services\ProgrammeLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $programmes = Programme::with(['steps.stepPartners.partner', 'departures'])
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
            'cover_image' => 'nullable|string|max:255',
            'min_participants' => 'nullable|integer|min:1',
            'max_participants' => 'nullable|integer|min:1',
            'cancellation_policy_id' => 'nullable|exists:cancellation_policies,id',
        ]);

        $validated['slug'] = $this->uniqueSlug($validated['title']);
        $validated['created_by'] = $request->user()->id;
        $validated['status'] = 'draft';

        $programme = Programme::create($validated);

        return response()->json(['programme' => $programme], 201);
    }

    // GET /admin/programmes/{id}
    public function show(int $id)
    {
        $programme = Programme::with([
            'rules', 'steps.stepPartners.partner.partnerType', 'departures', 'cancellationPolicy',
        ])->findOrFail($id);

        return response()->json(['programme' => $programme]);
    }

    // PUT /admin/programmes/{id}
    public function update(Request $request, int $id)
    {
        $programme = Programme::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|string|max:255',
            'min_participants' => 'nullable|integer|min:1',
            'max_participants' => 'nullable|integer|min:1',
            'cancellation_policy_id' => 'nullable|exists:cancellation_policies,id',
        ]);

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
        $programme = Programme::with(['rules', 'steps.stepPartners'])->findOrFail($id);

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

            foreach ($programme->steps as $step) {
                $newStep = $copy->steps()->create($step->only([
                    'sort_order', 'title', 'description', 'day_offset', 'start_time', 'end_time',
                    'location_label', 'location_lat', 'location_lng',
                ]));

                foreach ($step->stepPartners as $sp) {
                    $newStep->stepPartners()->create($sp->only(['partner_id', 'price', 'commission_rate']));
                }
            }

            return $copy;
        });

        return response()->json(['programme' => $copy->load('steps.stepPartners')], 201);
    }

    /* ── Steps (nested) ──────────────────────────────────────────────────── */

    // POST /admin/programmes/{id}/steps
    public function storeStep(Request $request, int $id)
    {
        $programme = Programme::findOrFail($id);
        $validated = $request->validate([
            'sort_order' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'day_offset' => 'nullable|integer|min:0',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'location_label' => 'nullable|string|max:255',
            'location_lat' => 'nullable|numeric',
            'location_lng' => 'nullable|numeric',
        ]);

        $step = $programme->steps()->create($validated);

        return response()->json(['step' => $step], 201);
    }

    // PUT /admin/programmes/{id}/steps/{stepId}
    public function updateStep(Request $request, int $id, int $stepId)
    {
        $step = ProgrammeStep::where('programme_id', $id)->findOrFail($stepId);
        $validated = $request->validate([
            'sort_order' => 'nullable|integer',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'day_offset' => 'nullable|integer|min:0',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'location_label' => 'nullable|string|max:255',
            'location_lat' => 'nullable|numeric',
            'location_lng' => 'nullable|numeric',
        ]);

        $step->update($validated);

        return response()->json(['step' => $step]);
    }

    // DELETE /admin/programmes/{id}/steps/{stepId}
    public function destroyStep(int $id, int $stepId)
    {
        ProgrammeStep::where('programme_id', $id)->findOrFail($stepId)->delete();

        return response()->json(['message' => 'Étape supprimée.']);
    }

    /* ── Step partners (nested under a step) ─────────────────────────────── */

    // POST /admin/programmes/{id}/steps/{stepId}/partners
    public function storeStepPartner(Request $request, int $id, int $stepId)
    {
        $step = ProgrammeStep::where('programme_id', $id)->findOrFail($stepId);
        $validated = $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'price' => 'required|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $stepPartner = $step->stepPartners()->create($validated);

        return response()->json(['step_partner' => $stepPartner->load('partner')], 201);
    }

    // PUT /admin/programmes/{id}/steps/{stepId}/partners/{spId}
    public function updateStepPartner(Request $request, int $id, int $stepId, int $spId)
    {
        $stepPartner = ProgrammeStepPartner::where('programme_step_id', $stepId)->findOrFail($spId);
        $validated = $request->validate([
            'price' => 'sometimes|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $stepPartner->update($validated);

        return response()->json(['step_partner' => $stepPartner->load('partner')]);
    }

    // DELETE /admin/programmes/{id}/steps/{stepId}/partners/{spId}
    public function destroyStepPartner(int $id, int $stepId, int $spId)
    {
        ProgrammeStepPartner::where('programme_step_id', $stepId)->findOrFail($spId)->delete();

        return response()->json(['message' => 'Partenaire retiré de l\'étape.']);
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
            ->with(['user:id,first_name,last_name,email', 'departure', 'shares.partner'])
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
            'total_partner_net' => round($shares->sum('net_amount'), 2),
            'total_commission' => round($shares->sum('commission_amount'), 2),
            'reservations_count' => $reservations->count(),
            'by_partner' => $shares->groupBy('partner_id')->map(fn ($g) => [
                'partner_id' => $g->first()->partner_id,
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
            ->with(['user:id,first_name,last_name,email', 'departure', 'shares.partner'])
            ->orderBy('created_at')
            ->get();

        $rows = ["Reservation ID,Client,Email,Depart,Participants,Total,Statut,Partenaire,Part nette"];
        foreach ($reservations as $r) {
            $clientName = trim(($r->user->first_name ?? '').' '.($r->user->last_name ?? ''));
            if ($r->shares->isEmpty()) {
                $rows[] = "{$r->id},{$clientName},{$r->user->email},{$r->departure->start_date},{$r->participants_count},{$r->total_price},{$r->status},,";
                continue;
            }
            foreach ($r->shares as $share) {
                $rows[] = "{$r->id},{$clientName},{$r->user->email},{$r->departure->start_date},{$r->participants_count},{$r->total_price},{$r->status},{$share->partner->name},{$share->net_amount}";
            }
        }

        return response(implode("\n", $rows), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=programme-{$id}-export.csv",
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
