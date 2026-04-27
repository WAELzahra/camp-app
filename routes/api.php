<?php
// routes/api.php

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Controller;

// Authentication Controllers
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;

// Public Controllers
use App\Http\Controllers\Annonce\AnnonceController;
use App\Http\Controllers\Boutique\BoutiqueController;
use App\Http\Controllers\Boutique\AdminBoutiqueController;
use App\Http\Controllers\Materielle\MaterielleController;
use App\Http\Controllers\Feedback\FeedbackController;
use App\Http\Controllers\Event\EventController;
use App\Http\Controllers\Groupe\GroupController;
use App\Http\Controllers\zonecamping\CampingZonesController;
use App\Http\Controllers\zonecamping\CampingCentresController;
use App\Http\Controllers\zonecamping\PublicCampingController;
use App\Http\Controllers\Api\CenterServiceApiController;
use App\Http\Controllers\Comment\CommentController;

// Authenticated Controllers
use App\Http\Controllers\profile\ProfileController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Favoris\FavorisController;
use App\Http\Controllers\Favorites\FavoriteController;
use App\Http\Controllers\Message\ConversationController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Message\MessageController;

// Event Controllers
use App\Http\Controllers\Event\EventInterestController;
use App\Http\Controllers\Event\EventParticipantController;
use App\Http\Controllers\Reservation\ReservationEventController;
use App\Http\Controllers\Reservation\ReservationCancellationController;
use App\Http\Controllers\Reservation\UnifiedReservationController;
use App\Http\Controllers\Groupe\FollowGroupeController;
use App\Http\Controllers\Groupe\GroupeCoOwnerController;

// Payment Controller
use App\Http\Controllers\Payment\PaymentController;

// Camping Controllers
use App\Http\Controllers\zonecamping\ZonePolygonController;
use App\Http\Controllers\Signal\SignalementZoneController;

// Reservation Centre Controllers
use App\Http\Controllers\Reservation\ReservationsCentreController;
use App\Http\Controllers\Reservation\ReservationMaterielleController;

// Admin Controllers
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminEventController;
use App\Http\Controllers\Admin\AdminEventReservationController;
use App\Http\Controllers\Admin\AdminFeedbackController;
use App\Http\Controllers\Admin\SignaleZoneController;
use App\Http\Controllers\Admin\CampingCentreController;
use App\Http\Controllers\Admin\CampingZoneController;
use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\AdminReservationsController;
use App\Http\Controllers\PromoCodeController;
use App\Http\Controllers\PopupController;
use App\Http\Controllers\Admin\AdminEventsController;
use App\Http\Controllers\Admin\AdminAnnonceController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminExpenseController;
use App\Http\Controllers\ExpenseController;

use Illuminate\Support\Facades\Broadcast;

// Report & Contact Controllers
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\Contact\ContactController;

// Centre Claim Controller
use App\Http\Controllers\CentreClaimController;
use App\Http\Controllers\Dashboard\DashboardController;

// ==================== BROADCAST ROUTES ====================
Broadcast::routes(['middleware' => ['auth:sanctum']]);

// ==================== PUBLIC ROUTES (NO AUTH REQUIRED) ====================

// -------------------- AUTHENTICATION & VERIFICATION --------------------
Route::get('/csrf-token', function () {
    return response()->json(['csrf_token' => csrf_token()]);
});

Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login');
Route::post('/forgot-password', [NewPasswordController::class, 'sendResetEmail']);
Route::post('/reset-password', [NewPasswordController::class, 'resetPassword']);
Route::post('/verify-password', [NewPasswordController::class, 'verifyResetCode']);

// Email Verification
Route::post('/send-verification', [VerifyEmailController::class, 'sendVerification']);
Route::post('/verify-by-token', [VerifyEmailController::class, 'verifyByToken']);
Route::post('/verify-by-code', [VerifyEmailController::class, 'verifyByCode']);
Route::post('/resend-verification', [VerifyEmailController::class, 'resendVerification']);
Route::get('/verification-status/{id}', [VerifyEmailController::class, 'verificationStatus']);

// Email Verification Signed Route
Route::middleware('signed')->get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();
    return response()->json(['message' => 'Email verified successfully']);
})->name('verification.verify');
Route::middleware('auth:sanctum')->get('/annonces/user-likes', [AnnonceController::class, 'getUserLikes']);

// -------------------- CONTACT FORM --------------------
Route::middleware('throttle:10,1')->post('/contact', [ContactController::class, 'store']);

// -------------------- PUBLIC SETTINGS (withdrawal days etc.) --------------------
Route::get('/settings/public', [\App\Http\Controllers\Admin\AdminSettingsController::class, 'publicSettings']);

// -------------------- PLATFORM STATS (landing page) --------------------
Route::get('/platform/stats', function () {
    $reservations = \DB::table('reservations_centres')->count()
        + \DB::table('reservations_events')->count()
        + \DB::table('reservations_materielles')->count();

    return response()->json(['data' => [
        'total_users'        => \App\Models\User::count(),
        'total_centres'      => \DB::table('camping_centres')->count(),
        'total_events'       => \DB::table('events')->count(),
        'total_reservations' => $reservations,
    ]]);
});

// -------------------- REPORTS (public submission, auth optional) --------------------
Route::middleware('throttle:5,1')->post('/reports', [ReportController::class, 'store']);

// -------------------- PUBLIC ANNOUNCEMENTS --------------------
Route::prefix('annonces')->group(function () {
    Route::get('/all', [AnnonceController::class, 'getAll']);
    
    Route::get('/{id}', [AnnonceController::class, 'show']);
    Route::get('/user/{id}', [AnnonceController::class, 'index']);
    Route::get('/{id}/likes', [AnnonceController::class, 'getLikes']);
});

// -------------------- PUBLIC COMMENTS (Read-only) --------------------
Route::get('/annonces/{annonceId}/comments', [CommentController::class, 'index']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post ('/annonces/{annonceId}/comments/{commentId}/like',   [CommentController::class, 'like']);
    Route::post ('/annonces/{annonceId}/comments/{commentId}/unlike', [CommentController::class, 'unlike']);
    // Route::patch('/annonces/{annonceId}/comments/{commentId}/pin',    [CommentController::class, 'pin']);
    // Route::patch('/annonces/{annonceId}/comments/{commentId}/unpin',  [CommentController::class, 'unpin']);
    Route::post  ('/annonces/{annonceId}/comments', [CommentController::class, 'store']);
    Route::put   ('/annonces/{annonceId}/comments/{commentId}', [CommentController::class, 'update']);
    Route::delete('/annonces/{annonceId}/comments/{commentId}', [CommentController::class, 'destroy']);
});
// -------------------- PUBLIC SHOPS & EQUIPMENT --------------------
Route::get('/boutiques', [BoutiqueController::class, 'index']);
Route::get('/boutiques/{fournisseur_id}', [BoutiqueController::class, 'show']);
Route::get('/materielles/categories', [MaterielleController::class, 'categories']);
Route::get('/materielles/fournisseur/{fournisseur_id}', [MaterielleController::class, 'index']);
Route::get('/materielles/{materielle_id}', [MaterielleController::class, 'show']);
Route::get('/materielles/compare/{id1}/{id2}', [MaterielleController::class, 'compare']);
Route::get('/materielles', [MaterielleController::class, 'marketplace']);

