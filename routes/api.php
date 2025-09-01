<?php


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

use App\Http\Controllers\Notification\NotificationController;


use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\PasswordController;

use App\Http\Controllers\ProfileController;

use App\Http\Controllers\message_chat\PrivateChatController;
use App\Http\Controllers\GroupChat\ChatGroupController;
use App\Http\Controllers\Auth\SocialAuthController;



// routes for admin 
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // for annonce
    Route::get('/annonces/create', [AnnonceController::class, 'create']);
    Route::get('/annonces/edit/{id}', [AnnonceController::class, 'edit']);
    Route::delete('/annonces/destroy/{id}', [AnnonceController::class, 'destroy']);
    Route::patch('/annonces/deactivate/{id}', [AnnonceController::class, 'deactivate']);
    Route::patch('/annonces/activate/{id}', [AnnonceController::class, 'activate']);

    // for center reservations
    Route::get('/reservation/centre/{id}', [ReservationsCentreController::class, 'index']);
    Route::get('/reservation/centre/show{id}', [ReservationsCentreController::class, 'show']);
    Route::get('/reservation/centre/create', [ReservationsCentreController::class, 'create']);
    Route::patch('/reservation/centre/destroy', [ReservationsCentreController::class, 'destroy']);
    // for boutique
    Route::patch('/boutique/update', [BoutiqueController::class, 'update']);
    Route::delete('/boutique/destroy', [BoutiqueController::class, 'destroy']);
    Route::get('/boutique/create', [BoutiqueController::class, 'create']);
    Route::get('/boutique/edit', [BoutiqueController::class, 'edit']);
    Route::patch('/boutique/activate/{id}', [BoutiqueController::class, 'activate']);
    Route::patch('/boutique/deactivate/{id}', [BoutiqueController::class, 'deactivate']);

    // for feedback
    Route::get('/feedback/index_user', [FeedbackController::class, 'index_user']);
    Route::delete('/feedback/destroy/{id}', [FeedbackController::class, 'destroy']);
    Route::get('/feedback/show/{id}', [FeedbackController::class, 'show']);
    Route::delete('/feedback/destroy/{id}', [FeedbackController::class, 'destroy']);
    Route::post('/feedback/create', [FeedbackController::class, 'create']);
    Route::post('/feedback/store', [FeedbackController::class, 'store']);
    Route::get('/feedback/edit/{id}', [FeedbackController::class, 'edit']);
    Route::delete('/feedback/adminDestroy/{id}', [FeedbackController::class, 'adminDestroy']);
    //for materielle
    Route::patch('/materielle/activate/{id}', [MaterielleController::class, 'activate']);
    Route::patch('/materielle/deactivate/{id}', [MaterielleController::class, 'deactivate']);


});

// REST Annonce
Route::middleware(['auth:sanctum', 'can.publish'])->group(function () {
    Route::get('/annonces/create', [AnnonceController::class, 'create']);
    Route::get('/annonces/edit/{id}', [AnnonceController::class, 'edit']);
    Route::post('/annonces', [AnnonceController::class, 'store']);
    Route::patch('/annonces/update/{id}', [AnnonceController::class, 'update']);
    Route::delete('/annonces/destroy/{id}', [AnnonceController::class, 'destroy']);
});

