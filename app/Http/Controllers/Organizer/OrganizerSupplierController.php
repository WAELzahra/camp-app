<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\InviteSupplierRequest;
use App\Http\Requests\Organizer\RequestSupplierLinkRequest;
use App\Http\Requests\Organizer\SearchSuppliersRequest;
use App\Models\Materielles;
use App\Models\OrganizerSupplierLink;
use App\Models\SupplierInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class OrganizerSupplierController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // ORGANIZER SIDE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * List all supplier associations for the authenticated organizer.
     */
    public function mySuppliers(Request $request)
    {
        $organizer = Auth::user();

        $links = OrganizerSupplierLink::where('organizer_id', $organizer->id)
            ->with(['supplier:id,first_name,last_name,email,avatar,phone_number'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($link) => [
                'id' => $link->id,
                'status' => $link->status,
                'message' => $link->message,
                'created_at' => $link->created_at,
                'responded_at' => $link->responded_at,
                'supplier' => $link->supplier,
            ]);

        return response()->json(['success' => true, 'data' => $links]);
    }

    /**
     * Get accepted suppliers with their available materials (for event booking display).
     */
    public function myAcceptedSuppliers()
    {
        $organizer = Auth::user();

        $links = OrganizerSupplierLink::where('organizer_id', $organizer->id)
            ->where('status', 'accepted')
            ->with([
                'supplier:id,first_name,last_name,email,avatar',
                'supplier.materielles' => fn ($q) => $q->where('status', 'up')
                    ->where('is_rentable', true)
                    ->with('photos', 'category'),
            ])
            ->get();

        return response()->json(['success' => true, 'data' => $links]);
    }

    /**
     * Search for existing supplier accounts to send association request.
     */
    public function searchSuppliers(SearchSuppliersRequest $request)
    {
        $request->validated();

        $organizer = Auth::user();
        $existingIds = OrganizerSupplierLink::where('organizer_id', $organizer->id)
            ->pluck('supplier_id');

        $suppliers = User::where('role_id', 4) // fournisseur
            ->where('is_active', true)
            ->whereNotIn('id', $existingIds)
            ->where(function ($q) use ($request) {
                $q->where('email', 'like', "%{$request->q}%")
                    ->orWhere('first_name', 'like', "%{$request->q}%")
                    ->orWhere('last_name', 'like', "%{$request->q}%");
            })
            ->select('id', 'first_name', 'last_name', 'email', 'avatar')
            ->limit(15)
            ->get();

        return response()->json(['success' => true, 'data' => $suppliers]);
    }

    /**
     * Directly associate an existing supplier (no approval required from supplier side).
     */
    public function requestLink(RequestSupplierLinkRequest $request)
    {
        $request->validated();

        $organizer = Auth::user();
        $supplier = User::findOrFail($request->supplier_id);

        if ($supplier->role_id !== 4) {
            return response()->json(['success' => false, 'message' => 'This user is not a supplier.'], 422);
        }

        $existing = OrganizerSupplierLink::where('organizer_id', $organizer->id)
            ->where('supplier_id', $supplier->id)
            ->first();

        if ($existing) {
            if ($existing->status === 'accepted') {
                return response()->json(['success' => false, 'message' => 'This supplier is already associated.'], 409);
            }
            // Any previous non-accepted record for same supplier → upgrade to accepted
            // But first check the one-supplier limit (excluding this record)
            $otherAccepted = OrganizerSupplierLink::where('organizer_id', $organizer->id)
                ->where('status', 'accepted')
                ->where('id', '!=', $existing->id)
                ->exists();
            if ($otherAccepted) {
                return response()->json(['success' => false, 'message' => 'You already have an associated supplier. Remove them first.'], 409);
            }
            $existing->update(['status' => 'accepted', 'responded_at' => now()]);

            return response()->json(['success' => true, 'message' => 'Supplier associated.', 'data' => $existing]);
        }

        // One-supplier limit: reject if an accepted link already exists
        $alreadyHasSupplier = OrganizerSupplierLink::where('organizer_id', $organizer->id)
            ->where('status', 'accepted')
            ->exists();
        if ($alreadyHasSupplier) {
            return response()->json(['success' => false, 'message' => 'You already have an associated supplier. Remove them first.'], 409);
        }

        $link = OrganizerSupplierLink::create([
            'organizer_id' => $organizer->id,
            'supplier_id' => $supplier->id,
            'status' => 'accepted',
            'responded_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Supplier associated.', 'data' => $link], 201);
    }

    /**
     * Invite a supplier by email (they don't have an account yet).
     */
    public function inviteSupplier(InviteSupplierRequest $request)
    {
        $request->validated();

        $organizer = Auth::user();

        // Check if email already has an account
        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser && $existingUser->role_id === 4) {
            return response()->json([
                'success' => false,
                'message' => 'This email is already registered as a supplier. Use "request link" instead.',
                'supplier' => $existingUser->only('id', 'first_name', 'last_name', 'email'),
            ], 409);
        }

        // Check for existing pending invitation
        $existing = SupplierInvitation::where('organizer_id', $organizer->id)
            ->where('email', $request->email)
            ->where('status', 'pending')
            ->first();

        if ($existing && !$existing->isExpired()) {
            return response()->json(['success' => false, 'message' => 'An invitation has already been sent to this email.'], 409);
        }

        $invitation = SupplierInvitation::create([
            'organizer_id' => $organizer->id,
            'email' => $request->email,
            'status' => 'pending',
            'token' => SupplierInvitation::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        // Send invitation email
        try {
            Mail::to($request->email)->send(new \App\Mail\SupplierInvitationMail($invitation, $organizer, $request->message));
        } catch (\Exception $e) {
            \Log::warning('Failed to send supplier invitation email: '.$e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Invitation sent.', 'data' => $invitation], 201);
    }

    /**
     * Cancel an association request or active link (organizer side).
     */
    public function cancelLink($linkId)
    {
        $organizer = Auth::user();

        $link = OrganizerSupplierLink::where('id', $linkId)
            ->where('organizer_id', $organizer->id)
            ->firstOrFail();

        $link->update(['status' => 'cancelled', 'responded_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Association cancelled.']);
    }

    /**
     * Get materials available from a specific accepted supplier.
     */
    public function supplierMaterials($supplierId)
    {
        $organizer = Auth::user();

        $link = OrganizerSupplierLink::where('organizer_id', $organizer->id)
            ->where('supplier_id', $supplierId)
            ->where('status', 'accepted')
            ->firstOrFail();

        $materials = Materielles::where('fournisseur_id', $supplierId)
            ->where('status', 'up')
            ->where('is_rentable', true)
            ->where('quantite_dispo', '>', 0)
            ->with(['photos', 'category'])
            ->get();

        return response()->json(['success' => true, 'data' => $materials]);
    }

    /**
     * Get all materials from all accepted suppliers (for event booking flow).
     */
    public function allAssociatedMaterials()
    {
        $organizer = Auth::user();

        $supplierIds = OrganizerSupplierLink::where('organizer_id', $organizer->id)
            ->where('status', 'accepted')
            ->pluck('supplier_id');

        $materials = Materielles::whereIn('fournisseur_id', $supplierIds)
            ->where('status', 'up')
            ->where('is_rentable', true)
            ->where('quantite_dispo', '>', 0)
            ->with(['photos', 'category', 'fournisseur:id,first_name,last_name,avatar'])
            ->get();

        return response()->json(['success' => true, 'data' => $materials]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SUPPLIER SIDE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * List association requests received by the authenticated supplier.
     */
    public function myRequests(Request $request)
    {
        $supplier = Auth::user();

        $links = OrganizerSupplierLink::where('supplier_id', $supplier->id)
            ->with(['organizer:id,first_name,last_name,email,avatar'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($link) => [
                'id' => $link->id,
                'status' => $link->status,
                'message' => $link->message,
                'created_at' => $link->created_at,
                'responded_at' => $link->responded_at,
                'organizer' => $link->organizer,
            ]);

        return response()->json(['success' => true, 'data' => $links]);
    }

    /**
     * Accept an association request (supplier side).
     */
    public function acceptRequest($linkId)
    {
        $supplier = Auth::user();

        $link = OrganizerSupplierLink::where('id', $linkId)
            ->where('supplier_id', $supplier->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $link->update(['status' => 'accepted', 'responded_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Association accepted.', 'data' => $link]);
    }

    /**
     * Reject an association request (supplier side).
     */
    public function rejectRequest($linkId)
    {
        $supplier = Auth::user();

        $link = OrganizerSupplierLink::where('id', $linkId)
            ->where('supplier_id', $supplier->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $link->update(['status' => 'rejected', 'responded_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Association rejected.']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PUBLIC — used when camper is booking an event
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Get available materials from organizer's accepted suppliers for a specific event.
     * Used in the event booking flow (Step 2 — optional equipment).
     */
    public function eventMaterials($eventId)
    {
        $event = \App\Models\Events::findOrFail($eventId);

        $supplierIds = OrganizerSupplierLink::where('organizer_id', $event->group_id)
            ->where('status', 'accepted')
            ->pluck('supplier_id');

        if ($supplierIds->isEmpty()) {
            return response()->json(['success' => true, 'data' => [], 'has_suppliers' => false]);
        }

        $materials = Materielles::whereIn('fournisseur_id', $supplierIds)
            ->where('status', 'up')
            ->where('is_rentable', true)
            ->where('quantite_dispo', '>', 0)
            ->with(['photos', 'category', 'fournisseur:id,first_name,last_name,avatar'])
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'nom' => $m->nom,
                'description' => $m->description,
                'tarif_nuit' => $m->tarif_nuit,
                'quantite_dispo' => $m->quantite_dispo,
                'supplier_id' => $m->fournisseur_id,
                'supplier' => $m->fournisseur,
                'category' => $m->category,
                'photos' => $m->photos,
            ]);

        return response()->json(['success' => true, 'data' => $materials, 'has_suppliers' => true]);
    }

    /**
     * Public: return accepted suppliers for a given organizer (no auth required).
     */
    public function publicAcceptedSuppliers($userId)
    {
        $links = OrganizerSupplierLink::where('organizer_id', $userId)
            ->where('status', 'accepted')
            ->with(['supplier:id,uuid,first_name,last_name,email,avatar'])
            ->get()
            ->map(fn ($link) => [
                'id' => $link->supplier?->id,
                'uuid' => $link->supplier?->uuid,
                'name' => trim(($link->supplier?->first_name ?? '').' '.($link->supplier?->last_name ?? '')),
                'email' => $link->supplier?->email,
                'avatar' => $link->supplier?->avatar,
            ])
            ->filter(fn ($item) => $item['id'] !== null)
            ->values();

        return response()->json(['success' => true, 'data' => $links]);
    }

    /**
     * Validate invitation token (called when a supplier registers via invite link).
     */
    public function validateInvitationToken($token)
    {
        $invitation = SupplierInvitation::where('token', $token)
            ->where('status', 'pending')
            ->first();

        if (!$invitation || $invitation->isExpired()) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired invitation.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'email' => $invitation->email,
                'organizer_name' => optional($invitation->organizer)->first_name,
                'expires_at' => $invitation->expires_at,
            ],
        ]);
    }
}