// -------------------- PUBLIC EVENTS (UNIQUEMENT GET - POUR LES VISITEURS) --------------------
Route::prefix('events')->group(function () {
    Route::get('/', [EventController::class, 'index']);
    Route::get('/search', [EventController::class, 'search']);
    Route::get('/groups/{groupId}', [EventController::class, 'getGroupEvents']);
    Route::get('/my-events', [EventController::class, 'myEvents']);
    Route::get('/{id}/share-links', [EventController::class, 'getEventShareLinks'])->where('id', '[0-9]+');
    Route::get('/{id}/copy-link', [EventController::class, 'getEventCopyLink'])->where('id', '[0-9]+');
    Route::get('/{id}', [EventController::class, 'getEventDetails'])->where('id', '[0-9]+');
});

// -------------------- PUBLIC GROUPS --------------------
Route::get('/groupes/search', [GroupController::class, 'searchGroups']);
Route::get('/groupes', [GroupController::class, 'listGroupsWithFeedbacks']);

// -------------------- PUBLIC CAMPING ZONES --------------------
Route::prefix('zones')->group(function () {
    Route::get('/', [CampingZonesController::class, 'index']);
    Route::get('/export-geojson', [CampingZonesController::class, 'exportGeoJson']);
    Route::get('/search', [CampingZonesController::class, 'search']);
    Route::get('/nearby', [CampingZonesController::class, 'nearby']);
    Route::get('/cluster', [CampingZonesController::class, 'clusterZones']);
    Route::get('/region', [CampingZonesController::class, 'zonesByRegion']);
    Route::get('/top', [CampingZonesController::class, 'topZones']);
    Route::get('/top-by-season', [CampingZonesController::class, 'topZonesBySeason']);
    Route::get('/recommend', [CampingZonesController::class, 'recommendZones']);
    Route::get('/exclude-non-relevant', [CampingZonesController::class, 'excludeNonRelevantZones']);
    Route::get('/{id}', [CampingZonesController::class, 'show']);
    Route::get('/{id}/validate-coordinates', [CampingZonesController::class, 'validateCoordinates']);
    Route::get('/{id}/stats', [CampingZonesController::class, 'zoneStats']);
});

// -------------------- PUBLIC CAMPING CENTERS --------------------
Route::prefix('centres')->group(function () {
    Route::get('/map', [CampingCentresController::class, 'getCentresMap']);
    Route::get('/registered-map', [CampingCentresController::class, 'registeredCentresMap']);
    Route::get('/search', [CampingCentresController::class, 'searchCentres']);
    Route::get('/search-unlinked', [CentreClaimController::class, 'searchUnlinked']); // partenariats
    Route::get('/by-user/{userId}', [CampingCentresController::class, 'getByUser'])->whereNumber('userId');
    Route::get('/{id}/zones', [CampingCentresController::class, 'listZones'])->whereNumber('id');
    Route::get('/{id}', [CampingCentresController::class, 'showCentre'])->whereNumber('id');
});

// -------------------- PUBLIC CAMPING (Combined) --------------------
Route::prefix('public')->group(function () {
    Route::get('/zones-centres', [PublicCampingController::class, 'zonesWithCentres']);
    Route::get('/centre/{id}', [PublicCampingController::class, 'showCentre']);
    Route::get('/zones-filters', [PublicCampingController::class, 'zonesWithFilters']);
    Route::get('/zones-nearby', [PublicCampingController::class, 'nearbyZones']);
});

// -------------------- PUBLIC CENTER SERVICES API --------------------
Route::prefix('centers')->group(function () {
    Route::get('/services', [CenterServiceApiController::class, 'centersWithServices']);
    Route::get('/{center}/services', [CenterServiceApiController::class, 'centerServices']);
    Route::get('/service-categories', [CenterServiceApiController::class, 'serviceCategories']);
    Route::post('/calculate-price', [CenterServiceApiController::class, 'calculatePrice']);
});

// -------------------- PUBLIC FEEDBACKS (Read-only) --------------------
Route::prefix('feedbacks')->group(function () {
    Route::get('/statistics', [FeedbackController::class, 'statistics']);
    Route::get('/target/{type}/{targetId}', [FeedbackController::class, 'getTargetFeedbacks']);
    Route::get('/zone/{zoneId}', [FeedbackController::class, 'listZone']);
});

// -------------------- PUBLIC PROFILE LOOKUP --------------------
Route::prefix('profile')->group(function () {
    Route::get('/public/{id}', [ProfileController::class, 'showById']);
});

// -------------------- PUBLIC USER VERIFICATION --------------------
Route::get('/user/{id}/verification-status', function ($id) {
    $user = \App\Models\User::find($id);
    if (!$user) {
        return response()->json(['error' => 'User not found'], 404);
    }
    return response()->json([
        'verified' => $user->hasVerifiedEmail(),
        'email' => $user->email,
    ]);
});

// -------------------- PAYMENT CALLBACKS (Public) --------------------
Route::get('/flouci/callback', [PaymentController::class, 'callback']);

// -------------------- ROLES (Public) --------------------
Route::get('/roles', function () {
    return response()->json([
        'success' => true,
        'data' => \App\Models\Role::all()
    ]);
});

