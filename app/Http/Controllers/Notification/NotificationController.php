<?php

namespace App\Http\Controllers\Notification;

use App\Models\Events;
use App\Models\Reservations_events;
use Illuminate\Support\Facades\Mail;
use App\Mail\EventReminderMail;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Messages;                
use App\Events\NotificationCreated;     
use Illuminate\Support\Facades\Auth;    

class NotificationController extends Controller
{
   public function sendRemindersForEvent(Events $event)
    {
        // Récupérer les réservations confirmées ET avec paiement payé
        $reservations = Reservations_events::where('event_id', $event->id)
            ->where('status', 'confirmé')  // bien avec l'accent
            ->whereHas('payment', function($query) {
                $query->where('status', 'paid');
            })
            ->with('user')  // pour charger l'utilisateur et son email
            ->get();

        if ($reservations->isEmpty()) {
            \Log::info("Aucune réservation confirmée et payée pour l'événement ID {$event->id}");
            return response()->json(['message' => 'Aucune réservation confirmée et payée pour cet événement']);
        }

        $sentCount = 0;

        foreach ($reservations as $reservation) {
            $user = $reservation->user;
            if (!$user) {
                \Log::warning("Réservation ID {$reservation->id} sans user associé.");
                continue;
            }
            if (empty($user->email)) {
                \Log::warning("Utilisateur ID {$user->id} sans email.");
                continue;
            }

            try {
                \Mail::to($user->email)->send(new \App\Mail\EventReminderMail($event));
                $sentCount++;
            } catch (\Exception $e) {
                \Log::error("Erreur envoi mail à {$user->email} : " . $e->getMessage());
            }
        }

        return response()->json(['message' => "Mails de rappel envoyés à {$sentCount} utilisateur(s)"]);
    }
    /**
     * Display a list of notifications (for the logged-in user or all if admin).
     */
    public function index()
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            if ($user->role === 'admin') {
                // Admin sees all notifications
                $notifications = Messages::orderBy('created_at', 'desc')->get();
            } else {
                // Normal user: their own + global
                $notifications = Messages::where(function ($query) use ($user) {
                        $query->where('target_id', $user->id)
                            ->orWhereNull('target_id');
                    })
                    ->orderBy('created_at', 'desc')
                    ->get();
            }

            return response()->json([
                'status'        => 'success',
                'count'         => $notifications->count(),
                'notifications' => $notifications
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to fetch notifications',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Store a new notification (admin creates).
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'target_id'       => 'nullable|exists:users,id',
                'type'            => 'required|in:system_alert,welcome_msg,payment_confirmation,status_update,support_ticket,invitaion',
                'contenu'         => 'required|string|max:255',
                'degree_urgence'  => 'required|in:low,medium,high,critical',
            ], [
                'type.required'           => 'Le type est obligatoire.',
                'type.in'                 => 'Le type doit être valide.',
                'contenu.required'        => 'Le contenu est obligatoire.',
                'contenu.max'             => 'Le contenu ne doit pas dépasser 255 caractères.',
                'degree_urgence.required' => 'Le degré d’urgence est obligatoire.',
                'degree_urgence.in'       => 'Le degré d’urgence doit être low, medium, high ou critical.',
            ]);

            $message = Messages::create([
                'sender_id'      => auth()->id(), // admin or logged user sending
                'target_id'      => $validated['target_id'] ?? null, // null = global
                'type'           => $validated['type'],
                'contenu'        => $validated['contenu'],
                'degree_urgence' => $validated['degree_urgence'],
                'is_read'        => false, // default unread
            ]);
            event(new \App\Events\NotificationCreated($message));
            return response()->json([
                'status'  => 'success',
                'message' => 'Notification créée avec succès.',
                'data'    => $message,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Erreur de validation.',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Une erreur est survenue lors de la création de la notification.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Show details of a single notification.
     */
    public function show($id)
    {
        try {
            $user = auth()->user();

            $notification = Messages::findOrFail($id);

            // Admins can view all, users only their own or global
            if ($user->role !== 'admin' && $notification->target_id !== null && $notification->target_id !== $user->id) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Unauthorized to view this notification.',
                ], 403);
            }

            return response()->json([
                'status'  => 'success',
                'data'    => $notification,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Notification not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to fetch notification.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead($id)
    {
        try {
            $user = auth()->user();

            $notification = Messages::findOrFail($id);

            // Check permission (admin or recipient)
            if ($user->role !== 'admin' && $notification->target_id !== $user->id && $notification->target_id !== null) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Unauthorized to update this notification.',
                ], 403);
            }

            $notification->is_read = true;
            $notification->save();

            return response()->json([
                'status'  => 'success',
                'message' => 'Notification marked as read.',
                'data'    => $notification,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Notification not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update notification.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark all notifications as read for the user.
     */
    public function markAllAsRead()
    {
        try {
            $user = auth()->user();

            $query = Messages::query();

            if ($user->role !== 'admin') {
                $query->where(function ($q) use ($user) {
                    $q->where('target_id', $user->id)
                    ->orWhereNull('target_id');
                });
            }

            $updatedCount = $query->update(['is_read' => true]);

            return response()->json([
                'status'        => 'success',
                'message'       => 'All notifications marked as read.',
                'updated_count' => $updatedCount,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update notifications.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a notification (admin only, or user clearing their own).
     */
    public function destroy($id)
    {
        try {
            $user = auth()->user();

            $notification = Messages::findOrFail($id);

            // Admin can delete any, users only their own
            if ($user->role !== 'admin' && $notification->target_id !== $user->id) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Unauthorized to delete this notification.',
                ], 403);
            }

            $notification->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Notification deleted successfully.',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Notification not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete notification.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

}
