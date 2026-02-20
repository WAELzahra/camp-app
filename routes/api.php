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
use App\Http\Controllers\message_chat\PrivateChatController;
use App\Http\Controllers\GroupChat\ChatGroupController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Favoris\FavorisController;

// Event Controllers
use App\Http\Controllers\Event\EventInterestController;
use App\Http\Controllers\Event\EventParticipantController;
use App\Http\Controllers\Reservation\ReservationEventController;
use App\Http\Controllers\Reservation\ReservationCancellationController;
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
// Broadcast::routes(['middleware' => ['auth:sanctum']]);

// ==================== ROUTES PUBLIQUES (NO AUTH REQUIRED) ====================
// Email Verification
Route::post('/send-verification', [VerifyEmailController::class, 'sendVerification']);
Route::post('/verify-by-token', [VerifyEmailController::class, 'verifyByToken']);
Route::post('/verify-by-code', [VerifyEmailController::class, 'verifyByCode']);
Route::post('/resend-verification', [VerifyEmailController::class, 'resendVerification']);
Route::get('/verification-status/{id}', [VerifyEmailController::class, 'verificationStatus']);
// Public profile lookup - separate route group
Route::prefix('profile')->group(function () {
    Route::get('/public/{id}', [ProfileController::class, 'showById']);
});
// Authentication
Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login');
Route::post('/forgot-password', [NewPasswordController::class, 'sendResetEmail']);
Route::post('/reset-password', [NewPasswordController::class, 'resetPassword']);
Route::post('/verify-password', [NewPasswordController::class, 'verifyResetCode']);

// Email Verification Routes (Sanctum signed)
Route::middleware('signed')->get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();
    return response()->json(['message' => 'Email verified successfully']);
})->name('verification.verify');

// Social Auth (commented out but kept for reference)
// Route::get('/auth/google/url', [SocialAuthController::class, 'getSocialAuthUrls']);
// Route::get('/auth/google', [SocialAuthController::class, 'redirectToGoogle'])->name('google.redirect');
// Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback']);
// Route::get('/auth/facebook', [SocialAuthController::class, 'redirectToFacebook'])->name('facebook.redirect');
// Route::get('/auth/facebook/callback', [SocialAuthController::class, 'handleFacebookCallback']);

// Public Announcements
Route::get('/annonces/show/{id}', [AnnonceController::class, 'show']);
Route::get('/annonces/user/{id}', [AnnonceController::class, 'index']);
Route::get('/annonces/{id}/likes', [AnnonceController::class, 'getLikes']);

Route::prefix('comments')->group(function () {
    Route::get('/annonce/{annonceId}', [CommentController::class, 'index']);
    Route::post('/annonce/{annonceId}', [CommentController::class, 'store'])->middleware('auth:sanctum');
    Route::post('/annonce/{annonceId}/reply/{parentId}', [CommentController::class, 'store'])->middleware('auth:sanctum');
    Route::put('/{id}', [CommentController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/{id}', [CommentController::class, 'destroy'])->middleware('auth:sanctum');
    Route::post('/{id}/like', [CommentController::class, 'toggleLike'])->middleware('auth:sanctum');
    
    // Admin routes
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/{id}/pin', [CommentController::class, 'togglePin']);
        Route::post('/{id}/hide', [CommentController::class, 'toggleHide']);
    });
});

// Public Shop
Route::get('/boutique', [BoutiqueController::class, 'index']);
Route::get('/boutique/show/{id}', [BoutiqueController::class, 'show']);



// Public Equipment
Route::get('/materielle/compare/{id1}-{id2}', [MaterielleController::class, 'compare']);
Route::get('/materielle/index/{fournisseur_id}', [MaterielleController::class, 'index']);
Route::get('/materielle/show/{materielle_id}', [MaterielleController::class, 'show']);

// Public Events
Route::get('/events/search', [EventController::class, 'search']);
Route::get('/events/{id}/participants/stats', [ReservationEventController::class, 'participantStats']);
Route::get('/events/{id}/participants', [ReservationEventController::class, 'participants']);
Route::get('/events/{id}/share-links', [EventController::class, 'getEventShareLinks']);
Route::get('/events/{id}/copy-link', [EventController::class, 'getEventCopyLink']);
Route::get('/events/{id}', [EventController::class, 'getEventDetails']);
Route::get('/events', [EventController::class, 'index']);

