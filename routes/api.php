<?php

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Controller;

// Authentication Controllers
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\VerifyEmailController;

// Public Controllers
use App\Http\Controllers\Annonce\AnnonceController;
use App\Http\Controllers\Boutique\BoutiqueController;
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
use App\Http\Controllers\Message\ConversationController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Message\MessageController;
use App\Notifications\EventInviteNotification;

// Event Controllers
use App\Http\Controllers\Event\EventInterestController;
use App\Http\Controllers\Event\EventParticipantController;
use App\Http\Controllers\Reservation\ReservationEventController;
use App\Http\Controllers\Reservation\ReservationCancellationController;
use App\Http\Controllers\Reservation\UnifiedReservationController;
use App\Http\Controllers\Groupe\FollowGroupeController;

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
use App\Http\Controllers\Admin\TestUserController;

// API Services
use App\Services\ZoneSearchService;
use Illuminate\Support\Facades\Broadcast;

// ==================== BROADCAST ROUTES ====================
Broadcast::routes(['middleware' => ['auth:sanctum']]);

// ==================== PUBLIC ROUTES (NO AUTH REQUIRED) ====================

// -------------------- AUTHENTICATION & VERIFICATION --------------------
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

// -------------------- PUBLIC EVENTS --------------------
Route::prefix('events')->group(function () {
    Route::get('/', [EventController::class, 'index']);
    Route::get('/search', [EventController::class, 'search']);
    Route::get('/groups/{groupId}', [EventController::class, 'getGroupEvents']);
    Route::get('/my-events', [EventController::class, 'myEvents']);
    Route::get('/{id}/share-links', [EventController::class, 'getEventShareLinks']);
    Route::get('/{id}/copy-link', [EventController::class, 'getEventCopyLink']);
    Route::get('/{id}', [EventController::class, 'getEventDetails']);
});

// -------------------- PUBLIC GROUPS --------------------
Route::get('/groupes/search', [GroupController::class, 'searchGroups']);
Route::get('/groupes', [GroupController::class, 'listGroupsWithFeedbacks']);

// -------------------- PUBLIC CAMPING ZONES --------------------
Route::prefix('zones')->group(function () {
    Route::get('/',[CampingZonesController::class, 'index']);
    Route::get('/export-geojson',[CampingZonesController::class, 'exportGeoJson']);
    Route::get('/search',[CampingZonesController::class, 'search']);
    Route::get('/nearby',[CampingZonesController::class, 'nearby']);
    Route::get('/cluster',[CampingZonesController::class, 'clusterZones']);
    Route::get('/region',[CampingZonesController::class, 'zonesByRegion']);
    Route::get('/top',[CampingZonesController::class, 'topZones']);
    Route::get('/top-by-season',[CampingZonesController::class, 'topZonesBySeason']);
    Route::get('/recommend',[CampingZonesController::class, 'recommendZones']);
    Route::get('/exclude-non-relevant',[CampingZonesController::class, 'excludeNonRelevantZones']);
    Route::get('/{id}',[CampingZonesController::class, 'show']);
    Route::get('/{id}/validate-coordinates', [CampingZonesController::class, 'validateCoordinates']);
    Route::get('/{id}/stats',[CampingZonesController::class, 'zoneStats']);
});