// REST Reservation centre
Route::middleware(['auth:sanctum', 'campeur'])->group(function () {
    Route::get('/reservation/centre/{id}', [ReservationsCentreController::class, 'index']);
    Route::get('/reservation/centre/create', [ReservationsCentreController::class, 'create']);
    Route::post('/reservation/centre', [ReservationsCentreController::class, 'store']);
    Route::patch('/reservation/centre/destroy/{id}', [ReservationsCentreController::class, 'destroy']);
    Route::get('/reservation/centre/show/{id}', [ReservationsCentreController::class, 'show']);
    Route::get('/reservation/centre/index_user', [ReservationsCentreController::class, 'index_user']);
    //Camper reserve materielle
    Route::post('/reservation/materielle/store', [ReservationMaterielleController::class, 'store']);
    Route::patch('/reservation/materielle/destroy/{id}', [ReservationMaterielleController::class, 'destroy']);
    Route::get('/reservation/materielle/index_user', [ReservationMaterielleController::class, 'index_user']);
    // noter service
    Route::post('/feedback/create', [FeedbackController::class, 'create']);
    Route::post('/feedback/store', [FeedbackController::class, 'store']);
    Route::post('/feedback/edit/{id}', [FeedbackController::class, 'edit']);
    Route::patch('/feedback/update/{id}', [FeedbackController::class, 'update']);
    Route::delete('/feedback/destroy/{id}', [FeedbackController::class, 'destroy']);
    Route::get('/feedback/index_user', [FeedbackController::class, 'index_user']);

});
//centre confirm/refuse reservation
Route::middleware(['auth:sanctum', 'centre'])->group(function () {
    Route::patch('/reservation/centre/confirm/{id}', [ReservationsCentreController::class, 'confirm']);
    Route::patch('/reservation/centre/reject/{id}', [ReservationsCentreController::class, 'reject']);
    Route::get('/reservation/centre/index', [ReservationsCentreController::class, 'index']);
    Route::patch('/reservation/centre/update_status', [ReservationsCentreController::class, 'update_status']);

});
//REST boutique, reservation materielle
Route::middleware(['auth:sanctum', 'fournisseur'])->group(function () {
    // RESET for boutique
    Route::get('/boutique/create', [BoutiqueController::class, 'create']);
    Route::get('/boutique/edit', [BoutiqueController::class, 'edit']);
    Route::post('/boutique/add', [BoutiqueController::class, 'add']);
    Route::patch('/boutique/update', [BoutiqueController::class, 'update']);
    Route::delete('/boutique/destroy', [BoutiqueController::class, 'destroy']);
    // REST for materielle 
    Route::post('/materielle/store', [MaterielleController::class, 'store']);
    Route::patch('/materielle/update/{id}', [MaterielleController::class, 'update']);
    Route::delete('/materielle/destroy/{id}', [MaterielleController::class, 'destroy']);
    Route::get('/materielle/create', [MaterielleController::class, 'create']);
    Route::get('/materielle/edit/{materielle_id}', [MaterielleController::class, 'edit']);
    Route::patch('/materielle/deactivate/{id}', [MaterielleController::class, 'deactivate']);

    // REST for reservation materielle
    Route::get('/reservation/materielle/index/{idMaterielle}', [ReservationMaterielleController::class, 'index']);
    Route::get('/reservation/materielle/show', [ReservationMaterielleController::class, 'show']);
    Route::get('/reservation/materielle/create', [ReservationMaterielleController::class, 'create']);
    Route::patch('/reservation/materielle/confirm/{id}', [ReservationMaterielleController::class, 'confirm']);
    Route::patch('/reservation/materielle/reject/{id}', [ReservationMaterielleController::class, 'reject']);




});
// routes accesible for visiters
    //for annonce
Route::get('/annonces/index/{id}', [AnnonceController::class, 'index']);
Route::get('/annonces/show/{id}', [AnnonceController::class, 'show']);
    // for boutique
Route::get('/boutique', [BoutiqueController::class, 'index']);
Route::get('/boutique/show/{id}', [BoutiqueController::class, 'show']);
    // for feedback
Route::get('/feedback/index', [FeedbackController::class, 'index']);
Route::get('/feedback/show/{id}', [FeedbackController::class, 'show']);
    // for materielle
Route::get('/materielle/compare/{id1}-{id2}', [MaterielleController::class, 'compare']);
Route::get('/materielle/index/{fournisseur_id}', [MaterielleController::class, 'index']);
Route::get('/materielle/show/{materielle_id}', [MaterielleController::class, 'show']);

Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
Route::post('/reset-password', [NewPasswordController::class, 'store']);

// EVENTS
Route::get('/events/search', [EventController::class, 'search']);
Route::get('/events/{id}/participants/stats', [ReservationEventController::class, 'participantStats']);
Route::get('/events/{id}/participants', [ReservationEventController::class, 'participants']);
Route::get('/events/{id}/share-links', [EventController::class, 'getEventShareLinks']);
Route::get('/events/{id}/copy-link', [EventController::class, 'getEventCopyLink']);
Route::get('/events/{id}', [EventController::class, 'getEventDetails']);
Route::get('/events', [EventController::class, 'index']);

// GROUPES DE CAMPING
Route::get('/groupes/search', [GroupController::class, 'searchGroups']);
Route::get('/groupes', [GroupController::class, 'listGroupsWithFeedbacks']);