// Public Groups
Route::get('/groupes/search', [GroupController::class, 'searchGroups']);
Route::get('/groupes', [GroupController::class, 'listGroupsWithFeedbacks']);

// Public Camping Zones
Route::prefix('zones')->group(function () {
    Route::get('/', [CampingZonesController::class, 'index']);
    Route::get('/export-geojson', [CampingZonesController::class, 'exportGeoJson']);
    Route::get('/search', [CampingZonesController::class, 'search']);
    Route::get('/nearby', [CampingZonesController::class, 'nearby']);
    Route::get('/cluster', [CampingZonesController::class, 'clusterZones']);
    Route::get('/region', [CampingZonesController::class, 'zonesByRegion']);
    Route::get('/top-by-season', [CampingZonesController::class, 'topZonesBySeason']);
    Route::get('/exclude-non-relevant', [CampingZonesController::class, 'excludeNonRelevantZones']);
    Route::post('/suggest', [CampingZonesController::class, 'suggestZone']);
    Route::get('/{id}', [CampingZonesController::class, 'show']);
    Route::get('/{id}/validate-coordinates', [CampingZonesController::class, 'validateCoordinates']);
});

// Public Camping Centers
Route::prefix('centres')->group(function () {
    Route::get('/map', [CampingCentresController::class, 'getCentresMap']);
    Route::get('/search', [CampingCentresController::class, 'searchCentres']);
    Route::get('/{id}/zones', [CampingCentresController::class, 'listZones'])->whereNumber('id');
    Route::get('/{id}', [CampingCentresController::class, 'showCentre'])->whereNumber('id');
});

// Public Zones with Centers
Route::prefix('public-zones')->group(function () {
    Route::get('/with-centres', [PublicCampingController::class, 'zonesWithCentres']);
    Route::get('/filters', [PublicCampingController::class, 'zonesWithFilters']);
    Route::get('/nearby', [PublicCampingController::class, 'nearbyZones']);
});

// General Public Routes
Route::prefix('public')->group(function () {
    Route::get('/zones-centres', [PublicCampingController::class, 'zonesWithCentres']);
    Route::get('/centre/{id}', [PublicCampingController::class, 'showCentre']);
    Route::get('/zones-filters', [PublicCampingController::class, 'zonesWithFilters']);
    Route::get('/zones-nearby', [PublicCampingController::class, 'nearbyZones']);
});

// Center Services API (Public)
Route::prefix('v1')->group(function () {
    Route::get('centers/services', [CenterServiceApiController::class, 'centersWithServices']);
    Route::get('centers/{center}/services', [CenterServiceApiController::class, 'centerServices']);
    Route::get('service-categories', [CenterServiceApiController::class, 'serviceCategories']);
    Route::post('calculate-price', [CenterServiceApiController::class, 'calculatePrice']);
});

// Payment Callback (Public)
Route::get('/flouci/callback', [PaymentController::class, 'callback']);


// Feedback routes - Place this after your public routes
Route::prefix('feedbacks')->group(function () {
    // Public/authenticated routes
    Route::get('/', [FeedbackController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/statistics', [FeedbackController::class, 'statistics']);
    Route::get('/target/{type}/{targetId}', [FeedbackController::class, 'getTargetFeedbacks']);
    Route::get('/zone/{zoneId}', [FeedbackController::class, 'listZone']);
    
    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [FeedbackController::class, 'store']);
        Route::put('/{id}', [FeedbackController::class, 'update']);
        Route::delete('/{id}', [FeedbackController::class, 'destroy']);
        Route::post('/zone/{zoneId}', [FeedbackController::class, 'storeZone']);
    });
    
    // Admin routes
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/{id}/moderate', [FeedbackController::class, 'moderate']);
    });
});

// ==================== ROUTES AUTHENTIFIÉES (REQUIRE SANCTUM AUTH) ====================

