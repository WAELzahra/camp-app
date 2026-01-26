 <?php

use App\Services\ZoneSearchService;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\Annonce\AnnonceController;
use App\Http\Controllers\Reservation\ReservationsCentreController;
use App\Http\Controllers\Boutique\BoutiqueController;
use App\Http\Controllers\Materielle\MaterielleController;
use App\Http\Controllers\Reservation\ReservationMaterielleController;
use App\Http\Controllers\Feedback\FeedbackController;
use App\Http\Controllers\Event\EventController;
use App\Http\Controllers\Event\EventInterestController;
use App\Http\Controllers\Event\EventParticipantController;
use App\Http\Controllers\Reservation\ReservationEventController;
use App\Http\Controllers\Reservation\ReservationCancellationController;
use App\Http\Controllers\Payment\PaymentController;
use App\Http\Controllers\Groupe\GroupController;
use App\Http\Controllers\Groupe\FollowGroupeController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminEventController;
use App\Http\Controllers\Admin\AdminEventReservationController;
use App\Http\Controllers\Admin\AdminFeedbackController;

use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\message_chat\PrivateChatController;
use App\Http\Controllers\GroupChat\ChatGroupController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\zonecamping\CampingZonesController;
use App\Http\Controllers\zonecamping\ZonePolygonController;
use App\Http\Controllers\Signal\SignalementZoneController;
use App\Http\Controllers\Admin\SignaleZoneController;
use App\Http\Controllers\Favoris\FavorisController;
use App\Http\Controllers\Admin\CampingCentreController;
use App\Http\Controllers\zonecamping\CampingCentresController;
use App\Http\Controllers\Admin\CampingZoneController;
use App\Http\Controllers\zonecamping\PublicCampingController;
use App\Http\Controllers\Api\CenterServiceApiController;
use App\Http\Controllers\Admin\ServiceCategoryController;

// routes/web.php (for admin)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('service-categories', \App\Http\Controllers\Admin\ServiceCategoryController::class);
});
// routes/api.php
Route::prefix('v1')->group(function () {
    Route::get('centers/services', [\App\Http\Controllers\Api\CenterServiceApiController::class, 'centersWithServices']);
    Route::get('centers/{center}/services', [\App\Http\Controllers\Api\CenterServiceApiController::class, 'centerServices']);
    Route::get('service-categories', [\App\Http\Controllers\Api\CenterServiceApiController::class, 'serviceCategories']);
    Route::post('calculate-price', [\App\Http\Controllers\Api\CenterServiceApiController::class, 'calculatePrice']);
});

// Route::get('/auth/google/url', [SocialAuthController::class, 'getSocialAuthUrls']);
// Route::get('/auth/google', [SocialAuthController::class, 'redirectToGoogle'])->name('google.redirect');
// Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback']);
// Route::get('/auth/facebook', [SocialAuthController::class, 'redirectToFacebook'])->name('facebook.redirect');
// Route::get('/auth/facebook/callback', [SocialAuthController::class, 'handleFacebookCallback']);

Route::post('/verify-by-token', [VerifyEmailController::class, 'verifyByToken']);
Route::post('/verify-by-code', [VerifyEmailController::class, 'verifyByCode']);
Route::post('/resend-verification', [VerifyEmailController::class, 'resendVerification']);
Route::get('/verification-status/{id}', [VerifyEmailController::class, 'verificationStatus']);

// ==================== ROUTES PUBLIQUES (ACCESSIBLES SANS AUTHENTIFICATION) ====================

// Authentification
Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
Route::post('/reset-password', [NewPasswordController::class, 'store']);

// Annonces
Route::get('/annonces/index/{id}', [AnnonceController::class, 'index']);
Route::get('/annonces/show/{id}', [AnnonceController::class, 'show']);

// Boutique
Route::get('/boutique', [BoutiqueController::class, 'index']);
Route::get('/boutique/show/{id}', [BoutiqueController::class, 'show']);

// Feedback
Route::get('/feedback/index', [FeedbackController::class, 'index']);
Route::get('/feedback/show/{id}', [FeedbackController::class, 'show']);