// Flouci callback
Route::get('/flouci/callback', [PaymentController::class, 'callback']);

// ------------------ ✅ ROUTES AUTHENTIFIÉES ------------------ //

Route::middleware('auth:sanctum')->group(function () {

    // Auth user profile
    Route::get('/user', fn(Request $request) => $request->user());
    Route::get('/email/verify-prompt', EmailVerificationPromptController::class);
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store']);
    Route::put('/user/password', [PasswordController::class, 'update']);
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    // Email verification
    Route::middleware('signed')->get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        return response()->json(['message' => 'Email verified successfully']);
    })->name('verification.verify');

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::post('/profile/photos', [ProfileController::class, 'storeOrUpdateProfilePhotos']);

    // Admin
    Route::middleware('can:isAdmin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{id}', [AdminUserController::class, 'show']);
        Route::put('/users/{id}', [AdminUserController::class, 'update']);
        Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
        Route::put('/users/{id}/toggle-activation', [AdminUserController::class, 'toggleActivation']);
        Route::post('/feedbacks/{id}/moderate', [AdminUserController::class, 'moderate']);
    });

    // Gestion des événements (groupes)
    Route::post('/events', [EventController::class, 'store']);
    Route::put('/events/{id}', [EventController::class, 'update']);
    Route::delete('/events/{id}', [EventController::class, 'destroy']);
    Route::patch('/events/{event}/status', [EventController::class, 'updateStatus']);

    // Réservations classiques
    Route::post('/reservation-event', [ReservationEventController::class, 'createReservationWithPayment']);
    Route::put('/reservation-event/{id}/simulate-payment', [ReservationEventController::class, 'simulatePayment']);
    Route::post('/event/{eventId}/payer', [PaymentController::class, 'initPayment']);
    Route::post('/reservations/{id}/annuler', [ReservationCancellationController::class, 'annulerReservation']);
    Route::patch('/reservations/{id}/status', [ReservationEventController::class, 'updateStatus']);
    Route::delete('/reservations/{id}', [ReservationEventController::class, 'destroy']);
    Route::get('/reservation-event/{id}', [ReservationEventController::class, 'show']);
    Route::middleware('auth:sanctum')->group(function () {
    // Réservations passées de l'utilisateur connecté
    Route::get('/reservations/passees', [ReservationEventController::class, 'mesReservationsPassees']);
});

    // Participants
    Route::post('/events/{event}/participants/manual', [ReservationEventController::class, 'addManualParticipant']);
    Route::get('/events/{eventId}/participants/search', [ReservationEventController::class, 'search']);
    Route::put('/participants/{id}/update', [ReservationEventController::class, 'updateManualParticipant']);

    // Suivre groupes
    Route::post('/groupes/{groupeId}/follow', [FollowGroupeController::class, 'follow']);
    Route::delete('/groupes/{groupeId}/unfollow', [FollowGroupeController::class, 'unfollow']);
    Route::get('/me/followed-groupes', [FollowGroupeController::class, 'myFollowedGroupes']);

    // Feedbacks
    Route::post('/feedbacks/groupes/{groupeId}', [FeedbackController::class, 'storeOrUpdateFeedback']);

    // Notifications
    Route::post('/events/{event}/send-reminders', [NotificationController::class, 'sendRemindersForEvent']);

    // ------------------ ✅ GESTION PAR LES GROUPES ------------------ //

    // 📌 Routes des réservations côté groupes
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

    // Mettre à jour une réservation par ID (auth sanctum)
    Route::put('/reservations/{id}', [ReservationEventController::class, 'updateReservation']);

    // Participants & statistiques avancées
    Route::get('/group/events/{event_id}/participants', [ReservationEventController::class, 'participants']);
    Route::get('/group/events/{event_id}/stats', [ReservationEventController::class, 'participantStats']);
    Route::get('/group/events/{event_id}/search', [ReservationEventController::class, 'search']);
});

// INTÉRÊTS D'ÉVÉNEMENTS
Route::middleware('auth:sanctum')->post('/events/{id}/interest', [EventInterestController::class, 'toggleInterest']);

// Liste feedbacks publics d’un groupe
Route::get('/groupes/{groupe}/feedbacks', [GroupController::class, 'listForGroup']);