Route::middleware('auth:sanctum')->group(function () {

    // Group Chat Routes
    Route::prefix('group-chat')->group(function () {
        // Group management
        Route::post('/', [ChatGroupController::class, 'store']);
        Route::get('/my-groups', [ChatGroupController::class, 'myGroups']);
        Route::get('/my-memberships', [ChatGroupController::class, 'myMemberships']);
        Route::delete('/{id}', [ChatGroupController::class, 'destroy']);
        Route::patch('/{id}/archive', [ChatGroupController::class, 'archive']);
        Route::put('/{id}/rename', [ChatGroupController::class, 'renameGroup']);
        
        // Invitations
        Route::get('/join/{token}', [ChatGroupController::class, 'joinByToken']);
        
        // Messages
        Route::get('/{chat_group_id}/messages', [ChatGroupController::class, 'getMessages']);
        Route::post('/{chat_group_id}/message', [ChatGroupController::class, 'sendMessage']);
        Route::post('/messages/{message_id}/react', [ChatGroupController::class, 'reactToMessage']);
        Route::delete('/messages/{message_id}/react/{reaction}', [ChatGroupController::class, 'removeReaction']);
        Route::patch('/messages/{message_id}/pin', [ChatGroupController::class, 'pinMessage']);
        
        // Members
        Route::get('/{chat_group_id}/members', [ChatGroupController::class, 'getMembers']);
        Route::delete('/{chat_group_id}/leave', [ChatGroupController::class, 'leaveGroup']);
        Route::delete('/{chat_group_id}/members/{user_id}', [ChatGroupController::class, 'removeMember']);
        Route::post('/{chat_group_id}/members/{user_id}/mute', [ChatGroupController::class, 'muteMember']);
        Route::post('/{chat_group_id}/members/{user_id}/unmute', [ChatGroupController::class, 'unmuteMember']);
        
        // Typing status
        Route::post('/{chat_group_id}/typing', [ChatGroupController::class, 'typingStatus']);
        Route::get('/{chat_group_id}/typing', [ChatGroupController::class, 'typingUsers']);
        
        // Statistics
        Route::get('/{chat_group_id}/stats', [ChatGroupController::class, 'getStats']);
    });
    Route::post('/annonces/{id}/like', [AnnonceController::class, 'like']);
    Route::post('/annonces/{id}/unlike', [AnnonceController::class, 'unlike']);
    Route::get('/annonces/{id}/check-like', [AnnonceController::class, 'checkLike']);
    // -------------------- USER & AUTH MANAGEMENT --------------------
    Route::get('/user', fn(Request $request) => $request->user());
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store']);
    Route::post('/send-verification-current', [VerifyEmailController::class, 'sendVerificationForCurrentUser']);
    Route::put('/user/password', [PasswordController::class, 'update']);
    
    // Email verification status
    Route::get('/user/verification-status', function (Request $request) {
        return response()->json([
            'verified' => $request->user()->hasVerifiedEmail(),
            'email' => $request->user()->email,
        ]);
    });
    
    // Profile Management
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::put('/change-password', [ProfileController::class, 'changePassword']);
        Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
        Route::post('/updateInfo', [ProfileController::class, 'updateInfo']);
        
        // Photos Management
        Route::get('/photos', [ProfileController::class, 'getProfilePhotos']);
        Route::post('/photos', [ProfileController::class, 'storeOrUpdateProfilePhotos']);
        Route::delete('/photos/{photoId}', [ProfileController::class, 'deletePhoto']);
        Route::put('/photos/{photoId}/cover', [ProfileController::class, 'setCoverPhoto']);
        Route::put('/photos/reorder', [ProfileController::class, 'reorderPhotos']);
        
        // Service Categories (public for selection)
        Route::get('/service-categories', [ProfileController::class, 'getServiceCategories']);
        
        // Center Management (for center users only)
        Route::prefix('center/{centerId}')->where(['centerId' => '[0-9]+'])->group(function () {
            Route::put('/', [ProfileController::class, 'updateCenter']);
            
            // Center Services
            Route::prefix('services')->group(function () {
                Route::get('/', [ProfileController::class, 'getCenterServices']);
                Route::post('/', [ProfileController::class, 'updateCenterService']);
                Route::put('/{serviceId}', [ProfileController::class, 'updateCenterService'])->where(['serviceId' => '[0-9]+']);
                Route::delete('/{serviceId}', [ProfileController::class, 'deleteCenterService'])->where(['serviceId' => '[0-9]+']);
                Route::post('/custom', [ProfileController::class, 'addCustomService']);
            });
            
            // Center Equipment
            Route::prefix('equipment')->group(function () {
                Route::get('/', [ProfileController::class, 'getCenterEquipment']);
                Route::put('/', [ProfileController::class, 'updateCenterEquipment']);
            });
        });
        
        // Profile lookup (should be last to avoid conflicts)
        Route::get('/{type}/{userId}', [ProfileController::class, 'getProfileDetails'])
            ->where('type', 'guide|centre|groupe|fournisseur')
            ->where('userId', '[0-9]+');
    });
    
    // -------------------- EVENTS --------------------
    Route::post('/events', [EventController::class, 'store']);
    Route::put('/events/{id}', [EventController::class, 'update']);
    Route::delete('/events/{id}', [EventController::class, 'destroy']);
    Route::patch('/events/{event}/status', [EventController::class, 'updateStatus']);
    Route::post('/events/{id}/interest', [EventInterestController::class, 'toggleInterest']);
    Route::get('/events/nearby/{userId}', [EventController::class, 'notifyNearbyEvents']);
    
    // -------------------- EVENT RESERVATIONS --------------------
    Route::post('/reservation-event', [ReservationEventController::class, 'createReservationWithPayment']);
    Route::put('/reservation-event/{id}/simulate-payment', [ReservationEventController::class, 'simulatePayment']);
    Route::post('/event/{eventId}/payer', [PaymentController::class, 'initPayment']);
    Route::get('/reservation-event/{id}', [ReservationEventController::class, 'show']);
    Route::put('/reservations/{id}', [ReservationEventController::class, 'updateReservation']);
    Route::patch('/reservations/{id}/status', [ReservationEventController::class, 'updateStatus']);
    Route::delete('/reservations/{id}', [ReservationEventController::class, 'destroy']);
    Route::get('/reservations/passees', [ReservationEventController::class, 'mesReservationsPassees']);
    
    // Event participants
    Route::post('/events/{event}/participants/manual', [ReservationEventController::class, 'addManualParticipant']);
    Route::get('/events/{eventId}/participants/search', [ReservationEventController::class, 'search']);
    Route::put('/participants/{id}/update', [ReservationEventController::class, 'updateManualParticipant']);
    Route::get('/events/{event}/participants', [EventParticipantController::class, 'index']);
    
    // -------------------- GROUPS --------------------
    Route::post('/groupes/{groupeId}/follow', [FollowGroupeController::class, 'follow']);
    Route::delete('/groupes/{groupeId}/unfollow', [FollowGroupeController::class, 'unfollow']);
    Route::get('/me/followed-groupes', [FollowGroupeController::class, 'myFollowedGroupes']);
    Route::get('/groupes/{groupe}/feedbacks', [GroupController::class, 'listForGroup']);
    
    // -------------------- FEEDBACKS --------------------
    Route::post('/feedbacks/zone/{zoneId}', [FeedbackController::class, 'storeZone']);
    
    // -------------------- NOTIFICATIONS --------------------
    Route::post('/events/{event}/send-reminders', [NotificationController::class, 'sendRemindersForEvent']);
    
    // -------------------- CHAT --------------------
    // Private Chat
    Route::get('/chat/conversation/{receiver_id}/{event_id}', [PrivateChatController::class, 'conversation']);
    Route::get('/chat/unread-count/{event_id}', [PrivateChatController::class, 'unreadCount']);
    Route::post('/chat/send', [PrivateChatController::class, 'send']);
    Route::delete('/chat/message/{id}', [PrivateChatController::class, 'deleteMessage']);
    Route::post('/chat/archive', [PrivateChatController::class, 'archiveConversation']);
    Route::get('/chat/conversations', [PrivateChatController::class, 'listConversations']);
    

    
    // -------------------- CAMPING ZONES --------------------
    Route::prefix('zones')->group(function () {
        Route::post('/', [CampingZonesController::class, 'store']);
        Route::patch('/{id}', [CampingZonesController::class, 'update']);
        Route::delete('/{id}', [CampingZonesController::class, 'destroy']);
        Route::post('/{id}/gallery', [CampingZonesController::class, 'addGallery']);
        Route::post('/{id}/review', [CampingZonesController::class, 'markForReview']);
        Route::get('/recommend', [CampingZonesController::class, 'recommendZones']);
        Route::get('/{id}/stats', [CampingZonesController::class, 'zoneStats']);
        Route::get('/top', [CampingZonesController::class, 'topZones']);
        Route::get('/{userId}/recommendations', [CampingZonesController::class, 'personalizedRecommendations']);
        Route::get('/recommended/{userId}', [CampingZonesController::class, 'recommendedZones']);
        Route::get('/personalized/{userId}', [CampingZonesController::class, 'personalizedRecommendations']);
    });
    
    // -------------------- CAMPING CENTERS --------------------
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
    
    // -------------------- REPORTS --------------------
    Route::post('/zones/{zoneId}/signales', [SignalementZoneController::class, 'store']);
    
    // -------------------- FAVORITES --------------------
    Route::post('/zone/{id}/favoris', [FavorisController::class, 'toggleZone']);
    Route::get('/liste/favoris', [FavorisController::class, 'listFavoris']);
    
    // -------------------- PAYMENTS --------------------
    Route::get('/payment/confirm/{reservationId}', [PaymentController::class, 'confirmerPaiement']);
    Route::get('/reservation/{id}/imprimer', [PaymentController::class, 'imprimerTicket']);
    Route::get('/reservation/{id}/telecharger', [PaymentController::class, 'telechargerTicket']);
    Route::get('/konnect/success', [PaymentController::class, 'konnectCallback'])->name('konnect.success');
    Route::get('/konnect/fail', [PaymentController::class, 'konnectCallback'])->name('konnect.fail');
    Route::post('/konnect/webhook', [PaymentController::class, 'webhookKonnect']);
    
    // -------------------- RESERVATION CANCELLATION --------------------
    Route::post('/reservations/{id}/annuler', [ReservationCancellationController::class, 'annulerReservation']);
    
    // ==================== ROLE-SPECIFIC ROUTES ====================
    
    // -------------------- CAMPING CENTER RESERVATIONS --------------------
    Route::prefix('reservation')->group(function () {
        // Routes accessible to both campers and centers
        Route::middleware(['campeur_or_centre'])->group(function () {
            Route::get('/{id}', [ReservationsCentreController::class, 'show']);
            Route::get('/{id}/invoice', [ReservationsCentreController::class, 'downloadInvoice']);
            Route::get('/{id}/user-history', [ReservationsCentreController::class, 'getUserReservationHistory']);
            Route::patch('/centre/cancel/{id}', [ReservationsCentreController::class, 'destroy']);
            Route::patch('/centre/approve-modified/{id}', [ReservationsCentreController::class, 'approveModified']);

        });
        
        // Routes for campers only
        Route::middleware(['campeur'])->group(function () {
            Route::post('/centre', [ReservationsCentreController::class, 'store']);
            Route::put('/centre/{id}', [ReservationsCentreController::class, 'update']);
            Route::patch('/centre/destroy/{id}', [ReservationsCentreController::class, 'destroy']);
            Route::get('/centre/index_user', [ReservationsCentreController::class, 'index_user']);
            Route::get('/centre/user/statistics', [ReservationsCentreController::class, 'user_statistics']);
            
            // Equipment reservations
            Route::post('/materielle/store', [ReservationMaterielleController::class, 'store']);
            Route::patch('/materielle/destroy/{id}', [ReservationMaterielleController::class, 'destroy']);
            Route::get('/materielle/index_user', [ReservationMaterielleController::class, 'index_user']);
            
            // // Feedback
            // Route::post('/feedback/create', [FeedbackController::class, 'create']);
            // Route::post('/feedback/store', [FeedbackController::class, 'store']);
            // Route::post('/feedback/edit/{id}', [FeedbackController::class, 'edit']);
            // Route::delete('/feedback/destroy/{id}', [FeedbackController::class, 'destroy']);
            // Route::get('/feedback/index_user', [FeedbackController::class, 'index_user']);
        });
        
        // Routes for centers only
        Route::middleware(['centre'])->group(function () {
            Route::get('/centre/index', [ReservationsCentreController::class, 'index']);
            Route::patch('/centre/confirm/{id}', [ReservationsCentreController::class, 'confirm']);
            Route::patch('/centre/reject/{id}', [ReservationsCentreController::class, 'reject']);
            Route::patch('/centre/update_status', [ReservationsCentreController::class, 'update_status']);
            Route::put('/centre/{id}/update', [ReservationsCentreController::class, 'update']);
            Route::get('/centre/centre/statistics', [ReservationsCentreController::class, 'statistics']);
            Route::get('/centre/availability', [ReservationsCentreController::class, 'availability']);
            Route::patch('/centre/partial-accept/{id}', [ReservationsCentreController::class, 'partialAccept']);
        });
    });
    
    // -------------------- SUPPLIER ROUTES --------------------
    Route::middleware(['fournisseur'])->group(function () {
        // Shop
        Route::get('/boutique/create', [BoutiqueController::class, 'create']);
        Route::get('/boutique/edit', [BoutiqueController::class, 'edit']);
        Route::post('/boutique/add', [BoutiqueController::class, 'add']);
        Route::patch('/boutique/update', [BoutiqueController::class, 'update']);
        Route::delete('/boutique/destroy', [BoutiqueController::class, 'destroy']);
        
        // Equipment
        Route::post('/materielle/store', [MaterielleController::class, 'store']);
        Route::patch('/materielle/update/{id}', [MaterielleController::class, 'update']);
        Route::delete('/materielle/destroy/{id}', [MaterielleController::class, 'destroy']);
        Route::get('/materielle/create', [MaterielleController::class, 'create']);
        Route::get('/materielle/edit/{materielle_id}', [MaterielleController::class, 'edit']);
        Route::patch('/materielles/{id}/activate', [MaterielleController::class, 'activate']);
        Route::patch('/materielles/{id}/deactivate', [MaterielleController::class, 'deactivate']);
        
        // Equipment reservations
        Route::get('/reservation/materielle/index/{idMaterielle}', [ReservationMaterielleController::class, 'index']);
        Route::get('/reservation/materielle/show', [ReservationMaterielleController::class, 'show']);
        Route::get('/reservation/materielle/create', [ReservationMaterielleController::class, 'create']);
        Route::patch('/reservation/materielle/confirm/{id}', [ReservationMaterielleController::class, 'confirm']);
        Route::patch('/reservation/materielle/reject/{id}', [ReservationMaterielleController::class, 'reject']);
    });
    
    // -------------------- ADMIN ROUTES --------------------