// Matériel
Route::get('/materielle/compare/{id1}-{id2}', [MaterielleController::class, 'compare']);
Route::get('/materielle/index/{fournisseur_id}', [MaterielleController::class, 'index']);
Route::get('/materielle/show/{materielle_id}', [MaterielleController::class, 'show']);

// Événements
Route::get('/events/search', [EventController::class, 'search']);
Route::get('/events/{id}/participants/stats', [ReservationEventController::class, 'participantStats']);
Route::get('/events/{id}/participants', [ReservationEventController::class, 'participants']);
Route::get('/events/{id}/share-links', [EventController::class, 'getEventShareLinks']);
Route::get('/events/{id}/copy-link', [EventController::class, 'getEventCopyLink']);
Route::get('/events/{id}', [EventController::class, 'getEventDetails']);
Route::get('/events', [EventController::class, 'index']);

// Groupes de camping
Route::get('/groupes/search', [GroupController::class, 'searchGroups']);
Route::get('/groupes', [GroupController::class, 'listGroupsWithFeedbacks']);

// Zones de camping (accès public)
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

// Centres de camping (accès public)
    Route::prefix('centres')->group(function () {

    // Carte des centres
    Route::get('/map', [CampingCentresController::class, 'getCentresMap']);

    // Recherche de centres
    Route::get('/search', [CampingCentresController::class, 'searchCentres']);



    // Liste des zones d’un centre
    Route::get('/{id}/zones', [CampingCentresController::class, 'listZones'])
        ->whereNumber('id');

    // Détail d’un centre (doit être après toutes les routes spécifiques)
    Route::get('/{id}', [CampingCentresController::class, 'showCentre'])
        ->whereNumber('id');

});

Route::prefix('public-zones')->group(function () {
    Route::get('/with-centres', [PublicCampingController::class, 'zonesWithCentres']);
    Route::get('/filters', [PublicCampingController::class, 'zonesWithFilters']);
    Route::get('/nearby', [PublicCampingController::class, 'nearbyZones']);
});



// Routes publiques générales
Route::prefix('public')->group(function () {
    Route::get('/zones-centres', [PublicCampingController::class, 'zonesWithCentres']);
    Route::get('/centre/{id}', [PublicCampingController::class, 'showCentre']);
    Route::get('/zones-filters', [PublicCampingController::class, 'zonesWithFilters']);
    Route::get('/zones-nearby', [PublicCampingController::class, 'nearbyZones']);
});

// Callback Flouci
Route::get('/flouci/callback', [PaymentController::class, 'callback']);

// ==================== ROUTES AUTHENTIFIÉES (SANCTUM) ====================