// ------------------ ✅ ROUTES ADMIN ------------------ //
Route::middleware('auth:api')->prefix('admin')->group(function () {
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


    Route::get('/reservations/export', [AdminEventReservationController::class, 'exportReservations']); // ici la route export avant {id}

    Route::get('/reservations', [AdminEventReservationController::class, 'listReservations']);
    Route::get('/reservations/{id}', [AdminEventReservationController::class, 'getReservationDetails']);
    Route::delete('/reservations/{id}', [AdminEventReservationController::class, 'deleteReservation']);
    Route::put('/reservations/{id}', [AdminEventReservationController::class, 'update']);
});

// Routes admin réservations manuelles
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    Route::post('/events/{eventId}/participants/manual', [AdminEventReservationController::class, 'addManualParticipant'])
        ->middleware('auth:api');  // ou autre middleware selon config

    Route::get('/stats', [AdminEventReservationController::class, 'stats']);
});




// Routes de  tiket de paiement event
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/payment/confirm/{reservationId}', [PaymentController::class, 'confirmerPaiement']);
    Route::get('/reservation/{id}/imprimer', [PaymentController::class, 'imprimerTicket']);
    Route::get('/reservation/{id}/telecharger', [PaymentController::class, 'telechargerTicket']);
});


// routes/api.php
Route::middleware('auth:sanctum')->get('/events/{event}/participants', [EventParticipantController::class, 'index']);


Route::middleware('auth:sanctum')->group(function () {
    
    // Récupérer la conversation entre l'utilisateur connecté et un participant
    Route::get('/chat/conversation/{receiver_id}/{event_id}', [PrivateChatController::class, 'conversation']);

    // Obtenir le nombre de messages non lus dans un événement
    Route::get('/chat/unread-count/{event_id}', [PrivateChatController::class, 'unreadCount']);

    // Envoyer un message (déjà fonctionnelle)
    Route::middleware('auth:sanctum')->post('/chat/send', [PrivateChatController::class, 'send']);

    Route::middleware('auth:sanctum')->delete('/chat/message/{id}', [PrivateChatController::class, 'deleteMessage']);

    Route::middleware('auth:sanctum')->post('/chat/archive', [PrivateChatController::class, 'archiveConversation']);

    Route::middleware('auth:sanctum')->get('/chat/conversations', [PrivateChatController::class, 'listConversations']);

    Route::middleware('auth:group')->group(function () {
    Route::post('/group-chat/create', [ChatGroupController::class, 'store']);
});

    Route::get('/group-chat/my-groups', [ChatGroupController::class, 'myGroups'])->middleware('auth:group');
    Route::delete('/group-chat/{id}', [ChatGroupController::class, 'destroy'])->middleware('auth:group');
    Route::get('/group-chat/join/{token}', [ChatGroupController::class, 'joinByToken'])->middleware('auth:sanctum');


    Route::middleware('auth:sanctum')->prefix('group-chat')->group(function () {
    Route::post('/{chat_group_id}/message', [ChatGroupController::class, 'sendMessage']);
    Route::get('/{chat_group_id}/messages', [ChatGroupController::class, 'getMessages']);
});
    Route::get('/group-chat/{chat_group_id}/members', [ChatGroupController::class, 'getMembers'])->middleware('auth:sanctum');
    Route::put('/group-chat/{chat_group_id}/rename', [ChatGroupController::class, 'renameGroup'])->middleware('auth:group');
    Route::delete('/group-chat/{chat_group_id}/leave', [ChatGroupController::class, 'leaveGroup'])->middleware('auth:sanctum');

    Route::post('/group-chat/{chat_group_id}/typing', [ChatGroupController::class, 'typingStatus'])->middleware('auth:sanctum');
    Route::get('/group-chat/{chat_group_id}/typing', [ChatGroupController::class, 'typingUsers'])->middleware('auth:sanctum');

    Route::post('/group-chat/{chat_group_id}/archive', [ChatGroupController::class, 'archive'])->middleware('auth:group');

    Route::delete('/group-chat/{chat_group_id}/members/{user_id}', [ChatGroupController::class, 'removeMember'])->middleware('auth:group');



    Route::get('/konnect/success', [PaymentController::class, 'konnectCallback'])->name('konnect.success');
    Route::get('/konnect/fail', [PaymentController::class, 'konnectCallback'])->name('konnect.fail');
    Route::post('/konnect/webhook', [PaymentController::class, 'webhookKonnect']);
    
});