// ==================== AUTHENTICATED ROUTES (SANCTUM) ====================
Route::middleware('auth:sanctum')->group(function () {
    
    // Current User
    Route::get('/user', fn(Request $request) => $request->user());
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    // ---- Expenses (per-user CRUD) ----
    Route::prefix('my/expenses')->group(function () {
        Route::get('/',          [ExpenseController::class, 'index']);
        Route::get('/stats',     [ExpenseController::class, 'stats']);
        Route::post('/',         [ExpenseController::class, 'store']);
        Route::get('/{id}',      [ExpenseController::class, 'show']);
        Route::put('/{id}',      [ExpenseController::class, 'update']);
        Route::delete('/{id}',   [ExpenseController::class, 'destroy']);
    });

    // ---- Balance (per-user read) ----
    Route::get('/my/balance', function () {
        $balance = \App\Models\Balance::forUser(auth()->id());
        return response()->json(['success' => true, 'data' => $balance]);
    });

    // ---- Withdrawal request (user submits) ----
    Route::post('/my/withdrawal-request', function (\Illuminate\Http\Request $request) {
        $data = $request->validate([
            'montant'          => 'required|numeric|min:1',
            'methode'          => 'required|in:virement_bancaire,chèque,espèces,flouci',
            'details_paiement' => 'nullable|array',
        ]);

        // Check if withdrawals are enabled
        $enabled = \App\Models\PlatformSetting::get('withdrawal_enabled', true);
        if (!$enabled) {
            return response()->json(['success' => false, 'message' => 'Les retraits sont temporairement désactivés.'], 422);
        }

        // Check allowed days (1=Monday ... 7=Sunday, using isoWeekday convention)
        $allowedDays = \App\Models\PlatformSetting::get('withdrawal_allowed_days', [1, 4]);
        $todayDow = (int) now()->isoWeekday(); // 1=Mon, 7=Sun
        if (!in_array($todayDow, $allowedDays)) {
            $dayNames = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
            $allowedNames = implode(' et ', array_map(fn($d) => $dayNames[$d] ?? $d, $allowedDays));
            return response()->json(['success' => false, 'message' => "Les retraits sont acceptés uniquement le $allowedNames."], 422);
        }

        // Check minimum amount
        $minAmount = \App\Models\PlatformSetting::get('withdrawal_min_amount', 50);
        if ($data['montant'] < $minAmount) {
            return response()->json(['success' => false, 'message' => "Le montant minimum de retrait est {$minAmount} TND."], 422);
        }

        $balance = \App\Models\Balance::forUser(auth()->id());

        if (($balance->solde_disponible ?? 0) < $data['montant']) {
            return response()->json(['success' => false, 'message' => 'Solde insuffisant.'], 422);
        }

        // Vérifier qu'il n'y a pas déjà une demande en attente
        $existing = \App\Models\WithdrawalRequest::where('user_id', auth()->id())
            ->whereIn('status', ['en_attente', 'en_cours'])
            ->exists();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Une demande est déjà en cours de traitement.'], 422);
        }

        $wd = \App\Models\WithdrawalRequest::create([
            'user_id'          => auth()->id(),
            'montant'          => $data['montant'],
            'methode'          => $data['methode'],
            'details_paiement' => $data['details_paiement'] ?? null,
            'status'           => 'en_attente',
        ]);

        return response()->json(['success' => true, 'data' => $wd], 201);
    });

    // ---- My withdrawals (user reads own) ----
    Route::get('/my/withdrawals', function (\Illuminate\Http\Request $request) {
        $wds = \App\Models\WithdrawalRequest::where('user_id', auth()->id())
            ->latest()
            ->paginate($request->get('per_page', 10));
        return response()->json(['success' => true, 'data' => $wds]);
    });
    Route::get('/user/{userId}/status', function ($userId) {
        $user = \App\Models\User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        return response()->json([
            'online' => $user->isOnline(),
            'last_seen' => $user->last_login_at,
            'is_active' => $user->is_active
        ]);
    });
    
    // Email Verification
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store']);
    Route::post('/send-verification-current', [VerifyEmailController::class, 'sendVerificationForCurrentUser']);
    Route::get('/user/verification-status', function (Request $request) {
        return response()->json([
            'verified' => $request->user()->hasVerifiedEmail(),
            'email' => $request->user()->email,
        ]);
    });
    Route::put('/user/password', [PasswordController::class, 'update']);
    
    // Profile Management
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::get('/completion', [ProfileController::class, 'completion']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::put('/change-password', [ProfileController::class, 'changePassword']);
        Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
        Route::post('/updateInfo', [ProfileController::class, 'updateInfo']);
        
        Route::prefix('photos')->group(function () {
            Route::get('/', [ProfileController::class, 'getProfilePhotos']);
            Route::post('/', [ProfileController::class, 'storeOrUpdateProfilePhotos']);
            Route::delete('/{photoId}', [ProfileController::class, 'deletePhoto']);
            Route::put('/{photoId}/cover', [ProfileController::class, 'setCoverPhoto']);
            Route::put('/reorder', [ProfileController::class, 'reorderPhotos']);
        });
        
        Route::get('/service-categories', [ProfileController::class, 'getServiceCategories']);
        
        Route::prefix('center/{centerId}')->where(['centerId' => '[0-9]+'])
            ->middleware('centre.not.pending')
            ->group(function () {
                Route::put('/', [ProfileController::class, 'updateCenter']);

                Route::prefix('services')->group(function () {
                    Route::get('/', [ProfileController::class, 'getCenterServices']);
                    Route::post('/', [ProfileController::class, 'updateCenterService']);
                    Route::put('/{serviceId}', [ProfileController::class, 'updateCenterService'])->where(['serviceId' => '[0-9]+']);
                    Route::delete('/{serviceId}', [ProfileController::class, 'deleteCenterService'])->where(['serviceId' => '[0-9]+']);
                    Route::post('/custom', [ProfileController::class, 'addCustomService']);
                });

                Route::prefix('equipment')->group(function () {
                    Route::get('/', [ProfileController::class, 'getCenterEquipment']);
                    Route::put('/', [ProfileController::class, 'updateCenterEquipment']);
                });
            });
        
        Route::get('/{type}/{userId}', [ProfileController::class, 'getProfileDetails'])
            ->where('type', 'guide|centre|groupe|fournisseur')
            ->where('userId', '[0-9]+');
    });
    
    Route::get('/users/search', [ProfileController::class, 'searchUsers']);
    Route::get('/annonces/user-likes', [AnnonceController::class, 'getUserLikes']);
    // -------------------- ANNOUNCEMENTS (Authenticated actions) --------------------
    
    // Announcements
    Route::prefix('annonces')->group(function () {
        // Read-only — always allowed
        Route::get('/user/{userId}', [AnnonceController::class, 'index']);
        Route::get('/archived/{userId}', [AnnonceController::class, 'getArchived']);
        Route::get('/my-liked', [AnnonceController::class, 'myLiked']);
        Route::post('/{id}/like', [AnnonceController::class, 'like']);
        Route::post('/{id}/unlike', [AnnonceController::class, 'unlike']);
        Route::get('/{id}/likes', [AnnonceController::class, 'getLikes']);
        Route::get('/{id}/likes/check', [AnnonceController::class, 'checkLike']);
        Route::get('/{id}', [AnnonceController::class, 'show']);

        // Write actions — require approved account
        Route::middleware(['require.active'])->group(function () {
            Route::post('/', [AnnonceController::class, 'store']);
            Route::post('/{id}', [AnnonceController::class, 'update']);
            Route::delete('/{id}', [AnnonceController::class, 'destroy']);
            Route::patch('/{id}/archive', [AnnonceController::class, 'archive']);
            Route::patch('/{id}/unarchive', [AnnonceController::class, 'unarchive']);
        });
    });
    
    // Comments
    Route::prefix('comments')->group(function () {
        Route::post('/annonce/{annonceId}', [CommentController::class, 'store']);
        Route::post('/annonce/{annonceId}/reply/{parentId}', [CommentController::class, 'store']);
        Route::put('/{id}', [CommentController::class, 'update']);
        Route::delete('/{id}', [CommentController::class, 'destroy']);
        Route::post('/{id}/like', [CommentController::class, 'toggleLike']);
    });
    
    // Feedbacks
    Route::prefix('feedbacks')->group(function () {
        Route::get('/', [FeedbackController::class, 'index']);
        Route::post('/', [FeedbackController::class, 'store']);
        Route::put('/{id}', [FeedbackController::class, 'update']);
        Route::delete('/{id}', [FeedbackController::class, 'destroy']);
        Route::post('/zone/{zoneId}', [FeedbackController::class, 'storeZone']);
    });
    
    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread/count', [NotificationController::class, 'getUnreadCount']);
        Route::get('/preferences', [NotificationController::class, 'getPreferences']);
        Route::get('/stats', [NotificationController::class, 'getStats']);
        Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::patch('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::patch('/{id}/archive', [NotificationController::class, 'archive']);
        Route::put('/preferences', [NotificationController::class, 'updatePreferences']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });
    
    // Messages & Conversations
    Route::prefix('conversations')->group(function () {
        Route::get('/', [ConversationController::class, 'index']);
        Route::post('/start', [ConversationController::class, 'start']);
        Route::post('/start-with-group', [ConversationController::class, 'startWithGroup']);
        Route::get('/group/{groupId}', [ConversationController::class, 'getGroupConversations']);
        Route::get('/my-groups', [ConversationController::class, 'getMyGroupConversations']);
        Route::get('/{conversationId}/messages', [ConversationController::class, 'messages']);
        Route::post('/{conversationId}/messages', [ConversationController::class, 'sendMessage']);
        Route::patch('/{conversationId}/read', [ConversationController::class, 'markAsRead']);
        Route::post('/{conversationId}/mark-as-read', [ConversationController::class, 'markAsRead']);
        Route::patch('/{conversationId}/archive', [ConversationController::class, 'toggleArchive']);
        Route::delete('/{conversationId}', [ConversationController::class, 'destroy']);
    });
    
    Route::prefix('messages')->group(function () {
        Route::get('/{messageId}', [MessageController::class, 'show']);
        Route::put('/{messageId}', [MessageController::class, 'update']);
        Route::delete('/{messageId}', [MessageController::class, 'destroy']);
        Route::post('/{messageId}/react', [MessageController::class, 'react']);
        Route::get('/{messageId}/read-receipts', [MessageController::class, 'readReceipts']);
    });
    
    // -------------------- AUTHENTICATED EVENT ROUTES (Non-admin) --------------------
    Route::prefix('events')->group(function () {
        Route::get('/nearby/{userId}', [EventController::class, 'notifyNearbyEvents']);
        Route::get('/{id}/participants', [EventController::class, 'participants']);
        Route::get('/{id}/details', [EventController::class, 'show']);
        Route::get('/{id}/participants/stats', [ReservationEventController::class, 'participantStats']);
        Route::post('/{id}/interest',          [EventInterestController::class, 'toggleInterest']);
        Route::get('/my-interests',            [EventInterestController::class, 'myInterests']);
        Route::get('/my-interest-ids',         [EventInterestController::class, 'myInterestIds']);
        Route::get('/{id}/interest/check',     [EventInterestController::class, 'check']);
        
        Route::middleware(['group', 'require.active'])->group(function () {
            Route::post('/', [EventController::class, 'store']);
            Route::put('/{id}', [EventController::class, 'update']);
            Route::delete('/{id}', [EventController::class, 'destroy']);
            Route::patch('/participants/{id}/status', [EventController::class, 'updateStatus']);
            Route::post('/{id}/invite', [EventController::class, 'sendInvites']);
        });
    });
    
    // Event Reservations (non-admin)
    Route::prefix('reservations')->group(function () {
        Route::get('/my-participations', [ReservationEventController::class, 'myParticipations']);
        Route::get('/check-conflict/{eventId}', [ReservationEventController::class, 'checkConflict']);
        Route::get('/passees', [ReservationEventController::class, 'mesReservationsPassees']);
        Route::post('/event', [ReservationEventController::class, 'createReservationWithPayment']);
        Route::put('/event/{id}/simulate-payment', [ReservationEventController::class, 'simulatePayment']);
        Route::post('/event/{eventId}/cancel-by-user', [ReservationEventController::class, 'cancelByUser']);
        Route::get('/event/{id}', [ReservationEventController::class, 'show']);
        Route::put('/{id}', [ReservationEventController::class, 'updateReservation']);
        Route::patch('/{id}/status', [ReservationEventController::class, 'updateStatus']);
        Route::delete('/{id}', [ReservationEventController::class, 'destroy']);
        Route::post('/events/{event}/participants/manual', [ReservationEventController::class, 'addManualParticipant']);
        Route::get('/events/{eventId}/participants/search', [ReservationEventController::class, 'search']);
        Route::put('/participants/{id}/update', [ReservationEventController::class, 'updateManualParticipant']);
        Route::get('/events/{event}/participants', [EventParticipantController::class, 'index']);
    });
    
    // Payments
    Route::prefix('payment')->group(function () {
        Route::post('/event/{eventId}/payer', [PaymentController::class, 'initPayment']);
        Route::get('/confirm/{reservationId}', [PaymentController::class, 'confirmerPaiement']);
        Route::get('/konnect/success', [PaymentController::class, 'konnectCallback'])->name('konnect.success');
        Route::get('/konnect/fail', [PaymentController::class, 'konnectCallback'])->name('konnect.fail');
        Route::post('/konnect/webhook', [PaymentController::class, 'webhookKonnect']);
    });
    
    Route::get('/reservation/{id}/imprimer', [PaymentController::class, 'imprimerTicket']);
    Route::get('/reservation/{id}/telecharger', [PaymentController::class, 'telechargerTicket']);
    Route::post('/reservations/{id}/annuler', [ReservationCancellationController::class, 'annulerReservation']);
    
    // Groups
    Route::prefix('groupes')->group(function () {
        Route::post('/{groupeId}/follow', [FollowGroupeController::class, 'follow']);
        Route::delete('/{groupeId}/unfollow', [FollowGroupeController::class, 'unfollow']);
        Route::get('/me/followed-groupes', [FollowGroupeController::class, 'myFollowedGroupes']);
        Route::get('/{groupe}/feedbacks', [GroupController::class, 'listForGroup']);
        // Join / leave / membership by the group's user ID
        Route::post('/user/{groupUserId}/join',         [FollowGroupeController::class, 'joinGroup']);
        Route::delete('/user/{groupUserId}/leave',      [FollowGroupeController::class, 'leaveGroup']);
        Route::get('/user/{groupUserId}/membership',    [FollowGroupeController::class, 'checkMembership']);
        // Co-owners (public list)
        Route::get('/user/{groupUserId}/co-owners',     [GroupeCoOwnerController::class, 'list']);
        // Co-owner management (authenticated group owner)
        Route::get('/co-owners/search',                 [GroupeCoOwnerController::class, 'search']);
        Route::post('/co-owners/{userId}',              [GroupeCoOwnerController::class, 'add']);
        Route::delete('/co-owners/{userId}',            [GroupeCoOwnerController::class, 'remove']);
    });
    
    // Group Reservation Management — requires approved account
    Route::prefix('group/reservations')->middleware(['require.active'])->group(function () {
        Route::get('/', [ReservationEventController::class, 'toutesMesReservations']);
        Route::get('/export', [ReservationEventController::class, 'exportReservations']);
        Route::get('/stats', [ReservationEventController::class, 'reservationStats']);
        Route::get('/event/{event_id}', [ReservationEventController::class, 'reservationsParEvenement']);
        Route::get('/{reservation_id}', [ReservationEventController::class, 'show']);
        Route::patch('/{reservation_id}/status', [ReservationEventController::class, 'updateStatus']);
        Route::post('/{reservation_id}/cancel', [ReservationEventController::class, 'cancelReservation']);
        Route::patch('/{reservation_id}/update-places', [ReservationEventController::class, 'updatePlaces']);
        Route::put('/{reservation_id}/update-participant', [ReservationEventController::class, 'updateManualParticipant']);
        Route::delete('/{reservation_id}', [ReservationEventController::class, 'destroy']);
        Route::post('/create', [ReservationEventController::class, 'createReservationWithPayment']);
        Route::post('/{reservation_id}/simulate-payment', [ReservationEventController::class, 'simulatePayment']);
    });
    
    // Group Events Statistics
    Route::prefix('group/events')->group(function () {
        Route::get('/{event_id}/participants', [ReservationEventController::class, 'participants']);
        Route::get('/{event_id}/stats', [ReservationEventController::class, 'participantStats']);
        Route::get('/{event_id}/search', [ReservationEventController::class, 'search']);
    });
    
    // Camping Zones
    Route::prefix('zones')->group(function () {
        Route::post('/suggest', [CampingZonesController::class, 'suggestZone']);
        Route::post('/{id}/gallery', [CampingZonesController::class, 'addGallery']);
        Route::post('/{id}/review', [CampingZonesController::class, 'markForReview']);
        Route::get('/recommended/{userId}', [CampingZonesController::class, 'recommendedZones']);
        Route::get('/personalized/{userId}', [CampingZonesController::class, 'personalizedRecommendations']);
        Route::get('/{userId}/recommendations', [CampingZonesController::class, 'personalizedRecommendations']);
    });
    
    // Camping Centers
    Route::prefix('centres')->group(function () {
        Route::post('/suggest', [CampingCentresController::class, 'suggestCentre']);
        Route::get('/favoris', [CampingCentresController::class, 'listFavoris']);
        Route::post('/{id}/favoris', [CampingCentresController::class, 'toggleFavoris']);
        Route::get('/{centreId}/stats', [CampingCentresController::class, 'centreStats']);
        // Partenariats — soumettre une demande
        Route::post('/{centreId}/claim', [CentreClaimController::class, 'submitClaim'])->whereNumber('centreId');
    });

    // Partenariats — consulter ses propres demandes
    Route::get('/my-centre-claim', [CentreClaimController::class, 'myClaim']);
    
    // Zone Polygons
    Route::prefix('zone-polygons')->group(function () {
        Route::post('/', [ZonePolygonController::class, 'store']);
        Route::put('/{id}', [ZonePolygonController::class, 'update']);
        Route::delete('/{id}', [ZonePolygonController::class, 'destroy']);
        Route::get('/zone/{zoneId}', [ZonePolygonController::class, 'listByZone']);
    });
    
    // -------------------- DASHBOARD STATS --------------------
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // -------------------- FAVORITES (unified, polymorphic) --------------------
    Route::prefix('favorites')->group(function () {
        Route::post('/',                          [FavoriteController::class, 'toggle']);
        Route::get('/',                           [FavoriteController::class, 'list']);
        Route::get('/ids',                        [FavoriteController::class, 'ids']);
        Route::get('/check/{type}/{id}',          [FavoriteController::class, 'check']);
    });

    Route::prefix('favoris')->group(function () {
        Route::post('/zone/{id}', [FavorisController::class, 'toggleZone']);
        Route::get('/liste', [FavorisController::class, 'listFavoris']);
    });

    Route::post('/zones/{zoneId}/signales', [SignalementZoneController::class, 'store']);
    Route::post('/events/{event}/send-reminders', [NotificationController::class, 'sendRemindersForEvent']);
});
// ==================== ROLE-SPECIFIC ROUTES ====================

