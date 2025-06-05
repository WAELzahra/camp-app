<?php


use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use Illuminate\Http\Request;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\AnnonceController;
use App\Http\Controllers\ReservationsCentreController;
use App\Http\Controllers\BoutiqueController;
use App\Http\Controllers\MaterielleController;
use App\Http\Controllers\ReservationMaterielleController;
use App\Http\Controllers\FeedbackController;




// routes for admin 
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // for annonce
    Route::get('/annonces/create', [AnnonceController::class, 'create']);
    Route::get('/annonces/edit/{id}', [AnnonceController::class, 'edit']);
    Route::delete('/annonces/destroy/{id}', [AnnonceController::class, 'destroy']);
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
    // for feedback
    Route::get('/feedback/index_user', [FeedbackController::class, 'index_user']);
    Route::delete('/feedback/destroy/{id}', [FeedbackController::class, 'destroy']);
    Route::get('/feedback/show/{id}', [FeedbackController::class, 'show']);
    Route::delete('/feedback/destroy/{id}', [FeedbackController::class, 'destroy']);
    Route::post('/feedback/create', [FeedbackController::class, 'create']);
    Route::post('/feedback/store', [FeedbackController::class, 'store']);
    Route::get('/feedback/edit/{id}', [FeedbackController::class, 'edit']);
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

// AUTH API (Sanctum)
Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->put('/user/password', [PasswordController::class, 'update']);
Route::middleware('auth:sanctum')->get('/email/verify-prompt', EmailVerificationPromptController::class);


Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
Route::post('/reset-password', [NewPasswordController::class, 'store']);
Route::middleware('auth:sanctum')->post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store']);

Route::middleware(['auth:sanctum', 'signed'])->get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill(); // Mark email as verified
    return response()->json(['message' => 'Email verified successfully']);
})->name('verification.verify');
// Authenticated user
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
});




Route::middleware(['auth:sanctum', 'can:isAdmin'])->group(function () {
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{id}', [AdminUserController::class, 'show']);
    Route::put('/users/{id}', [AdminUserController::class, 'update']);
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
    Route::put('/users/{id}/toggle-activation', [AdminUserController::class, 'toggleActivation']);

});


