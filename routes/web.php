<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\SocialAuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Mail;


Route::post('/login', [AuthenticatedSessionController::class, 'store']);


Route::get('/test-mail', function () {
    Mail::raw('This is a test email from Laravel via MailHog.', function ($message) {
        $message->to('any@example.com')->subject('MailHog Test');
    });

    return 'Email sent!';
});

// Google
Route::get('/auth/google/redirect', [SocialAuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback']);

// Facebook
Route::get('/auth/facebook/redirect', [SocialAuthController::class, 'redirectToFacebook']);
Route::get('/auth/facebook/callback', [SocialAuthController::class, 'handleFacebookCallback']);


Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
  Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
                ->name('logout');



// require __DIR__.'/auth.php';
// Route::get('/{any}', function () {
//     return view('app'); // Vue React compilée
// })->where('any', '.*');


Route::get('/redis-test', function () {
    \Illuminate\Support\Facades\Redis::set('foo', 'bar');
    return \Illuminate\Support\Facades\Redis::get('foo');
});
