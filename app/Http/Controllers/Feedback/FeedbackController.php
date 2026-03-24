<?php

namespace App\Http\Controllers\feedback;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Feedbacks;
use App\Models\User;
use App\Models\Events;
use App\Models\CampingZones;
use App\Http\Controllers\Controller;

class FeedbackController extends Controller
{
    // ── Allowed types (single source of truth) ────────────────────────────────
    private const ALLOWED_TYPES = ['guide', 'groupe', 'fournisseur', 'centre', 'zone', 'event', 'materielle'];

    /**
     * Get all feedbacks with filters (admin / camper dashboard).
     */
    public function index(Request $request)
    {
        $user     = Auth::user();
        $userRole = $user->role_id ?? null;

        $query = Feedbacks::with(['user', 'user_target', 'event', 'zone', 'materielle']);

        if ($userRole == 1) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status'))    $query->where('status', $request->status);
        if ($request->filled('type'))      $query->where('type', $request->type);
        if ($request->filled('target_id')) $query->where('target_id', $request->target_id);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('contenu', 'like', "%{$s}%")
                  ->orWhereHas('user', fn ($u) =>
                      $u->where('first_name', 'like', "%{$s}%")
                        ->orWhere('last_name',  'like', "%{$s}%")
                  );
            });
        }

        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to'))   $query->whereDate('created_at', '<=', $request->date_to);

        $query->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_order', 'desc'));

        $feedbacks = $query->paginate($request->get('per_page', 15));
        $feedbacks->getCollection()->transform(fn ($f) => $this->formatFeedback($f));

        return response()->json([
            'success'   => true,
            'feedbacks' => $feedbacks,
            'filters'   => [
                'statuses' => $this->getStatusCounts(),
                'types'    => $this->getTypeCounts(),
            ],
        ]);
    }

    /**
     * Get feedbacks for a specific target.
     */
    public function getTargetFeedbacks($type, $targetId)
    {
        if (!in_array($type, self::ALLOWED_TYPES)) {
            return response()->json(['success' => false, 'message' => 'Type de cible non valide.'], 400);
        }

        $query = Feedbacks::with(['user', 'user_target', 'zone', 'event', 'materielle'])
            ->where('type', $type)
            ->where('status', 'approved')
            ->latest();

        $query = $this->applyTargetFilter($query, $type, $targetId);

        $feedbacks    = $query->get();
        $averageNote  = $this->applyTargetFilter(
            Feedbacks::where('type', $type)->where('status', 'approved'),
            $type, $targetId
        )->avg('note');

        return response()->json([
            'success'      => true,
            'average_note' => $averageNote ? round($averageNote, 1) : null,
            'count'        => $feedbacks->count(),
            'feedbacks'    => $feedbacks->map(fn ($f) => $this->formatFeedback($f)),
        ]);
    }

    /**
     * Store a new feedback.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $rules = [
            'type'    => 'required|in:' . implode(',', self::ALLOWED_TYPES),
            'note'    => 'required|integer|min:1|max:5',
            'contenu' => 'nullable|string|max:1000',
        ];

        // Target ID validation per type
        switch ($request->type) {
            case 'zone':        $rules['zone_id']        = 'required|exists:camping_zones,id'; break;
            case 'event':       $rules['event_id']       = 'required|exists:events,id';        break;
            case 'materielle':  $rules['materielle_id']  = 'required|exists:materielles,id';   break;
            default:            $rules['target_id']      = 'required|exists:users,id';         break;
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Duplicate check
        $existing = $this->applyTargetFilter(
            Feedbacks::where('user_id', $user->id)->where('type', $request->type),
            $request->type,
            $this->getTargetIdFromRequest($request)
        )->first();

        if ($existing) {
            return response()->json([
                'success'  => false,
                'message'  => 'Vous avez déjà soumis un avis pour cette cible.',
                'feedback' => $this->formatFeedback($existing),
            ], 400);
        }

        $feedbackData = [
            'user_id' => $user->id,
            'type'    => $request->type,
            'note'    => $request->note,
            'contenu' => $request->contenu,
            'status'  => 'pending',
        ];

        switch ($request->type) {
            case 'zone':       $feedbackData['zone_id']       = $request->zone_id;       break;
            case 'event':      $feedbackData['event_id']      = $request->event_id;      break;
            case 'materielle': $feedbackData['materielle_id'] = $request->materielle_id; break;
            default:           $feedbackData['target_id']     = $request->target_id;     break;
        }

        $feedback = Feedbacks::create($feedbackData);

        return response()->json([
            'success'  => true,
            'message'  => 'Votre avis a été soumis avec succès. Il sera publié après validation.',
            'feedback' => $this->formatFeedback($feedback->load('user')),
        ], 201);
    }

    /**
     * Update a feedback.
     */
    public function update(Request $request, $id)
    {
        $feedback = Feedbacks::findOrFail($id);
        $user     = Auth::user();

        if ($feedback->user_id !== $user->id && $user->role_id !== 6) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'note'    => 'sometimes|integer|min:1|max:5',
            'contenu' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->has('note'))    $feedback->note    = $request->note;
        if ($request->has('contenu')) $feedback->contenu = $request->contenu;

        if ($user->role_id === 6) {
            if ($request->has('response')) $feedback->response = $request->response;
            if ($request->has('status'))   $feedback->status   = $request->status;
        } else {
            $feedback->status = 'pending';
        }

        $feedback->save();

        return response()->json([
            'success'  => true,
            'message'  => 'Feedback updated successfully!',
            'feedback' => $this->formatFeedback($feedback->load('user')),
        ]);
    }

    /**
     * Delete a feedback.
     */
    public function destroy($id)
    {
        $feedback = Feedbacks::findOrFail($id);
        $user     = Auth::user();

        if ($feedback->user_id !== $user->id && $user->role_id !== 6) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $feedback->delete();
        return response()->json(['success' => true, 'message' => 'Feedback deleted successfully!']);
    }

    /**
     * Moderate feedback (admin only).
     */
    public function moderate(Request $request, $id)
    {
        $user = Auth::user();
        if ($user->role_id !== 6) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $feedback  = Feedbacks::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'status'           => 'required|in:approved,rejected',
            'response'         => 'nullable|string|max:1000',
            'rejection_reason' => 'required_if:status,rejected|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $feedback->status = $request->status;
        if ($request->has('response'))         $feedback->response         = $request->response;
        if ($request->status === 'rejected' && $request->has('rejection_reason'))
            $feedback->rejection_reason = $request->rejection_reason;

        $feedback->save();

        return response()->json([
            'success'  => true,
            'message'  => 'Feedback ' . ($request->status === 'approved' ? 'approuvé' : 'rejeté') . ' avec succès.',
            'feedback' => $this->formatFeedback($feedback),
        ]);
    }

    /**
     * Get feedback statistics.
     */
    public function statistics()
    {
        $user  = Auth::user();
        $stats = [
            'total'    => Feedbacks::count(),
            'pending'  => Feedbacks::where('status', 'pending')->count(),
            'approved' => Feedbacks::where('status', 'approved')->count(),
            'rejected' => Feedbacks::where('status', 'rejected')->count(),
            'by_type'  => [
                'guide'       => Feedbacks::where('type', 'guide')->count(),
                'groupe'      => Feedbacks::where('type', 'groupe')->count(),
                'fournisseur' => Feedbacks::where('type', 'fournisseur')->count(),
                'centre'      => Feedbacks::where('type', 'centre')->count(),
                'zone'        => Feedbacks::where('type', 'zone')->count(),
                'event'       => Feedbacks::where('type', 'event')->count(),
                'materielle'  => Feedbacks::where('type', 'materielle')->count(),
            ],
            'average_rating' => round(Feedbacks::where('status', 'approved')->avg('note'), 1),
        ];

        if ($user->role_id === 1) {
            $stats['my_feedbacks'] = Feedbacks::where('user_id', $user->id)->count();
            $stats['my_average']   = round(
                Feedbacks::where('user_id', $user->id)->where('status', 'approved')->avg('note'), 1
            );
        }

        return response()->json(['success' => true, 'statistics' => $stats]);
    }

    // ── Backward-compat wrappers ──────────────────────────────────────────────

    public function storeOrUpdateFeedback(Request $request, $type, $targetId)
    {
        $request->merge(['type' => $type, 'target_id' => $targetId]);
        return $this->store($request);
    }

    public function getFeedbacks($type, $targetId)
    {
        return $this->getTargetFeedbacks($type, $targetId);
    }

    public function storeZone(Request $request, $zoneId)
    {
        $request->merge(['type' => 'zone', 'zone_id' => $zoneId]);
        return $this->store($request);
    }

    public function listZone($zoneId)
    {
        return $this->getTargetFeedbacks('zone', $zoneId);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /** Apply the correct WHERE clause for each feedback type. */
    private function applyTargetFilter($query, string $type, $targetId)
    {
        return match ($type) {
            'zone'       => $query->where('zone_id',       $targetId),
            'event'      => $query->where('event_id',      $targetId),
            'materielle' => $query->where('materielle_id', $targetId),
            default      => $query->where('target_id',     $targetId),
        };
    }

    /** Extract the target ID from the request based on the feedback type. */
    private function getTargetIdFromRequest(Request $request): mixed
    {
        return match ($request->type) {
            'zone'       => $request->zone_id,
            'event'      => $request->event_id,
            'materielle' => $request->materielle_id,
            default      => $request->target_id,
        };
    }

    private function getStatusCounts(): array
    {
        return [
            'pending'  => Feedbacks::where('status', 'pending')->count(),
            'approved' => Feedbacks::where('status', 'approved')->count(),
            'rejected' => Feedbacks::where('status', 'rejected')->count(),
        ];
    }

    private function getTypeCounts(): array
    {
        return [
            'guide'       => Feedbacks::where('type', 'guide')->count(),
            'groupe'      => Feedbacks::where('type', 'groupe')->count(),
            'fournisseur' => Feedbacks::where('type', 'fournisseur')->count(),
            'centre'      => Feedbacks::where('type', 'centre')->count(),
            'zone'        => Feedbacks::where('type', 'zone')->count(),
            'event'       => Feedbacks::where('type', 'event')->count(),
            'materielle'  => Feedbacks::where('type', 'materielle')->count(),
        ];
    }
    private function formatFeedback($feedback): array
    {
        $formatted = [
            'id'           => $feedback->id,
            'user_id'      => $feedback->user_id,
            'user'         => $feedback->user ? [
                'id'         => $feedback->user->id,
                'first_name' => $feedback->user->first_name,
                'last_name'  => $feedback->user->last_name,
                'email'      => $feedback->user->email,
                'avatar'     => $feedback->user->avatar ? asset('storage/' . $feedback->user->avatar) : null,                'role_id'    => $feedback->user->role_id,
                'ville'      => $feedback->user->ville,
                'is_active'  => $feedback->user->is_active,
            ] : null,
            'target_id'    => $feedback->target_id,
            'event_id'     => $feedback->event_id,
            'zone_id'      => $feedback->zone_id,
            'materielle_id'=> $feedback->materielle_id,
            'contenu'      => $feedback->contenu,
            'response'     => $feedback->response,
            'note'         => $feedback->note,
            'type'         => $feedback->type,
            'status'       => $feedback->status,
            'created_at'   => $feedback->created_at,
            'updated_at'   => $feedback->updated_at,
        ];

        // Add target info for clickable profiles
        if ($feedback->target_id) {
            $targetUser = $feedback->user_target;
            if ($targetUser) {
                $formatted['target'] = [
                    'id'         => $targetUser->id,
                    'first_name' => $targetUser->first_name,
                    'last_name'  => $targetUser->last_name,
                    'avatar'     => $targetUser->avatar ? asset('storage/' . $targetUser->avatar) : null,
                    'role_id'    => $targetUser->role_id,
                ];
            }
        }

        // Add target name for display (backward compatibility)
        switch ($feedback->type) {
            case 'zone':
                if ($feedback->zone) {
                    $formatted['target_name']   = $feedback->zone->nom;
                    $formatted['target_detail'] = 'Zone: ' . $feedback->zone->nom;
                }
                break;
            case 'event':
                if ($feedback->event) {
                    $formatted['target_name']   = $feedback->event->titre;
                    $formatted['target_detail'] = 'Event: ' . $feedback->event->titre;
                }
                break;
            case 'materielle':
                if ($feedback->materielle) {
                    $formatted['target_name']   = $feedback->materielle->nom;
                    $formatted['target_detail'] = 'Materielle: ' . $feedback->materielle->nom;
                }
                break;
            default:
                if ($feedback->user_target) {
                    $name = $feedback->user_target->first_name . ' ' . $feedback->user_target->last_name;
                    $formatted['target_name']   = $name;
                    $formatted['target_detail'] = ucfirst($feedback->type) . ': ' . $name;
                }
                break;
        }

        return $formatted;
    }
}