// -------------------- PUBLIC CAMPING CENTERS --------------------
Route::prefix('centres')->group(function () {
    Route::get('/map', [CampingCentresController::class, 'getCentresMap']);
    Route::get('/search', [CampingCentresController::class, 'searchCentres']);
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
    
    // -------------------- CURRENT USER --------------------
    Route::get('/user', fn(Request $request) => $request->user());
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
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
    
    // -------------------- EMAIL VERIFICATION --------------------
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store']);
    Route::post('/send-verification-current', [VerifyEmailController::class, 'sendVerificationForCurrentUser']);
    Route::get('/user/verification-status', function (Request $request) {
        return response()->json([
            'verified' => $request->user()->hasVerifiedEmail(),
            'email' => $request->user()->email,
        ]);
    });
    Route::put('/user/password', [PasswordController::class, 'update']);
    
    // -------------------- PROFILE MANAGEMENT --------------------
    Route::prefix('profile')->group(function () {
        // Basic profile
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::put('/change-password', [ProfileController::class, 'changePassword']);
        Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
        Route::post('/updateInfo', [ProfileController::class, 'updateInfo']);
        
        // Photos
        Route::prefix('photos')->group(function () {
            Route::get('/', [ProfileController::class, 'getProfilePhotos']);
            Route::post('/', [ProfileController::class, 'storeOrUpdateProfilePhotos']);
            Route::delete('/{photoId}', [ProfileController::class, 'deletePhoto']);
            Route::put('/{photoId}/cover', [ProfileController::class, 'setCoverPhoto']);
            Route::put('/reorder', [ProfileController::class, 'reorderPhotos']);
        });
        
        // Service Categories
        Route::get('/service-categories', [ProfileController::class, 'getServiceCategories']);
        
        // Center Management
        Route::prefix('center/{centerId}')->where(['centerId' => '[0-9]+'])->group(function () {
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
        
        // Profile lookup (must be last)
        Route::get('/{type}/{userId}', [ProfileController::class, 'getProfileDetails'])
            ->where('type', 'guide|centre|groupe|fournisseur')
            ->where('userId', '[0-9]+');
    });
    
    // -------------------- USER SEARCH --------------------
    Route::get('/users/search', [ProfileController::class, 'searchUsers']);
    Route::get('/annonces/user-likes', [AnnonceController::class, 'getUserLikes']);
    // -------------------- ANNOUNCEMENTS (Authenticated actions) --------------------
    Route::prefix('annonces')->group(function () {
        Route::get('/user/{userId}', [AnnonceController::class, 'index']);
        Route::get('/archived/{userId}', [AnnonceController::class, 'getArchived']);
        
        Route::post('/', [AnnonceController::class, 'store']);
        
        Route::post('/{id}', [AnnonceController::class, 'update']);
        Route::delete('/{id}', [AnnonceController::class, 'destroy']);
        Route::patch('/{id}/archive', [AnnonceController::class, 'archive']);
        Route::patch('/{id}/unarchive', [AnnonceController::class, 'unarchive']);
        Route::post('/{id}/like', [AnnonceController::class, 'like']);
        Route::post('/{id}/unlike', [AnnonceController::class, 'unlike']);
        Route::get('/{id}/likes', [AnnonceController::class, 'getLikes']);
        Route::get('/{id}/likes/check', [AnnonceController::class, 'checkLike']);
        Route::get('/{id}', [AnnonceController::class, 'show']);

    });
    // -------------------- FEEDBACKS (Authenticated actions) --------------------
    Route::prefix('feedbacks')->group(function () {
        Route::get('/', [FeedbackController::class, 'index']);
        Route::post('/', [FeedbackController::class, 'store']);
        Route::put('/{id}', [FeedbackController::class, 'update']);
        Route::delete('/{id}', [FeedbackController::class, 'destroy']);
        Route::post('/zone/{zoneId}', [FeedbackController::class, 'storeZone']);
    });
    
    // -------------------- NOTIFICATIONS --------------------
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
    
    // -------------------- MESSAGES & CONVERSATIONS --------------------
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
    
    // -------------------- EVENTS (Authenticated) --------------------
    Route::prefix('events')->group(function () {
        Route::get('/nearby/{userId}', [EventController::class, 'notifyNearbyEvents']);
        Route::get('/{id}/participants', [EventController::class, 'participants']);
        Route::get('/{id}/details', [EventController::class, 'show']);
        Route::get('/{id}/participants/stats', [ReservationEventController::class, 'participantStats']);
        Route::post('/{id}/interest', [EventInterestController::class, 'toggleInterest']);
        
        // Group event management
        Route::middleware(['group'])->group(function () {
            Route::post('/', [EventController::class, 'store']);
            Route::put('/{id}', [EventController::class, 'update']);
            Route::delete('/{id}', [EventController::class, 'destroy']);
            Route::patch('/participants/{id}/status', [EventController::class, 'updateStatus']);
            Route::post('/{id}/invite', [EventController::class, 'sendInvites']);
        });
    });
    
    // -------------------- EVENT RESERVATIONS --------------------
    Route::prefix('reservations')->group(function () {
        Route::get('/my-participations', [ReservationEventController::class, 'myParticipations']);
        Route::get('/passees', [ReservationEventController::class, 'mesReservationsPassees']);
        Route::post('/event', [ReservationEventController::class, 'createReservationWithPayment']);
        Route::put('/event/{id}/simulate-payment', [ReservationEventController::class, 'simulatePayment']);
        Route::post('/event/{eventId}/cancel-by-user', [ReservationEventController::class, 'cancelByUser']);
        Route::get('/event/{id}', [ReservationEventController::class, 'show']);
        Route::put('/{id}', [ReservationEventController::class, 'updateReservation']);
        Route::patch('/{id}/status', [ReservationEventController::class, 'updateStatus']);
        Route::delete('/{id}', [ReservationEventController::class, 'destroy']);
        
        // Event participants
        Route::post('/events/{event}/participants/manual', [ReservationEventController::class, 'addManualParticipant']);
        Route::get('/events/{eventId}/participants/search', [ReservationEventController::class, 'search']);
        Route::put('/participants/{id}/update', [ReservationEventController::class, 'updateManualParticipant']);
        Route::get('/events/{event}/participants', [EventParticipantController::class, 'index']);
    });
    
    // -------------------- PAYMENTS --------------------
    Route::prefix('payment')->group(function () {
        Route::post('/event/{eventId}/payer', [PaymentController::class, 'initPayment']);
        Route::get('/confirm/{reservationId}', [PaymentController::class, 'confirmerPaiement']);
        Route::get('/konnect/success', [PaymentController::class, 'konnectCallback'])->name('konnect.success');
        Route::get('/konnect/fail', [PaymentController::class, 'konnectCallback'])->name('konnect.fail');
        Route::post('/konnect/webhook', [PaymentController::class, 'webhookKonnect']);
    });
    
    // -------------------- RESERVATION TICKETS --------------------
    Route::get('/reservation/{id}/imprimer', [PaymentController::class, 'imprimerTicket']);
    Route::get('/reservation/{id}/telecharger', [PaymentController::class, 'telechargerTicket']);
    
    // -------------------- RESERVATION CANCELLATION --------------------
    Route::post('/reservations/{id}/annuler', [ReservationCancellationController::class, 'annulerReservation']);
    
    // -------------------- GROUPS --------------------
    Route::prefix('groupes')->group(function () {
        Route::post('/{groupeId}/follow', [FollowGroupeController::class, 'follow']);
        Route::delete('/{groupeId}/unfollow', [FollowGroupeController::class, 'unfollow']);
        Route::get('/me/followed-groupes', [FollowGroupeController::class, 'myFollowedGroupes']);
        Route::get('/{groupe}/feedbacks', [GroupController::class, 'listForGroup']);
    });
    
    // -------------------- GROUP RESERVATION MANAGEMENT --------------------
    Route::prefix('group/reservations')->group(function () {
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
    
    // -------------------- GROUP EVENTS STATISTICS --------------------
    Route::prefix('group/events')->group(function () {
        Route::get('/{event_id}/participants', [ReservationEventController::class, 'participants']);
        Route::get('/{event_id}/stats', [ReservationEventController::class, 'participantStats']);
        Route::get('/{event_id}/search', [ReservationEventController::class, 'search']);
    });
    
    // -------------------- CAMPING ZONES (Authenticated actions) --------------------
    Route::prefix('zones')->group(function () {
        Route::post('/suggest',[CampingZonesController::class, 'suggestZone']);
        Route::post('/{id}/gallery',[CampingZonesController::class, 'addGallery']);
        Route::post('/{id}/review',[CampingZonesController::class, 'markForReview']);
        Route::get('/recommended/{userId}',[CampingZonesController::class, 'recommendedZones']);
        Route::get('/personalized/{userId}',[CampingZonesController::class, 'personalizedRecommendations']);
        Route::get('/{userId}/recommendations',[CampingZonesController::class, 'personalizedRecommendations']);
    });
    // -------------------- CAMPING CENTERS (Authenticated actions) --------------------
    Route::prefix('centres')->group(function () {
        Route::post('/suggest', [CampingCentresController::class, 'suggestCentre']);
        Route::get('/favoris', [CampingCentresController::class, 'listFavoris']);
        Route::post('/{id}/favoris', [CampingCentresController::class, 'toggleFavoris']);
        Route::get('/{centreId}/stats', [CampingCentresController::class, 'centreStats']);
    });
    
    // -------------------- ZONE POLYGONS --------------------
    Route::prefix('zone-polygons')->group(function () {
        Route::post('/', [ZonePolygonController::class, 'store']);
        Route::put('/{id}', [ZonePolygonController::class, 'update']);
        Route::delete('/{id}', [ZonePolygonController::class, 'destroy']);
        Route::get('/zone/{zoneId}', [ZonePolygonController::class, 'listByZone']);
    });
    
    // -------------------- FAVORITES --------------------
    Route::prefix('favoris')->group(function () {
        Route::post('/zone/{id}', [FavorisController::class, 'toggleZone']);
        Route::get('/liste', [FavorisController::class, 'listFavoris']);
    });
    
    // -------------------- REPORTS --------------------
    Route::post('/zones/{zoneId}/signales', [SignalementZoneController::class, 'store']);
    
    // -------------------- EVENT REMINDERS --------------------
    Route::post('/events/{event}/send-reminders', [NotificationController::class, 'sendRemindersForEvent']);
    
    // -------------------- ADDITIONAL RESERVATION ROUTES --------------------
    Route::get('/reservation/fournisseur', [ReservationMaterielleController::class, 'show']);
});

// ==================== ROLE-SPECIFIC ROUTES ====================

// -------------------- PUBLISHER ROUTES --------------------
Route::middleware(['auth:sanctum', 'can.publish'])->prefix('annonces')->group(function () {
    Route::post('/', [AnnonceController::class, 'store']);
    Route::match(['put', 'post'], '/{id}', [AnnonceController::class, 'update']);
    Route::delete('/{id}', [AnnonceController::class, 'destroy']);
    Route::get('/archived/{userId}', [AnnonceController::class, 'getArchived']);
    Route::patch('/{id}/archive', [AnnonceController::class, 'archive']);
    Route::patch('/{id}/unarchive', [AnnonceController::class, 'unarchive']);

});
        Route::patch('reservation/materiel/{id}/confirm',     [ReservationMaterielleController::class, 'confirm']);

// -------------------- FOURNISSEUR ROUTES --------------------
Route::middleware(['auth:sanctum', 'fournisseur'])->group(function () {
    
    // Boutiques
    Route::prefix('boutiques')->group(function () {
        Route::get('/edit/{boutique_id}', [BoutiqueController::class, 'edit']);
        Route::post('/', [BoutiqueController::class, 'add']);
        Route::post('/update', [BoutiqueController::class, 'update']);
        Route::delete('/', [BoutiqueController::class, 'destroy']);
    });
    
    // Materielles
    Route::prefix('materielles')->group(function () {
        Route::get('/{materielle_id}/edit', [MaterielleController::class, 'edit']);
        Route::post('/', [MaterielleController::class, 'store']);
        Route::post('/{id}', [MaterielleController::class, 'update']);
        Route::delete('/{id}', [MaterielleController::class, 'destroy']);
        Route::patch('/{id}/activate', [MaterielleController::class, 'activate']);
        Route::patch('/{id}/deactivate', [MaterielleController::class, 'deactivate']);
    });
    
    // Equipment Reservations
    Route::prefix('reservation/materiel')->group(function () {
        Route::patch('/{id}/reject',      [ReservationMaterielleController::class, 'reject']);
        Route::post('/{id}/verify-pin',   [ReservationMaterielleController::class, 'verifyPin']);
        Route::patch('/{id}/returned',    [ReservationMaterielleController::class, 'markReturned']);
        Route::patch('/{id}/cancel',      [ReservationMaterielleController::class, 'cancelByFournisseur']);
    });
    
    Route::prefix('reservation/fournisseur')->group(function () {
        Route::patch('/cancel/{id}', [ReservationMaterielleController::class, 'cancelByFournisseur']);
    });
});

// -------------------- CAMPING CENTER RESERVATIONS --------------------
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
    
    // Routes for centers only
    Route::middleware(['centre'])->group(function () {
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

// ==================== ADMIN ROUTES ====================
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {

    Route::prefix('admin/annonces')->group(function () {
        Route::patch('/{id}/activate',   [AnnonceController::class, 'activate']);
        Route::patch('/{id}/deactivate', [AnnonceController::class, 'deactivate']);
    });
    // Dashboard/Utility
    Route::post('/restock-expired', [ReservationMaterielleController::class, 'restockExpiredReservations']);
    
    // -------------------- USER MANAGEMENT --------------------
    Route::prefix('users')->group(function () {
        Route::get('/', [AdminUserController::class, 'index']);
        Route::get('/stats', [AdminUserController::class, 'stats']);
        Route::get('/stats/overview', [AdminUserController::class, 'stats']);
        Route::get('/roles/all', function () {
            return response()->json([
                'success' => true,
                'data' => \App\Models\Role::all()
            ]);
        });
        
        Route::prefix('{id}')->group(function () {
            Route::get('/', [AdminUserController::class, 'show']);
            Route::put('/', [AdminUserController::class, 'update']);
            Route::delete('/', [AdminUserController::class, 'destroy']);
            Route::put('/toggle-activation', [AdminUserController::class, 'toggleActivation']);
            Route::post('/reset-password', [AdminUserController::class, 'resetPassword']);
            Route::post('/send-email', [AdminUserController::class, 'sendEmail']);
            
            // Documents
            Route::prefix('documents')->group(function () {
                Route::post('/', [AdminUserController::class, 'uploadDocument']);
                Route::get('/{documentType}/download', [AdminUserController::class, 'downloadDocument']);
                Route::get('/{documentType}/view', [AdminUserController::class, 'viewDocument']);
                Route::delete('/{documentType}', [AdminUserController::class, 'deleteDocument']);
            });
            
            // Photos
            Route::prefix('photos')->group(function () {
                Route::post('/', [AdminUserController::class, 'uploadPhotos']);
                Route::get('/', [AdminUserController::class, 'getPhotos']);
                Route::delete('/{photoId}', [AdminUserController::class, 'deletePhoto']);
            });
        });
    });
    
    // -------------------- EVENT MANAGEMENT --------------------
    Route::prefix('events')->group(function () {
        Route::get('/', [AdminEventController::class, 'index']);
        Route::get('/{id}', [AdminEventController::class, 'show']);
        Route::post('/', [AdminEventController::class, 'store']);
        Route::delete('/{id}', [AdminEventController::class, 'destroy']);
        Route::patch('/{id}/activate', [AdminEventController::class, 'activate']);
        Route::patch('/{id}/deactivate', [AdminEventController::class, 'deactivate']);
        Route::patch('/{id}/cancel', [AdminEventController::class, 'cancelEvent']);
        Route::get('/{id}/reservations', [AdminEventController::class, 'reservations']);
        Route::get('/{id}/statistics', [AdminEventController::class, 'statistics']);
        Route::get('/{id}/export-csv', [AdminEventController::class, 'exportReservationsCsv']);
    });
    
    // -------------------- RESERVATION MANAGEMENT --------------------
    Route::prefix('reservations')->group(function () {
        Route::get('/', [AdminEventReservationController::class, 'listReservations']);
        Route::get('/export', [AdminEventReservationController::class, 'exportReservations']);
        Route::get('/stats', [AdminEventReservationController::class, 'stats']);
        Route::get('/{id}', [AdminEventReservationController::class, 'getReservationDetails']);
        Route::put('/{id}', [AdminEventReservationController::class, 'update']);
        Route::delete('/{id}', [AdminEventReservationController::class, 'deleteReservation']);
        Route::post('/events/{eventId}/participants/manual', [AdminEventReservationController::class, 'addManualParticipant']);
    });
    
    // -------------------- FEEDBACK MANAGEMENT --------------------
    Route::prefix('feedbacks')->group(function () {
        Route::post('/{id}/moderate', [AdminUserController::class, 'moderate']);
    });
    
    // -------------------- CENTER RESERVATION MANAGEMENT --------------------
    Route::get('/reservation/centre/show/{id}', [ReservationsCentreController::class, 'show']);
    
    // -------------------- ZONE REPORTS --------------------
    Route::prefix('signals')->group(function () {
        Route::get('/zones/{zoneId}', [SignaleZoneController::class, 'index']);
        Route::put('/{id}/validate', [SignaleZoneController::class, 'validateSignalement']);
        Route::put('/{id}/reject', [SignaleZoneController::class, 'rejectSignalement']);
    });
    
    // -------------------- CAMPING CENTERS MANAGEMENT --------------------
    Route::prefix('centres')->group(function () {
        Route::get('/', [CampingCentreController::class, 'index']);
        Route::get('/stats', [CampingCentreController::class, 'stats']);
        Route::get('/registered', [CampingCentreController::class, 'registeredCentres']);
        Route::get('/nearby', [CampingCentreController::class, 'nearby']);
        Route::get('/suggest-zones', [CampingCentreController::class, 'suggestZones']);
        Route::get('/search', [CampingCentreController::class, 'search']);
        Route::post('/', [CampingCentreController::class, 'store']);
        Route::get('/{id}', [CampingCentreController::class, 'show']);
        Route::put('/{id}', [CampingCentreController::class, 'update']);
        Route::post('/{id}/assign-zones', [CampingCentreController::class, 'assignZones']);
        Route::patch('/{id}/toggle-status', [CampingCentreController::class, 'toggleStatus']);
    });
    
    // -------------------- CAMPING ZONES MANAGEMENT --------------------
    Route::prefix('zone')->group(function () {
        Route::get('/stats', [CampingZoneController::class, 'stats']);
        Route::post('/', [CampingZoneController::class, 'store']);
        Route::put('/{id}', [CampingZoneController::class, 'update']);
        Route::delete('/{id}', [CampingZoneController::class, 'destroy']);
        Route::patch('/{id}/validate', [CampingZoneController::class, 'validateZone']);
        Route::patch('/{id}/toggle-status', [CampingZoneController::class, 'toggleZoneStatus']);
        Route::post('/merge', [CampingZoneController::class, 'merge']);
        Route::post('/bulk-assign', [CampingZoneController::class, 'bulkAssignToCentre']);
        Route::post('/zones/{id}/adjust-polygon', [CampingZoneController::class, 'adjustPolygonWithRoutes']);
        Route::post('/zones/import-geojson', [CampingZoneController::class, 'importGeoJson']);
    });
    // -------------------- CAMPING ZONES (Admin actions) --------------------
    Route::prefix('zones')->group(function () {
        Route::post('/',                    [CampingZonesController::class, 'store']);
        Route::patch('/{id}',              [CampingZonesController::class, 'update']);
        Route::delete('/{id}',             [CampingZonesController::class, 'destroy']);
        Route::patch('/{id}/validate',     [CampingZonesController::class, 'validateZone']);
        Route::patch('/{id}/toggle-status',[CampingZonesController::class, 'toggleZoneStatus']);
    });
    
    // -------------------- SERVICE CATEGORIES MANAGEMENT --------------------
    Route::resource('service-categories', ServiceCategoryController::class);
    
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
});

// ==================== WEB ADMIN ROUTES (for web interface) ====================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('service-categories', ServiceCategoryController::class);
});