// Publisher Routes — also require active (approved) account
Route::middleware(['auth:sanctum', 'can.publish', 'require.active'])->prefix('annonces')->group(function () {
    Route::post('/', [AnnonceController::class, 'store']);
    Route::match(['put', 'post'], '/{id}', [AnnonceController::class, 'update']);
    Route::delete('/{id}', [AnnonceController::class, 'destroy']);
    Route::get('/archived/{userId}', [AnnonceController::class, 'getArchived']);
    Route::patch('/{id}/archive', [AnnonceController::class, 'archive']);
    Route::patch('/{id}/unarchive', [AnnonceController::class, 'unarchive']);
});
Route::patch('reservation/materiel/{id}/confirm', [ReservationMaterielleController::class, 'confirm']);

// Fournisseur Routes
// Equipment Management - Both Suppliers AND Campers
Route::middleware(['auth:sanctum', 'supplier_or_camper'])->group(function () {
    Route::prefix('materielles')->group(function () {
        Route::get('/{materielle_id}/edit', [MaterielleController::class, 'edit']);
        Route::post('/', [MaterielleController::class, 'store']);
        Route::post('/{id}', [MaterielleController::class, 'update']);
        Route::delete('/{id}', [MaterielleController::class, 'destroy']);
        Route::patch('/{id}/activate', [MaterielleController::class, 'activate']);
        Route::patch('/{id}/deactivate', [MaterielleController::class, 'deactivate']);
    });
    
    // Supplier material reservation management — requires approved account
    Route::prefix('reservation/materiel')->middleware(['require.active'])->group(function () {
        Route::patch('/{id}/reject', [ReservationMaterielleController::class, 'reject']);
        Route::post('/{id}/verify-pin', [ReservationMaterielleController::class, 'verifyPin']);
        Route::patch('/{id}/returned', [ReservationMaterielleController::class, 'markReturned']);
        Route::patch('/{id}/cancel', [ReservationMaterielleController::class, 'cancelByFournisseur']);
    });

    Route::prefix('reservation/fournisseur')->middleware(['require.active'])->group(function () {
        Route::get('/', [ReservationMaterielleController::class, 'show']);
        Route::patch('/cancel/{id}', [ReservationMaterielleController::class, 'cancelByFournisseur']);
    });
});

