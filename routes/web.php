<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\SocialAuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Mail;


Route::get('/debug-auth', function () {
    return response()->json([
        'authenticated' => auth()->check(),
        'user' => auth()->user()?->id,
        'session_id' => session()->getId(),
        'guard' => 'web',
    ]);
});

// routes/web.php (for center owners)
Route::middleware(['auth'])->prefix('center')->name('center.')->group(function () {
    Route::prefix('{center}')->group(function () {
        Route::resource('services', \App\Http\Controllers\Center\CenterServiceController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::resource('equipment', \App\Http\Controllers\Center\CenterEquipmentController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        
        Route::post('services/bulk-update', [\App\Http\Controllers\Center\CenterServiceController::class, 'bulkUpdate'])
            ->name('services.bulk-update');
        Route::post('equipment/bulk-update', [\App\Http\Controllers\Center\CenterEquipmentController::class, 'bulkUpdate'])
            ->name('equipment.bulk-update');
    });
});


Route::get('/test-mail', function () {
    Mail::raw('This is a test email from Laravel via MailHog.', function ($message) {
        $message->to('any@example.com')->subject('MailHog Test');
    });

    return 'Email sent!';
});


Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');


// require __DIR__.'/auth.php';
// Route::get('/{any}', function () {
//     return view('app'); // Vue React compilée
// })->where('any', '.*');


Route::get('/redis-test', function () {
    \Illuminate\Support\Facades\Redis::set('foo', 'bar');
    return \Illuminate\Support\Facades\Redis::get('foo');
});
