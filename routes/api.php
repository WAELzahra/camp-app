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


// routes for admin 
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // for annonce
    Route::get('/annonces/create', [AnnonceController::class, 'create']);
    Route::get('/annonces/show/{id}', [AnnonceController::class, 'show']);
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

});
// REST Annonce
Route::middleware(['auth:sanctum', 'can.publish'])->group(function () {
    Route::get('/annonces/list/{id}', [AnnonceController::class, 'index']);
    Route::get('/annonces/create', [AnnonceController::class, 'create']);
    Route::post('/annonces', [AnnonceController::class, 'store']);
    Route::patch('/annonces/update/{id}', [AnnonceController::class, 'update']);
    Route::get('/annonces/show/{id}', [AnnonceController::class, 'show']);
    Route::get('/annonces/edit/{id}', [AnnonceController::class, 'edit']);
    Route::delete('/annonces/destroy/{id}', [AnnonceController::class, 'destroy']);
});

// REST Reservation centre
Route::middleware(['auth:sanctum', 'campeur'])->group(function () {
    Route::get('/reservation/centre/{id}', [ReservationsCentreController::class, 'index']);
    Route::get('/reservation/centre/create', [ReservationsCentreController::class, 'create']);
    Route::post('/reservation/centre', [ReservationsCentreController::class, 'store']);
    Route::patch('/reservation/centre/destroy', [ReservationsCentreController::class, 'destroy']);
    Route::get('/reservation/centre/show{id}', [ReservationsCentreController::class, 'show']);

});
//centre confirm/refuse reservation
Route::middleware(['auth:sanctum', 'centre'])->group(function () {
    Route::patch('/reservation/centre/confirm', [ReservationsCentreController::class, 'confirm']);
    Route::patch('/reservation/centre/reject', [ReservationsCentreController::class, 'reject']);
    Route::get('/reservation/centre/{id}', [ReservationsCentreController::class, 'index']);

});
//REST boutique, reservation materielle
Route::middleware(['auth:sanctum', 'fournisseur'])->group(function () {
    // RESET for boutique
    Route::get('/boutique/create', [BoutiqueController::class, 'create']);
    Route::post('/boutique/add', [BoutiqueController::class, 'add']);
    Route::patch('/boutique/update', [BoutiqueController::class, 'update']);
    Route::delete('/boutique/destroy', [BoutiqueController::class, 'destroy']);

});
// routes accesible for visiters
Route::get('/boutique/list', [BoutiqueController::class, 'index']);
Route::get('/boutique/show/{idFournisseur}', [BoutiqueController::class, 'show']);
Route::get('/annonces/list/{id}', [AnnonceController::class, 'index']);

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