// Shop Management - ONLY for Suppliers (role_id: 4)
Route::middleware(['auth:sanctum', 'fournisseur'])->group(function () {
    Route::prefix('boutiques')->group(function () {
        Route::get('/edit/{boutique_id}', [BoutiqueController::class, 'edit']);
        Route::post('/', [BoutiqueController::class, 'add']);
        Route::post('/update', [BoutiqueController::class, 'update']);
        Route::delete('/', [BoutiqueController::class, 'destroy']);
    });
});

// Camping Center Reservations
Route::middleware(['auth:sanctum'])->prefix('reservation')->group(function () {
    // ✅ Put specific routes BEFORE wildcard routes
    Route::get('/all', [UnifiedReservationController::class, 'getAllReservations']);
    
    // Then define routes with parameters
    Route::middleware(['campeur_or_centre'])->group(function () {
        Route::get('/{id}', [ReservationsCentreController::class, 'show']);
        Route::get('/{id}/invoice', [ReservationsCentreController::class, 'downloadInvoice']);
        Route::get('/{id}/user-history', [ReservationsCentreController::class, 'getUserReservationHistory']);
        Route::patch('/centre/cancel/{id}', [ReservationsCentreController::class, 'destroy']);
        Route::patch('/centre/approve-modified/{id}', [ReservationsCentreController::class, 'approveModified']);
    });

    // Camper rejects a center modification
    Route::middleware(['campeur'])->group(function () {
        Route::patch('/centre/{id}/reject-modification', [ReservationsCentreController::class, 'rejectModification']);
    });

    // Center modifies a pending reservation — requires approved account
    Route::middleware(['centre', 'require.active'])->group(function () {
        Route::put('/centre/{id}/center-modify', [ReservationsCentreController::class, 'centerModify']);
    });

    // Routes for campers only
    
    Route::middleware(['campeur'])->group(function () {
        Route::get('/my', [ReservationMaterielleController::class, 'index_user']);
        Route::post('/', [ReservationMaterielleController::class, 'store']);
        Route::delete('/{id}', [ReservationMaterielleController::class, 'destroy']);
        Route::post('/centre', [ReservationsCentreController::class, 'store']);
        Route::put('/centre/{id}', [ReservationsCentreController::class, 'update']);
        Route::patch('/centre/destroy/{id}', [ReservationsCentreController::class, 'destroy']);
        Route::get('/centre/index_user', [ReservationsCentreController::class, 'index_user']);
        Route::get('/centre/user/statistics', [ReservationsCentreController::class, 'user_statistics']);
    });
    
    // Centre management of bookings — requires approved account
    Route::middleware(['centre', 'require.active'])->group(function () {
        Route::get('/centre/index', [ReservationsCentreController::class, 'index']);
        Route::patch('/centre/confirm/{id}', [ReservationsCentreController::class, 'confirm']);
        Route::patch('/centre/reject/{id}', [ReservationsCentreController::class, 'reject']);
        Route::patch('/centre/update_status', [ReservationsCentreController::class, 'update_status']);
        Route::put('/centre/{id}/update', [ReservationsCentreController::class, 'update']);
        Route::get('/centre/statistics', [ReservationsCentreController::class, 'statistics']);
        Route::get('/centre/availability', [ReservationsCentreController::class, 'availability']);
        Route::patch('/centre/partial-accept/{id}', [ReservationsCentreController::class, 'partialAccept']);
    });
});