// routes/api.php



// Routes protégées par authentification et rôle admin
// Routes protégées par authentification et rôle admin
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    
    // Dashboard
    
    // ==================== GESTION DES UTILISATEURS ====================
    Route::prefix('users')->group(function () {
        
        // Liste des utilisateurs avec filtres
        Route::get('/', [AdminUserController::class, 'index']);
        
        // Statistiques des utilisateurs
        Route::get('/stats/overview', [AdminUserController::class, 'stats']);
        
        // Récupérer tous les rôles
        Route::get('/roles/all', function () {
            return response()->json([
                'success' => true,
                'data' => \App\Models\Role::all()
            ]);
        });
        
        // Actions sur un utilisateur spécifique
        Route::prefix('{id}')->group(function () {
            
            // Informations de base
            Route::get('/', [AdminUserController::class, 'show']); // Profil complet
            Route::put('/', [AdminUserController::class, 'update']); // Mise à jour
            Route::delete('/', [AdminUserController::class, 'destroy']); // Suppression
            
            // Actions spéciales
            Route::put('/toggle-activation', [AdminUserController::class, 'toggleActivation']); // Activer/Désactiver
            Route::post('/reset-password', [AdminUserController::class, 'resetPassword']); // Réinitialiser mot de passe
            Route::post('/send-email', [AdminUserController::class, 'sendEmail']); // Envoyer email
            
            // ==================== GESTION DES DOCUMENTS ====================
            Route::prefix('documents')->group(function () {
                
                // Upload de document
                Route::post('/', [AdminUserController::class, 'uploadDocument']);
                
                // Types de documents spécifiques
                Route::get('/{documentType}/download', [AdminUserController::class, 'downloadDocument']); // Télécharger
                Route::get('/{documentType}/view', [AdminUserController::class, 'viewDocument']); // Visualiser
                Route::delete('/{documentType}', [AdminUserController::class, 'deleteDocument']); // Supprimer
            });
            
            // ==================== GESTION DES PHOTOS (ALBUMS) ====================
             Route::prefix('photos')->group(function () {
            Route::post('/', [AdminUserController::class, 'uploadPhotos']);
            Route::get('/', [AdminUserController::class, 'getPhotos']);
            Route::delete('/{photoId}', [AdminUserController::class, 'deletePhoto']);
        });
        });
    });
    
    // ==================== GESTION DES FEEDBACKS ====================
    Route::prefix('feedbacks')->group(function () {
        Route::post('/{id}/moderate', [AdminUserController::class, 'moderate']);
    });
    
    // ==================== STATISTIQUES GLOBALES ====================
    Route::get('/stats', [AdminUserController::class, 'stats']);
});

