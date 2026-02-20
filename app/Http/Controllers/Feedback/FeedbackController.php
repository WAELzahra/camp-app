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
    /**
     * Get all feedbacks with filters (for admin/camper dashboard)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $userRole = $user->role_id ?? null;
        
        $query = Feedbacks::with(['user', 'user_target', 'event', 'zone']);
        
        // Filter by user role
        if ($userRole == 1) { // Camper - see their own feedbacks
            $query->where('user_id', $user->id);
        }
        
        // Apply filters from request
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }
        
        if ($request->has('target_id') && $request->target_id) {
            $query->where('target_id', $request->target_id);
        }
        
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('contenu', 'like', "%{$search}%")
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }
        
        // Date range filters
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
        
        // Pagination
        $perPage = $request->get('per_page', 15);
        $feedbacks = $query->paginate($perPage);
        
        // Transform data for frontend
        $feedbacks->getCollection()->transform(function($feedback) {
            return $this->formatFeedback($feedback);
        });
        
        return response()->json([
            'success' => true,
            'feedbacks' => $feedbacks,
            'filters' => [
                'statuses' => $this->getStatusCounts(),
                'types' => $this->getTypeCounts(),
            ]
        ]);
    }
    
    /**
     * Get feedbacks for a specific target (guide, centre, fournisseur, etc.)
     */
    public function getTargetFeedbacks($type, $targetId)
    {
        $allowedTypes = ['guide', 'groupe', 'fournisseur', 'centre', 'zone', 'event'];
        
        if (!in_array($type, $allowedTypes)) {
            return response()->json([
                'success' => false,
                'message' => 'Type de cible non valide.'
            ], 400);
        }
        
        $query = Feedbacks::with('user')
            ->where('type', $type)
            ->where('status', 'approved')
            ->latest();
        
        // Map type to the correct field
        if ($type === 'zone') {
            $query->where('zone_id', $targetId);
        } elseif ($type === 'event') {
            $query->where('event_id', $targetId);
        } else {
            $query->where('target_id', $targetId);
        }
        
        $feedbacks = $query->get();
        
        $avg = Feedbacks::where('type', $type)
            ->where('status', 'approved');
            
        if ($type === 'zone') {
            $avg->where('zone_id', $targetId);
        } elseif ($type === 'event') {
            $avg->where('event_id', $targetId);
        } else {
            $avg->where('target_id', $targetId);
        }
        
        $averageNote = $avg->avg('note');
        
        // Transform feedbacks
        $formattedFeedbacks = $feedbacks->map(function($feedback) {
            return $this->formatFeedback($feedback);
        });
        
        return response()->json([
            'success' => true,
            'average_note' => $averageNote ? round($averageNote, 1) : null,
            'count' => $feedbacks->count(),
            'feedbacks' => $formattedFeedbacks,
        ]);
    }
    
    /**
     * Store a new feedback
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Validation rules based on type
        $rules = [
            'type' => 'required|in:guide,groupe,fournisseur,centre,zone,event',
            'note' => 'required|integer|min:1|max:5',
            'contenu' => 'nullable|string|max:1000',
        ];
        
        // Add conditional validation based on type
        switch ($request->type) {
            case 'zone':
                $rules['zone_id'] = 'required|exists:camping_zones,id';
                break;
            case 'event':
                $rules['event_id'] = 'required|exists:events,id';
                break;
            default:
                $rules['target_id'] = 'required|exists:users,id';
                break;
        }
        
        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Check if user already submitted feedback for this target
        $existingQuery = Feedbacks::where('user_id', $user->id)
            ->where('type', $request->type);
        
        switch ($request->type) {
            case 'zone':
                $existingQuery->where('zone_id', $request->zone_id);
                break;
            case 'event':
                $existingQuery->where('event_id', $request->event_id);
                break;
            default:
                $existingQuery->where('target_id', $request->target_id);
                break;
        }
        
        $existing = $existingQuery->first();
        
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà soumis un avis pour cette cible.',
                'feedback' => $this->formatFeedback($existing)
            ], 400);
        }
        
        // Create feedback
        $feedbackData = [
            'user_id' => $user->id,
            'type' => $request->type,
            'note' => $request->note,
            'contenu' => $request->contenu,
            'status' => 'pending',
        ];
        
        // Add specific IDs based on type
        switch ($request->type) {
            case 'zone':
                $feedbackData['zone_id'] = $request->zone_id;
                break;
            case 'event':
                $feedbackData['event_id'] = $request->event_id;
                break;
            default:
                $feedbackData['target_id'] = $request->target_id;
                break;
        }
        
        $feedback = Feedbacks::create($feedbackData);
        
        return response()->json([
            'success' => true,
            'message' => 'Votre avis a été soumis avec succès. Il sera publié après validation.',
            'feedback' => $this->formatFeedback($feedback->load('user'))
        ], 201);
    }
    
    /**
     * Update a feedback
     */
    public function update(Request $request, $id)
    {
        $feedback = Feedbacks::findOrFail($id);
        $user = Auth::user();
        
        // Check authorization (owner or admin)
        if ($feedback->user_id !== $user->id && $user->role_id !== 6) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'note' => 'sometimes|integer|min:1|max:5',
            'contenu' => 'nullable|string|max:1000',
            'response' => 'nullable|string|max:1000',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Update fields
        if ($request->has('note')) {
            $feedback->note = $request->note;
        }
        
        if ($request->has('contenu')) {
            $feedback->contenu = $request->contenu;
        }
        
        // Only admins can update response and status
        if ($user->role_id === 6) {
            if ($request->has('response')) {
                $feedback->response = $request->response;
            }
            
            if ($request->has('status')) {
                $feedback->status = $request->status;
            }
        } else {
            // Reset to pending if content changed by owner
            $feedback->status = 'pending';
        }
        
        $feedback->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Feedback updated successfully!',
            'feedback' => $this->formatFeedback($feedback->load('user'))
        ]);
    }
    
    /**
     * Delete a feedback
     */
    public function destroy($id)
    {
        $feedback = Feedbacks::findOrFail($id);
        $user = Auth::user();
        
        // Check authorization (owner or admin)
        if ($feedback->user_id !== $user->id && $user->role_id !== 6) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.'
            ], 403);
        }
        
        $feedback->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Feedback deleted successfully!'
        ]);
    }
    
    /**
     * Moderate feedback (admin only)
     */
    public function moderate(Request $request, $id)
    {
        $user = Auth::user();
        
        if ($user->role_id !== 6) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.'
            ], 403);
        }
        
        $feedback = Feedbacks::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected',
            'response' => 'nullable|string|max:1000',
            'rejection_reason' => 'required_if:status,rejected|string|max:500'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $feedback->status = $request->status;
        
        if ($request->has('response')) {
            $feedback->response = $request->response;
        }
        
        // Store rejection reason (you may want to add this field to your table)
        if ($request->status === 'rejected' && $request->has('rejection_reason')) {
            // You can add a 'rejection_reason' column to your feedbacks table
            $feedback->rejection_reason = $request->rejection_reason;
        }
        
        $feedback->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Feedback ' . ($request->status === 'approved' ? 'approuvé' : 'rejeté') . ' avec succès.',
            'feedback' => $this->formatFeedback($feedback)
        ]);
    }
    
    /**
     * Get feedback statistics
     */
    public function statistics()
    {
        $user = Auth::user();
        
        $stats = [
            'total' => Feedbacks::count(),
            'pending' => Feedbacks::where('status', 'pending')->count(),
            'approved' => Feedbacks::where('status', 'approved')->count(),
            'rejected' => Feedbacks::where('status', 'rejected')->count(),
            'by_type' => [
                'guide' => Feedbacks::where('type', 'guide')->count(),
                'groupe' => Feedbacks::where('type', 'groupe')->count(),
                'fournisseur' => Feedbacks::where('type', 'fournisseur')->count(),
                'centre' => Feedbacks::where('type', 'centre')->count(),
                'zone' => Feedbacks::where('type', 'zone')->count(),
                'event' => Feedbacks::where('type', 'event')->count(),
            ],
            'average_rating' => round(Feedbacks::where('status', 'approved')->avg('note'), 1),
        ];
        
        // If user is camper, add their personal stats
        if ($user->role_id === 1) {
            $stats['my_feedbacks'] = Feedbacks::where('user_id', $user->id)->count();
            $stats['my_average'] = round(Feedbacks::where('user_id', $user->id)
                ->where('status', 'approved')
                ->avg('note'), 1);
        }
        
        return response()->json([
            'success' => true,
            'statistics' => $stats
        ]);
    }
    
    /**
     * Get feedback counts by status for filter badges
     */
    private function getStatusCounts()
    {
        return [
            'pending' => Feedbacks::where('status', 'pending')->count(),
            'approved' => Feedbacks::where('status', 'approved')->count(),
            'rejected' => Feedbacks::where('status', 'rejected')->count(),
        ];
    }
    
    /**
     * Get feedback counts by type for filter badges
     */
    private function getTypeCounts()
    {
        return [
            'guide' => Feedbacks::where('type', 'guide')->count(),
            'groupe' => Feedbacks::where('type', 'groupe')->count(),
            'fournisseur' => Feedbacks::where('type', 'fournisseur')->count(),
            'centre' => Feedbacks::where('type', 'centre')->count(),
            'zone' => Feedbacks::where('type', 'zone')->count(),
            'event' => Feedbacks::where('type', 'event')->count(),
        ];
    }
    
    /**
     * Format feedback for frontend
     */
    private function formatFeedback($feedback)
    {
        $formatted = [
            'id' => $feedback->id,
            'user_id' => $feedback->user_id,
            'user_name' => $feedback->user ? $feedback->user->first_name . ' ' . $feedback->user->last_name : null,
            'user_email' => $feedback->user ? $feedback->user->email : null,
            'target_id' => $feedback->target_id,
            'event_id' => $feedback->event_id,
            'zone_id' => $feedback->zone_id,
            'contenu' => $feedback->contenu,
            'response' => $feedback->response,
            'note' => $feedback->note,
            'type' => $feedback->type,
            'status' => $feedback->status,
            'created_at' => $feedback->created_at,
            'updated_at' => $feedback->updated_at,
        ];
        
        // Add target details based on type
        switch ($feedback->type) {
            case 'zone':
                if ($feedback->zone) {
                    $formatted['target_name'] = $feedback->zone->nom;
                    $formatted['target_detail'] = 'Zone: ' . $feedback->zone->nom;
                }
                break;
                
            case 'event':
                if ($feedback->event) {
                    $formatted['target_name'] = $feedback->event->titre;
                    $formatted['target_detail'] = 'Event: ' . $feedback->event->titre;
                }
                break;
                
            default:
                if ($feedback->target) {
                    $formatted['target_name'] = $feedback->target->first_name . ' ' . $feedback->target->last_name;
                    $formatted['target_detail'] = ucfirst($feedback->type) . ': ' . $formatted['target_name'];
                }
                break;
        }
        
        return $formatted;
    }
    
    // Keep your existing methods for backward compatibility
    public function storeOrUpdateFeedback(Request $request, $type, $targetId)
    {
        // This can now call the new store method
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
}