// ==================== ADMIN ROUTES (UNIQUEMENT ICI) ====================
// ==================== ADMIN ROUTES (UNIQUEMENT ICI) ====================
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {

    Route::prefix('admin/annonces')->group(function () {
        Route::patch('/{id}/activate',   [AnnonceController::class, 'activate']);
        Route::patch('/{id}/deactivate', [AnnonceController::class, 'deactivate']);
    });
    // Dashboard/Utility
    
    // Dashboard
    Route::post('/restock-expired', [ReservationMaterielleController::class, 'restockExpiredReservations']);
    
    // -------------------- USER MANAGEMENT --------------------
    Route::prefix('users')->group(function () {
        Route::get('/', [AdminUserController::class, 'index']);
        Route::get('/stats', [AdminUserController::class, 'stats']);
        Route::get('/stats/overview', [AdminUserController::class, 'stats']);
        Route::get('/roles/all', fn() => response()->json(['success' => true, 'data' => \App\Models\Role::all()]));
        
        Route::prefix('{id}')->group(function () {
            Route::get('/', [AdminUserController::class, 'show']);
            Route::put('/', [AdminUserController::class, 'update']);
            Route::delete('/', [AdminUserController::class, 'destroy']);
            Route::put('/toggle-activation', [AdminUserController::class, 'toggleActivation']);
            Route::post('/reset-password', [AdminUserController::class, 'resetPassword']);
            Route::post('/send-email', [AdminUserController::class, 'sendEmail']);
            
            Route::prefix('documents')->group(function () {
                Route::post('/', [AdminUserController::class, 'uploadDocument']);
                Route::get('/{documentType}/download', [AdminUserController::class, 'downloadDocument']);
                Route::get('/{documentType}/view', [AdminUserController::class, 'viewDocument']);
                Route::delete('/{documentType}', [AdminUserController::class, 'deleteDocument']);
            });
            
            Route::prefix('photos')->group(function () {
                Route::post('/', [AdminUserController::class, 'uploadPhotos']);
                Route::get('/', [AdminUserController::class, 'getPhotos']);
                Route::delete('/{photoId}', [AdminUserController::class, 'deletePhoto']);
            });
        });
    });
    
    // -------------------- ADMIN EVENTS & RESERVATIONS MANAGEMENT --------------------
    // CORRECTION: Supprimez le middleware et préfixe admin en double
    Route::prefix('admin-events')->group(function () {
        Route::get('/', [AdminEventsController::class, 'index']);
        Route::get('/groups', [AdminEventsController::class, 'getGroups']);
        Route::post('/', [AdminEventsController::class, 'store']);
        Route::get('/{id}', [AdminEventsController::class, 'show']);
        Route::put('/{id}', [AdminEventsController::class, 'update']);
        Route::delete('/{id}', [AdminEventsController::class, 'destroy']);
        Route::patch('/{id}/activate', [AdminEventsController::class, 'activate']);
        Route::patch('/{id}/deactivate', [AdminEventsController::class, 'deactivate']);
        Route::patch('/{id}/cancel', [AdminEventsController::class, 'cancelEvent']);
        Route::get('/{id}/reservations', [AdminEventsController::class, 'reservations']);
        Route::get('/{id}/statistics', [AdminEventsController::class, 'statistics']);
        Route::get('/{id}/export-csv', [AdminEventsController::class, 'exportReservationsCsv']);
    });

    // ---- AdminEventsController : CRUD + stats + updateStatus ----
    Route::prefix('evenements')->group(function () {
        Route::get('/', [AdminEventsController::class, 'index']);
        Route::get('/stats', [AdminEventsController::class, 'stats']);
        Route::get('/groupes', [AdminEventsController::class, 'getGroups']);
        Route::post('/', [AdminEventsController::class, 'store']);
        Route::get('/{id}', [AdminEventsController::class, 'show']);
        Route::put('/{id}', [AdminEventsController::class, 'update']);
        Route::delete('/{id}', [AdminEventsController::class, 'destroy']);
        Route::patch('/{id}/statut', [AdminEventsController::class, 'updateStatus']);
    });

    // ---- AdminEventReservationController ----
    Route::prefix('reservations')->group(function () {
        Route::get('/', [AdminEventReservationController::class, 'listReservations']);
        Route::get('/export', [AdminEventReservationController::class, 'exportReservations']);
        Route::get('/stats', [AdminEventReservationController::class, 'stats']);
        Route::get('/{id}', [AdminEventReservationController::class, 'getReservationDetails']);
        Route::put('/{id}', [AdminEventReservationController::class, 'update']);
        Route::delete('/{id}', [AdminEventReservationController::class, 'deleteReservation']);
        Route::post('/events/{eventId}/participants/manual', [AdminEventReservationController::class, 'addManualParticipant']);
    });

// Routes pour les annonces admin
Route::prefix('annonces')->group(function () {
 
    // ── Routes sans paramètre dynamique (déclarées EN PREMIER) ────────────────
 
    Route::get('/',               [AdminAnnonceController::class, 'index']);
    Route::post('/',              [AdminAnnonceController::class, 'store']);
 
    // FIX : bulk-action déplacé ici, avant /{id}, sinon "bulk-action" est
    //       interprété comme un {id} et la route n'est jamais atteinte.
    Route::post('/bulk-action',   [AdminAnnonceController::class, 'bulkAction']);
 
    Route::get('/statistics',     [AdminAnnonceController::class, 'statistics']);
    Route::get('/export',         [AdminAnnonceController::class, 'export']);
 
    // ── Routes avec paramètre dynamique (déclarées EN DERNIER) ───────────────
 
    Route::get('/{id}',           [AdminAnnonceController::class, 'show']);
    Route::put('/{id}',           [AdminAnnonceController::class, 'update']);
 
    Route::delete('/{id}/force',  [AdminAnnonceController::class, 'forceDelete']);
    Route::delete('/{id}',        [AdminAnnonceController::class, 'destroy']);
 
    Route::post('/{id}/approve',  [AdminAnnonceController::class, 'approve']);
    Route::post('/{id}/reject',   [AdminAnnonceController::class, 'reject']);
    Route::post('/{id}/archive',  [AdminAnnonceController::class, 'archive']);
    Route::post('/{id}/unarchive',[AdminAnnonceController::class, 'unarchive']);
});

    // -------------------- CENTER RESERVATIONS (Admin) --------------------
    Route::get('/reservation/centre/show/{id}', [ReservationsCentreController::class, 'show']);
    
    // -------------------- RESERVATIONS CENTER (ADMIN) --------------------
    Route::prefix('reservations-center')->group(function () {
        Route::get('/', [AdminReservationsController::class, 'index']);
        Route::get('/stats', [AdminReservationsController::class, 'stats']);
        Route::post('/bulk-action', [AdminReservationsController::class, 'bulkAction']);
        Route::get('/export', [AdminReservationsController::class, 'export']);
        Route::post('/materielle/check-availability/{materialId}', [AdminReservationsController::class, 'checkMaterialAvailability']);
        
        Route::prefix('{type}')->whereIn('type', ['center', 'events', 'materielle', 'guides'])->group(function () {
            Route::post('/', [AdminReservationsController::class, 'store']);
            Route::get('/{id}', [AdminReservationsController::class, 'show']);
            Route::put('/{id}', [AdminReservationsController::class, 'update']);
            Route::delete('/{id}', [AdminReservationsController::class, 'destroy']);
            Route::post('/{id}/send-confirmation', [AdminReservationsController::class, 'sendConfirmationEmail']);
        });
    });


    // -------------------- CENTER RESERVATIONS (Admin) --------------------
    Route::get('/reservation/centre/show/{id}', [ReservationsCentreController::class, 'show']);
    
    // -------------------- RESERVATIONS CENTER (ADMIN) --------------------
    Route::prefix('reservations-center')->group(function () {
        Route::get('/', [AdminReservationsController::class, 'index']);
        Route::get('/stats', [AdminReservationsController::class, 'stats']);
        Route::post('/bulk-action', [AdminReservationsController::class, 'bulkAction']);
        Route::get('/export', [AdminReservationsController::class, 'export']);
        Route::post('/materielle/check-availability/{materialId}', [AdminReservationsController::class, 'checkMaterialAvailability']);
        
        Route::prefix('{type}')->whereIn('type', ['center', 'events', 'materielle', 'guides'])->group(function () {
            Route::post('/', [AdminReservationsController::class, 'store']);
            Route::get('/{id}', [AdminReservationsController::class, 'show']);
            Route::put('/{id}', [AdminReservationsController::class, 'update']);
            Route::delete('/{id}', [AdminReservationsController::class, 'destroy']);
        });
    });
    
    // -------------------- FEEDBACK MANAGEMENT --------------------
    Route::prefix('feedbacks')->group(function () {
        Route::get('/', [AdminFeedbackController::class, 'index']);
        Route::get('/stats', [AdminFeedbackController::class, 'stats']);
        Route::get('/type/{type}', [AdminFeedbackController::class, 'getByType']);
        Route::get('/fournisseurs', [AdminFeedbackController::class, 'fournisseurs']);
        Route::get('/{id}', [AdminFeedbackController::class, 'show']);
        Route::put('/{id}', [AdminFeedbackController::class, 'update']);
        Route::post('/{id}/approve', [AdminFeedbackController::class, 'approve']);
        Route::post('/{id}/reject', [AdminFeedbackController::class, 'reject']);
        Route::delete('/{id}', [AdminFeedbackController::class, 'destroy']);
        Route::post('/{id}/moderate', [AdminUserController::class, 'moderate']);
    });
    
    // -------------------- CENTER RESERVATION MANAGEMENT --------------------
    Route::get('/reservation/centre/show/{id}', [ReservationsCentreController::class, 'show']);
    
    // -------------------- REPORTS MANAGEMENT --------------------
    Route::prefix('reports')->group(function () {
        Route::get('/', [ReportController::class, 'index']);
        Route::put('/{id}', [ReportController::class, 'update']);
        Route::delete('/{id}', [ReportController::class, 'destroy']);
    });

    // -------------------- CONTACT MESSAGES MANAGEMENT --------------------
    Route::prefix('contact-messages')->group(function () {
        Route::get('/', [ContactController::class, 'index']);
        Route::patch('/{id}/read', [ContactController::class, 'markRead']);
        Route::post('/{id}/reply', [ContactController::class, 'reply']);
        Route::delete('/{id}', [ContactController::class, 'destroy']);
    });

    // -------------------- PAYMENT MANAGEMENT --------------------
    Route::prefix('payments')->group(function () {
        Route::get('/',              [AdminPaymentController::class, 'index']);
        Route::get('/stats',         [AdminPaymentController::class, 'stats']);
        Route::get('/{id}',          [AdminPaymentController::class, 'show']);
        Route::put('/{id}/status',   [AdminPaymentController::class, 'updateStatus']);
    });

    // -------------------- REFUND REQUESTS --------------------
    Route::prefix('refunds')->group(function () {
        Route::get('/',              [AdminPaymentController::class, 'refunds']);
        Route::post('/{id}/approve', [AdminPaymentController::class, 'approveRefund']);
        Route::post('/{id}/reject',  [AdminPaymentController::class, 'rejectRefund']);
    });

    // -------------------- BALANCES --------------------
    Route::prefix('balances')->group(function () {
        Route::get('/',                      [AdminPaymentController::class, 'balances']);
        Route::post('/adjust/{userId}',      [AdminPaymentController::class, 'adjustBalance']);
    });

    // -------------------- WITHDRAWAL REQUESTS --------------------
    Route::prefix('withdrawals')->group(function () {
        Route::get('/',                  [AdminPaymentController::class, 'withdrawals']);
        Route::post('/{id}/approve',     [AdminPaymentController::class, 'approveWithdrawal']);
        Route::post('/{id}/complete',    [AdminPaymentController::class, 'completeWithdrawal']);
        Route::post('/{id}/reject',      [AdminPaymentController::class, 'rejectWithdrawal']);
    });

    // -------------------- EXPENSES (ADMIN VIEW) --------------------
    Route::prefix('expenses')->group(function () {
        Route::get('/',           [AdminExpenseController::class, 'index']);
        Route::get('/stats',      [AdminExpenseController::class, 'stats']);
        Route::patch('/{id}/status', [AdminExpenseController::class, 'updateStatus']);
        Route::delete('/{id}',    [AdminExpenseController::class, 'destroy']);
    });

    // -------------------- PLATFORM SETTINGS --------------------
    Route::prefix('settings')->group(function () {
        Route::get('/',    [\App\Http\Controllers\Admin\AdminSettingsController::class, 'index']);
        Route::put('/',    [\App\Http\Controllers\Admin\AdminSettingsController::class, 'update']);
    });

    // -------------------- ZONE REPORTS --------------------
    Route::prefix('signals')->group(function () {
        Route::get('/', [SignaleZoneController::class, 'indexAll']);
        Route::get('/zones/{zoneId}', [SignaleZoneController::class, 'index']);
        Route::put('/{id}/validate', [SignaleZoneController::class, 'validateSignalement']);
        Route::put('/{id}/reject', [SignaleZoneController::class, 'rejectSignalement']);
        Route::delete('/{id}', [SignaleZoneController::class, 'destroy']);
    });
    
    // -------------------- PARTNERSHIP CLAIMS MANAGEMENT --------------------
    Route::prefix('claims')->group(function () {
        Route::get('/', [CentreClaimController::class, 'adminIndex']);
        Route::post('/{id}/approve', [CentreClaimController::class, 'adminApprove']);
        Route::post('/{id}/reject',  [CentreClaimController::class, 'adminReject']);
        Route::post('/{id}/revoke',  [CentreClaimController::class, 'adminRevoke']);
    });

    // -------------------- CAMPING CENTERS MANAGEMENT --------------------
    Route::prefix('centres')->group(function () {
        Route::get('/', [CampingCentreController::class, 'index']);
        Route::get('/stats', [CampingCentreController::class, 'stats']);
        Route::get('/registered', [CampingCentreController::class, 'registeredCentres']);
        Route::get('/nearby', [CampingCentreController::class, 'nearby']);
        Route::get('/suggest-zones', [CampingCentreController::class, 'suggestZones']);
        Route::get('/search', [CampingCentreController::class, 'search']);
        Route::get('/search-users', [CampingCentreController::class, 'searchUsers']);
        Route::post('/', [CampingCentreController::class, 'store']);
        Route::get('/{id}', [CampingCentreController::class, 'show']);
        Route::put('/{id}', [CampingCentreController::class, 'update']);
        Route::post('/{id}/assign-zones', [CampingCentreController::class, 'assignZones']);
        Route::patch('/{id}/toggle-status', [CampingCentreController::class, 'toggleStatus']);
        Route::post('/{id}/link-user', [CampingCentreController::class, 'linkUser']);
        Route::post('/{id}/unlink-user', [CampingCentreController::class, 'unlinkUser']);
        Route::delete('/{id}', [CampingCentreController::class, 'destroy']);
        // Photo management
        Route::post('/{id}/photos', [CampingCentreController::class, 'addPhotos']);
        Route::delete('/{centreId}/photos/{photoId}', [CampingCentreController::class, 'deletePhoto']);
        Route::patch('/{centreId}/photos/{photoId}/cover', [CampingCentreController::class, 'setCoverPhoto']);
    });

// ─────────────────────────────────────────────────────────────────────────────
// CAMPING ZONES — routes admin
//
// RÈGLE ABSOLUE Laravel :
//   Les routes statiques (/stats, /merge …) DOIVENT être déclarées AVANT
//   les routes dynamiques (/{id}), sinon Laravel interprète "stats" comme
//   une valeur de {id} et appelle show('stats') → 404 ou 500.
// ─────────────────────────────────────────────────────────────────────────────

Route::prefix('zones')->group(function () {

    // ── Routes STATIQUES — sans {id} — EN PREMIER ─────────────────────────

    Route::get('/',                [CampingZoneController::class, 'index']);             // GET  /admin/zones
    Route::get('/stats',           [CampingZoneController::class, 'stats']);             // GET  /admin/zones/stats
    Route::post('/',               [CampingZoneController::class, 'store']);             // POST /admin/zones
    Route::post('/merge',          [CampingZoneController::class, 'merge']);             // POST /admin/zones/merge
    Route::post('/bulk-assign',    [CampingZoneController::class, 'bulkAssignToCentre']); // POST /admin/zones/bulk-assign
    Route::post('/import-geojson', [CampingZoneController::class, 'importGeoJson']);     // POST /admin/zones/import-geojson

    // ── Routes DYNAMIQUES — avec {id} — EN DERNIER ────────────────────────

    Route::get('/{id}',                 [CampingZoneController::class, 'show']);                   // GET    /admin/zones/{id}
    Route::put('/{id}',                 [CampingZoneController::class, 'update']);                 // PUT    /admin/zones/{id}
    Route::delete('/{id}',              [CampingZoneController::class, 'destroy']);                // DELETE /admin/zones/{id}
    Route::patch('/{id}/validate',      [CampingZoneController::class, 'validateZone']);           // PATCH  /admin/zones/{id}/validate
    Route::patch('/{id}/toggle-status', [CampingZoneController::class, 'toggleZoneStatus']);       // PATCH  /admin/zones/{id}/toggle-status
    Route::post('/{id}/adjust-polygon', [CampingZoneController::class, 'adjustPolygonWithRoutes']); // POST  /admin/zones/{id}/adjust-polygon
    Route::post('/{id}/photos',                      [CampingZoneController::class, 'addPhotos']);     // POST   /admin/zones/{id}/photos
    Route::delete('/{id}/photos/{photoId}',          [CampingZoneController::class, 'deletePhoto']);   // DELETE /admin/zones/{id}/photos/{photoId}
    Route::patch('/{id}/photos/{photoId}/cover',     [CampingZoneController::class, 'setCoverPhoto']); // PATCH  /admin/zones/{id}/photos/{photoId}/cover

});
    
    // -------------------- SERVICE CATEGORIES MANAGEMENT --------------------
    Route::resource('service-categories', ServiceCategoryController::class);
    
    // -------------------- SHOP (BOUTIQUE) MANAGEMENT --------------------
    Route::prefix('boutiques')->group(function () {
        Route::get('/',                    [AdminBoutiqueController::class, 'index']);
        Route::patch('/{id}/activate',     [AdminBoutiqueController::class, 'activate']);
        Route::patch('/{id}/deactivate',   [AdminBoutiqueController::class, 'deactivate']);
        Route::delete('/{id}',             [AdminBoutiqueController::class, 'destroy']);
    });

    // -------------------- NOTIFICATIONS MANAGEMENT --------------------
    Route::prefix('notifications')->group(function () {
        Route::post('/send', [AdminNotificationController::class, 'send']);
        Route::get('/templates', [AdminNotificationController::class, 'getTemplates']);
        Route::post('/templates', [AdminNotificationController::class, 'createTemplate']);
        Route::put('/templates/{id}', [AdminNotificationController::class, 'updateTemplate']);
        Route::delete('/templates/{id}', [AdminNotificationController::class, 'deleteTemplate']);
        Route::get('/logs', [AdminNotificationController::class, 'getLogs']);
        Route::get('/stats', [AdminNotificationController::class, 'getAdminStats']);
    });
});