// Routes publiques (si nécessaire)
Route::get('/roles', function () {
    return response()->json([
        'success' => true,
        'data' => \App\Models\Role::all()
    ]);
});

// Routes publiques (si nécessaire)
Route::get('/roles', function () {
    return response()->json([
        'success' => true,
        'data' => \App\Models\Role::all()
    ]);
});

        
        // Event Management
        Route::prefix('events')->group(function () {
            Route::get('/', [AdminEventController::class, 'index']);
            Route::get('/{id}', [AdminEventController::class, 'show']);
            Route::post('/', [AdminEventController::class, 'store']);
            Route::put('/{id}', [AdminEventController::class, 'update']);
            Route::delete('/{id}', [AdminEventController::class, 'destroy']);
            Route::patch('/{id}/activate', [AdminEventController::class, 'activate']);
            Route::patch('/{id}/deactivate', [AdminEventController::class, 'deactivate']);
            Route::patch('/{id}/cancel', [AdminEventController::class, 'cancelEvent']);
            Route::get('/{id}/reservations', [AdminEventController::class, 'reservations']);
            Route::get('/{id}/statistics', [AdminEventController::class, 'statistics']);
            Route::get('/{id}/export-csv', [AdminEventController::class, 'exportReservationsCsv']);
        });
        
        // Reservation Management
        Route::prefix('reservations')->group(function () {
            Route::get('/', [AdminEventReservationController::class, 'listReservations']);
            Route::get('/export', [AdminEventReservationController::class, 'exportReservations']);
            Route::get('/{id}', [AdminEventReservationController::class, 'getReservationDetails']);
            Route::delete('/{id}', [AdminEventReservationController::class, 'deleteReservation']);
            Route::put('/{id}', [AdminEventReservationController::class, 'update']);
            Route::post('/events/{eventId}/participants/manual', [AdminEventReservationController::class, 'addManualParticipant']);
            Route::get('/stats', [AdminEventReservationController::class, 'stats']);
        });
        
        // Feedback Management
        // Route::prefix('feedbacks')->group(function () {
        //     Route::post('/', [FeedbackController::class, 'store']);         
        //     Route::put('/{id}', [FeedbackController::class, 'update']);
        //     Route::delete('/{id}', [FeedbackController::class, 'destroy']);
        // });
        
        // Announcement Management
        // Route::prefix('annonces')->group(function () {
        //     Route::get('/create', [AnnonceController::class, 'create']);
        //     Route::get('/edit/{id}', [AnnonceController::class, 'edit']);
        //     Route::delete('/destroy/{id}', [AnnonceController::class, 'destroy']);
        //     Route::patch('/deactivate/{id}', [AnnonceController::class, 'deactivate']);
        //     Route::patch('/activate/{id}', [AnnonceController::class, 'activate']);
        // });
        
        // Shop Management
        Route::prefix('boutique')->group(function () {
            Route::patch('/update', [BoutiqueController::class, 'update']);
            Route::delete('/destroy', [BoutiqueController::class, 'destroy']);
            Route::get('/create', [BoutiqueController::class, 'create']);
            Route::get('/edit', [BoutiqueController::class, 'edit']);
        });
        
        // Center Reservation Management
        Route::get('/reservation/centre/show/{id}', [ReservationsCentreController::class, 'show']);
        
        // Zone Reports
        Route::prefix('signals')->group(function () {
            Route::get('/zones/{zoneId}', [SignaleZoneController::class, 'index']);
            Route::put('/{id}/validate', [SignaleZoneController::class, 'validateSignalement']);
            Route::put('/{id}/reject', [SignaleZoneController::class, 'rejectSignalement']);
        });
        
        // Camping Centers Management
        Route::prefix('centres')->group(function () {
            Route::get('/', [CampingCentreController::class, 'index']);
            Route::post('/', [CampingCentreController::class, 'store']);
            Route::get('/stats', [CampingCentreController::class, 'stats']);
            Route::get('/registered', [CampingCentreController::class, 'registeredCentres']);
            Route::get('/nearby', [CampingCentreController::class, 'nearby']);
            Route::get('/suggest-zones', [CampingCentreController::class, 'suggestZones']);
            Route::get('/search', [CampingCentreController::class, 'search']);
            Route::get('/{id}', [CampingCentreController::class, 'show']);
            Route::put('/{id}', [CampingCentreController::class, 'update']);
            Route::post('/{id}/assign-zones', [CampingCentreController::class, 'assignZones']);
            Route::patch('/{id}/toggle-status', [CampingCentreController::class, 'toggleStatus']);
        });
        
        // Camping Zones Management
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
        

        
        // Service Categories Management
        Route::resource('service-categories', ServiceCategoryController::class);
    });
    // -------------------- PUBLISHER ROUTES --------------------
    Route::middleware(['can.publish'])->prefix('annonces')->group(function () {
        Route::post('/', [AnnonceController::class, 'store']);
        Route::match(['put', 'post'], '/{id}', [AnnonceController::class, 'update']);
        Route::delete('/{id}', [AnnonceController::class, 'destroy']); 
        
        // Additional publisher routes
        Route::get('/archived/{userId}', [AnnonceController::class, 'getArchived']);
        Route::patch('/{id}/archive', [AnnonceController::class, 'archive']);
        Route::patch('/{id}/unarchive', [AnnonceController::class, 'unarchive']);
        Route::post('/{id}/like', [AnnonceController::class, 'like']);
        Route::post('/{id}/unlike', [AnnonceController::class, 'unlike']);
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
    
    // Group participants & advanced statistics
    Route::prefix('group/events')->group(function () {
        Route::get('/{event_id}/participants', [ReservationEventController::class, 'participants']);
        Route::get('/{event_id}/stats', [ReservationEventController::class, 'participantStats']);
        Route::get('/{event_id}/search', [ReservationEventController::class, 'search']);
    });

// ==================== PUBLIC USER VERIFICATION ROUTE ====================
// This route should be outside auth middleware since it's for public access
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

// ==================== WEB ADMIN ROUTES (for web interface) ====================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('service-categories', ServiceCategoryController::class);
});

// ==================== SEASON AND RECOMMENDATION ROUTES ====================
// These routes are commented out in your original code, keeping them for reference
// Route::get('/season/current', [RecommendationController::class, 'getCurrentSeason']);
// Route::get('/user/{id}/preferences', [RecommendationController::class, 'getUserPreferences']);
// Route::get('/user/{id}/region', [RecommendationController::class, 'getUserRegion']);