Route::middleware('auth:sanctum')->group(function () {
    
    // -------------------- PROFIL UTILISATEUR --------------------
    Route::get('/user', fn(Request $request) => $request->user());
    Route::get('/email/verify-prompt', EmailVerificationPromptController::class);
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store']);
    Route::put('/user/password', [PasswordController::class, 'update']);
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    
    Route::get('/user/verification-status', function (Request $request) {
        return response()->json([
            'verified' => $request->user()->hasVerifiedEmail(),
            'email' => $request->user()->email,
        ]);
    });
    // Also add this route for unauthenticated users (if they have the user ID)
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

    // Vérification email
    Route::middleware('signed')->get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        return response()->json(['message' => 'Email verified successfully']);
    })->name('verification.verify');
    
    // Profil
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::post('/profile/photos', [ProfileController::class, 'storeOrUpdateProfilePhotos']);
    
    // -------------------- RÉSERVATIONS PASSÉES --------------------
    Route::get('/reservations/passees', [ReservationEventController::class, 'mesReservationsPassees']);
    
    // -------------------- ÉVÉNEMENTS --------------------
    Route::post('/events', [EventController::class, 'store']);
    Route::put('/events/{id}', [EventController::class, 'update']);
    Route::delete('/events/{id}', [EventController::class, 'destroy']);
    Route::patch('/events/{event}/status', [EventController::class, 'updateStatus']);
    Route::post('/events/{id}/interest', [EventInterestController::class, 'toggleInterest']);
    Route::get('/events/nearby/{userId}', [EventController::class, 'notifyNearbyEvents']);
    
    // -------------------- RÉSERVATIONS ÉVÉNEMENTS --------------------
    Route::post('/reservation-event', [ReservationEventController::class, 'createReservationWithPayment']);
    Route::put('/reservation-event/{id}/simulate-payment', [ReservationEventController::class, 'simulatePayment']);
    Route::post('/event/{eventId}/payer', [PaymentController::class, 'initPayment']);
    Route::post('/reservations/{id}/annuler', [ReservationCancellationController::class, 'annulerReservation']);
    Route::patch('/reservations/{id}/status', [ReservationEventController::class, 'updateStatus']);
    Route::delete('/reservations/{id}', [ReservationEventController::class, 'destroy']);
    Route::get('/reservation-event/{id}', [ReservationEventController::class, 'show']);
    Route::put('/reservations/{id}', [ReservationEventController::class, 'updateReservation']);
    
    // Participants événements
    Route::post('/events/{event}/participants/manual', [ReservationEventController::class, 'addManualParticipant']);
    Route::get('/events/{eventId}/participants/search', [ReservationEventController::class, 'search']);
    Route::put('/participants/{id}/update', [ReservationEventController::class, 'updateManualParticipant']);
    Route::get('/events/{event}/participants', [EventParticipantController::class, 'index']);
    
    // -------------------- GROUPES --------------------
    Route::post('/groupes/{groupeId}/follow', [FollowGroupeController::class, 'follow']);
    Route::delete('/groupes/{groupeId}/unfollow', [FollowGroupeController::class, 'unfollow']);
    Route::get('/me/followed-groupes', [FollowGroupeController::class, 'myFollowedGroupes']);
    Route::get('/groupes/{groupe}/feedbacks', [GroupController::class, 'listForGroup']);
    
    // -------------------- FEEDBACKS --------------------
    Route::post('/feedback/{type}/{targetId}', [FeedbackController::class, 'storeOrUpdateFeedback'])
    ->middleware('auth'); 
    Route::get('/feedback/{type}/{id}', [FeedbackController::class, 'getFeedbacks']);
    Route::post('/zones/{id}/feedbacks', [FeedbackController::class, 'storeZone']);
    Route::post('/guides/{id}/report', [FeedbackController::class, 'reportGuide']);
    Route::get('/zone-feedbacks/{zoneId}', [FeedbackController::class, 'listZone']);
    
    // -------------------- NOTIFICATIONS --------------------
    Route::post('/events/{event}/send-reminders', [NotificationController::class, 'sendRemindersForEvent']);
    
    // -------------------- CHAT PRIVÉ --------------------
    Route::get('/chat/conversation/{receiver_id}/{event_id}', [PrivateChatController::class, 'conversation']);
    Route::get('/chat/unread-count/{event_id}', [PrivateChatController::class, 'unreadCount']);
    Route::post('/chat/send', [PrivateChatController::class, 'send']);
    Route::delete('/chat/message/{id}', [PrivateChatController::class, 'deleteMessage']);
    Route::post('/chat/archive', [PrivateChatController::class, 'archiveConversation']);
    Route::get('/chat/conversations', [PrivateChatController::class, 'listConversations']);
    
    // -------------------- CHAT DE GROUPE --------------------
    Route::get('/group-chat/my-groups', [ChatGroupController::class, 'myGroups']);
    Route::get('/group-chat/join/{token}', [ChatGroupController::class, 'joinByToken']);
    Route::prefix('group-chat')->group(function () {
        Route::post('/{chat_group_id}/message', [ChatGroupController::class, 'sendMessage']);
        Route::get('/{chat_group_id}/messages', [ChatGroupController::class, 'getMessages']);
        Route::get('/{chat_group_id}/members', [ChatGroupController::class, 'getMembers']);
        Route::delete('/{chat_group_id}/leave', [ChatGroupController::class, 'leaveGroup']);
        Route::post('/{chat_group_id}/typing', [ChatGroupController::class, 'typingStatus']);
        Route::get('/{chat_group_id}/typing', [ChatGroupController::class, 'typingUsers']);
    });
    
    // -------------------- ZONES DE CAMPING --------------------
    Route::prefix('zones')->group(function () {
        Route::post('/', [CampingZonesController::class, 'store']);
        Route::patch('/{id}', [CampingZonesController::class, 'update']);
        Route::delete('/{id}', [CampingZonesController::class, 'destroy']);
        Route::post('/{id}/gallery', [CampingZonesController::class, 'addGallery']);
        Route::post('/{id}/review', [CampingZonesController::class, 'markForReview']);
        Route::post('/suggest', [CampingZonesController::class, 'suggestZone']);
        Route::get('/recommend', [CampingZoneController::class, 'recommendZones']);
        Route::get('/{id}/stats', [CampingZoneController::class, 'zoneStats']);
        Route::get('/top', [CampingZoneController::class, 'topZones']);
        Route::get('/{zoneId}/stats', [CampingZoneController::class, 'zoneStats']);
        Route::get('/{userId}/recommendations', [CampingZoneController::class, 'personalizedRecommendations']);
        Route::get('/recommended/{userId}', [CampingZonesController::class, 'recommendedZones']);
        Route::get('/personalized/{userId}', [CampingZonesController::class, 'personalizedRecommendations']);
    });
    
    // -------------------- CENTRES DE CAMPING --------------------
    Route::prefix('centres')->group(function () {
        Route::post('/suggest', [CampingCentresController::class, 'suggestCentre']);
        Route::get('/favoris', [CampingCentresController::class, 'listFavoris']);
        Route::post('/{id}/favoris', [CampingCentresController::class, 'toggleFavoris']);
        Route::get('/{centreId}/stats', [CampingCentresController::class, 'centreStats']);
    });
    
    // -------------------- POLYGONES DE ZONES --------------------
    Route::prefix('zone-polygons')->group(function () {
        Route::post('/', [ZonePolygonController::class, 'store']);
        Route::put('/{id}', [ZonePolygonController::class, 'update']);
        Route::delete('/{id}', [ZonePolygonController::class, 'destroy']);
        Route::get('/zone/{zoneId}', [ZonePolygonController::class, 'listByZone']);
    });
    
    // -------------------- SIGNALEMENTS --------------------
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/zones/{zoneId}/signales', [SignalementZoneController::class, 'store']);
    });
    // -------------------- FAVORIS --------------------
    Route::post('/zone/{id}/favoris', [FavorisController::class, 'toggleZone']);
    Route::get('/liste/favoris', [FavorisController::class, 'listFavoris']);
    
    // -------------------- PAIEMENTS --------------------
    Route::get('/payment/confirm/{reservationId}', [PaymentController::class, 'confirmerPaiement']);
    Route::get('/reservation/{id}/imprimer', [PaymentController::class, 'imprimerTicket']);
    Route::get('/reservation/{id}/telecharger', [PaymentController::class, 'telechargerTicket']);
    Route::get('/konnect/success', [PaymentController::class, 'konnectCallback'])->name('konnect.success');
    Route::get('/konnect/fail', [PaymentController::class, 'konnectCallback'])->name('konnect.fail');
    Route::post('/konnect/webhook', [PaymentController::class, 'webhookKonnect']);
    
    // ==================== RÔLES SPÉCIFIQUES ====================
    
    // -------------------- ADMINISTRATEUR --------------------
    Route::middleware(['admin'])->group(function () {
        // Gestion utilisateurs
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{id}', [AdminUserController::class, 'show']);
        Route::put('/users/{id}', [AdminUserController::class, 'update']);
        Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
        Route::put('/users/{id}/toggle-activation', [AdminUserController::class, 'toggleActivation']);
        Route::post('/feedbacks/{id}/moderate', [AdminUserController::class, 'moderate']);
        
        // Gestion événements
        Route::prefix('admin')->group(function () {
            Route::get('events', [AdminEventController::class, 'index']);
            Route::get('events/{id}', [AdminEventController::class, 'show']);
            Route::post('events', [AdminEventController::class, 'store']);
            Route::put('events/{id}', [AdminEventController::class, 'update']);
            Route::delete('events/{id}', [AdminEventController::class, 'destroy']);
            Route::patch('events/{id}/activate', [AdminEventController::class, 'activate']);
            Route::patch('events/{id}/deactivate', [AdminEventController::class, 'deactivate']);
            Route::get('events/{id}/reservations', [AdminEventController::class, 'reservations']);
            Route::get('events/{id}/statistics', [AdminEventController::class, 'statistics']);
            Route::patch('events/{id}/cancel', [AdminEventController::class, 'cancelEvent']);
            Route::get('events/{id}/export-csv', [AdminEventController::class, 'exportReservationsCsv']);
            
            // Réservations
            Route::get('/reservations/export', [AdminEventReservationController::class, 'exportReservations']);
            Route::get('/reservations', [AdminEventReservationController::class, 'listReservations']);
            Route::get('/reservations/{id}', [AdminEventReservationController::class, 'getReservationDetails']);
            Route::delete('/reservations/{id}', [AdminEventReservationController::class, 'deleteReservation']);
            Route::put('/reservations/{id}', [AdminEventReservationController::class, 'update']);
            Route::post('/events/{eventId}/participants/manual', [AdminEventReservationController::class, 'addManualParticipant']);
            Route::get('/stats', [AdminEventReservationController::class, 'stats']);
        });
        
        // Gestion annonces
        Route::get('/annonces/create', [AnnonceController::class, 'create']);
        Route::get('/annonces/edit/{id}', [AnnonceController::class, 'edit']);
        Route::delete('/annonces/destroy/{id}', [AnnonceController::class, 'destroy']);
        Route::patch('/annonces/deactivate/{id}', [AnnonceController::class, 'deactivate']);
        Route::patch('/annonces/activate/{id}', [AnnonceController::class, 'activate']);
        
        // Gestion réservations centre
        Route::get('/reservation/centre/{id}', [ReservationsCentreController::class, 'index']);
        Route::get('/reservation/centre/show{id}', [ReservationsCentreController::class, 'show']);
        Route::get('/reservation/centre/create', [ReservationsCentreController::class, 'create']);
        Route::patch('/reservation/centre/destroy', [ReservationsCentreController::class, 'destroy']);
        
        // Gestion boutique
        Route::patch('/boutique/update', [BoutiqueController::class, 'update']);
        Route::delete('/boutique/destroy', [BoutiqueController::class, 'destroy']);
        Route::get('/boutique/create', [BoutiqueController::class, 'create']);
        Route::get('/boutique/edit', [BoutiqueController::class, 'edit']);
        
        // Gestion feedback
        Route::prefix('admin')->middleware(['auth:api','role:admin'])->group(function () {
            Route::get('feedbacks', [AdminFeedbackController::class, 'index']); // liste avec filtre/recherche
            Route::get('feedbacks/{id}', [AdminFeedbackController::class, 'show']); // détails
            Route::post('feedbacks/{id}/approve', [AdminFeedbackController::class, 'approve']); // valider
            Route::post('feedbacks/{id}/reject', [AdminFeedbackController::class, 'reject']);   // rejeter
        });

            // Signalements
        Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function() {
           
        });

        Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function() {
        Route::put('/signals/{id}/reject', [SignaleZoneController::class, 'rejectSignalement']);
        Route::get('/zones/{zoneId}/signals', [SignaleZoneController::class, 'index']);
        Route::put('/signals/{id}/validate', [SignaleZoneController::class, 'validateSignalement']);
});

        
        // Centres de camping (admin)
        Route::prefix('admin/centres')->group(function () {
        Route::get('/stats', [CampingCentreController::class, 'stats']); // 👈 mettre avant /{id}
        Route::get('/registered', [CampingCentreController::class, 'registeredCentres']);
        Route::get('/nearby', [CampingCentreController::class, 'nearby']);
        Route::get('/suggest-zones', [CampingCentreController::class, 'suggestZones']);
        Route::get('/search', [CampingCentreController::class, 'search']);

        Route::get('/', [CampingCentreController::class, 'index']);
        Route::post('/', [CampingCentreController::class, 'store']);
        Route::get('/{id}', [CampingCentreController::class, 'show']); // 👈 garder en dernier
        Route::put('/{id}', [CampingCentreController::class, 'update']);
        Route::post('/{id}/assign-zones', [CampingCentreController::class, 'assignZones']);
        Route::patch('/{id}/toggle-status', [CampingCentreController::class, 'toggleStatus']);
    });

        
        // Zones de camping (admin)
        Route::prefix('admin/zone')->group(function () {
            Route::post('/', [CampingZoneController::class, 'store']);
            Route::put('/{id}', [CampingZoneController::class, 'update']);
            Route::delete('/{id}', [CampingZoneController::class, 'destroy']);
            Route::patch('/{id}/validate', [CampingZoneController::class, 'validateZone']);
            Route::patch('/{id}/toggle-status', [CampingZoneController::class, 'toggleZoneStatus']);
            Route::post('/merge', [CampingZoneController::class, 'merge']);
            Route::get('/stats', [CampingZoneController::class, 'stats']);
            Route::post('/bulk-assign', [CampingZoneController::class, 'bulkAssignToCentre']);
            Route::post('/zones/{id}/adjust-polygon', [CampingZoneController::class, 'adjustPolygonWithRoutes']);
            Route::post('/zones/import-geojson', [CampingZoneController::class, 'importGeoJson']);
        });
        
        // Chat de groupe (admin)
        Route::post('/group-chat/create', [ChatGroupController::class, 'store']);
        Route::delete('/group-chat/{id}', [ChatGroupController::class, 'destroy']);
        Route::put('/group-chat/{chat_group_id}/rename', [ChatGroupController::class, 'renameGroup']);
        Route::post('/group-chat/{chat_group_id}/archive', [ChatGroupController::class, 'archive']);
        Route::delete('/group-chat/{chat_group_id}/members/{user_id}', [ChatGroupController::class, 'removeMember']);
    });
    
    // -------------------- PUBLISHER (PUBLICATEUR) --------------------
    Route::middleware(['can.publish'])->group(function () {
        Route::get('/annonces/create', [AnnonceController::class, 'create']);
        Route::get('/annonces/edit/{id}', [AnnonceController::class, 'edit']);
        Route::post('/annonces', [AnnonceController::class, 'store']);
        Route::patch('/annonces/update/{id}', [AnnonceController::class, 'update']);
        Route::delete('/annonces/destroy/{id}', [AnnonceController::class, 'destroy']);
    });
    
    // -------------------- CAMPEUR --------------------
    Route::middleware(['campeur'])->group(function () {
        // Réservation centre
        Route::get('/reservation/centre/{id}', [ReservationsCentreController::class, 'index']);
        Route::get('/reservation/centre/create', [ReservationsCentreController::class, 'create']);
        Route::post('/reservation/centre', [ReservationsCentreController::class, 'store']);
        Route::patch('/reservation/centre/destroy/{id}', [ReservationsCentreController::class, 'destroy']);
        Route::get('/reservation/centre/show/{id}', [ReservationsCentreController::class, 'show']);
        Route::get('/reservation/centre/index_user', [ReservationsCentreController::class, 'index_user']);
        
        // Réservation matériel
        Route::post('/reservation/materielle/store', [ReservationMaterielleController::class, 'store']);
        Route::patch('/reservation/materielle/destroy/{id}', [ReservationMaterielleController::class, 'destroy']);
        Route::get('/reservation/materielle/index_user', [ReservationMaterielleController::class, 'index_user']);
        
        // Feedback
        Route::post('/feedback/create', [FeedbackController::class, 'create']);
        Route::post('/feedback/store', [FeedbackController::class, 'store']);
        Route::post('/feedback/edit/{id}', [FeedbackController::class, 'edit']);
        Route::patch('/feedback/update/{id}', [FeedbackController::class, 'update']);
        Route::delete('/feedback/destroy/{id}', [FeedbackController::class, 'destroy']);
        Route::get('/feedback/index_user', [FeedbackController::class, 'index_user']);
    });
    
    // -------------------- CENTRE --------------------
    Route::middleware(['centre'])->group(function () {
        Route::patch('/reservation/centre/confirm/{id}', [ReservationsCentreController::class, 'confirm']);
        Route::patch('/reservation/centre/reject/{id}', [ReservationsCentreController::class, 'reject']);
        Route::get('/reservation/centre/index', [ReservationsCentreController::class, 'index']);
        Route::patch('/reservation/centre/update_status', [ReservationsCentreController::class, 'update_status']);
    });
    
    // -------------------- FOURNISSEUR --------------------
    Route::middleware(['fournisseur'])->group(function () {
        // Boutique
        Route::get('/boutique/create', [BoutiqueController::class, 'create']);
        Route::get('/boutique/edit', [BoutiqueController::class, 'edit']);
        Route::post('/boutique/add', [BoutiqueController::class, 'add']);
        Route::patch('/boutique/update', [BoutiqueController::class, 'update']);
        Route::delete('/boutique/destroy', [BoutiqueController::class, 'destroy']);
        
        // Matériel
        Route::post('/materielle/store', [MaterielleController::class, 'store']);
        Route::patch('/materielle/update/{id}', [MaterielleController::class, 'update']);
        Route::delete('/materielle/destroy/{id}', [MaterielleController::class, 'destroy']);
        Route::get('/materielle/create', [MaterielleController::class, 'create']);
        Route::get('/materielle/edit/{materielle_id}', [MaterielleController::class, 'edit']);
        Route::patch('/materielles/{id}/activate', [MaterielleController::class, 'activate']);
        Route::patch('/materielles/{id}/deactivate', [MaterielleController::class, 'deactivate']);
        
        // Réservation matériel
        Route::get('/reservation/materielle/index/{idMaterielle}', [ReservationMaterielleController::class, 'index']);
        Route::get('/reservation/materielle/show', [ReservationMaterielleController::class, 'show']);
        Route::get('/reservation/materielle/create', [ReservationMaterielleController::class, 'create']);
        Route::patch('/reservation/materielle/confirm/{id}', [ReservationMaterielleController::class, 'confirm']);
        Route::patch('/reservation/materielle/reject/{id}', [ReservationMaterielleController::class, 'reject']);
    });
    
    // -------------------- GESTION PAR LES GROUPES --------------------
    Route::prefix('group/reservations')->group(function () {
        Route::get('/export', [ReservationEventController::class, 'exportReservations']);
        Route::get('/stats', [ReservationEventController::class, 'reservationStats']);
        Route::get('/event/{event_id}', [ReservationEventController::class, 'reservationsParEvenement']);
        Route::get('/', [ReservationEventController::class, 'toutesMesReservations']);
        Route::get('/{reservation_id}', [ReservationEventController::class, 'show']);
        Route::patch('/{reservation_id}/status', [ReservationEventController::class, 'updateStatus']);
        Route::post('/{reservation_id}/cancel', [ReservationEventController::class, 'cancelReservation']);
        Route::patch('/{reservation_id}/update-places', [ReservationEventController::class, 'updatePlaces']);
        Route::put('/{reservation_id}/update-participant', [ReservationEventController::class, 'updateManualParticipant']);
        Route::delete('/{reservation_id}', [ReservationEventController::class, 'destroy']);
        Route::post('/create', [ReservationEventController::class, 'createReservationWithPayment']);
        Route::post('/{reservation_id}/simulate-payment', [ReservationEventController::class, 'simulatePayment']);
    });
    
    // Participants & statistiques avancées
    Route::get('/group/events/{event_id}/participants', [ReservationEventController::class, 'participants']);
    Route::get('/group/events/{event_id}/stats', [ReservationEventController::class, 'participantStats']);
    Route::get('/group/events/{event_id}/search', [ReservationEventController::class, 'search']);
});

// ==================== ROUTES DE SAISON ET RECOMMANDATIONS ====================

// Saison actuelle
Route::get('/season/current', [RecommendationController::class, 'getCurrentSeason']);

// Préférences utilisateur
Route::get('/user/{id}/preferences', [RecommendationController::class, 'getUserPreferences']);

// Région utilisateur
Route::get('/user/{id}/region', [RecommendationController::class, 'getUserRegion']);