// ==================== ADMIN COMMENT ROUTES ====================


// ==================== ADMIN FEEDBACK ROUTES ====================
Route::middleware(['auth:sanctum', 'admin'])->prefix('feedbacks')->group(function () {
    Route::post('/{id}/moderate', [FeedbackController::class, 'moderate']);
    
    // -------------------- ADMIN COMMENT ROUTES --------------------
    Route::prefix('comments')->group(function () {
        Route::post('/{id}/pin', [CommentController::class, 'togglePin']);
        Route::post('/{id}/hide', [CommentController::class, 'toggleHide']);
    });
});

// ==================== PROMO CODE ROUTES ====================

// Public: validate a promo code (used by booking forms — requires auth so we know who's applying)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/promo-code/validate', [PromoCodeController::class, 'checkCode']);
});

// Admin: full CRUD for promo codes
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::prefix('promo-codes')->group(function () {
        Route::get('/',           [PromoCodeController::class, 'index']);
        Route::post('/',          [PromoCodeController::class, 'store']);
        Route::get('/{id}',       [PromoCodeController::class, 'show']);
        Route::patch('/{id}/toggle', [PromoCodeController::class, 'toggle']);
        Route::delete('/{id}',    [PromoCodeController::class, 'destroy']);
    });
});

// ==================== WEB ADMIN ROUTES (for web interface) ====================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('service-categories', ServiceCategoryController::class);
});

// ==================== POPUP ROUTES ====================

// Authenticated: fetch active (non-dismissed) popups & dismiss one
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/admin-popups/active',           [PopupController::class, 'active']);
    Route::get('/admin-popups/welcome',          [PopupController::class, 'welcome']);
    Route::post('/admin-popups/{popup}/dismiss', [PopupController::class, 'dismiss']);
});

// Admin: full CRUD for popups
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::prefix('popups')->group(function () {
        Route::get('/',                    [PopupController::class, 'index']);
        Route::post('/',                   [PopupController::class, 'store']);
        Route::put('/{popup}',             [PopupController::class, 'update']);
        Route::delete('/{popup}',          [PopupController::class, 'destroy']);
        Route::patch('/{popup}/toggle',    [PopupController::class, 'toggle']);
    });
});
