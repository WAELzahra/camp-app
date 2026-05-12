<?php

namespace App\Providers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (config('filesystems.default') === 'r2') {
            config(['filesystems.disks.public' => config('filesystems.disks.r2')]);
        }
    }
}
