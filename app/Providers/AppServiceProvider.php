<?php

namespace App\Providers;

use App\Models\Reservations_centre;
use App\Models\Reservations_events;
use App\Models\Reservations_materielles;
use App\Services\Engagement\EngagementSnapshotService;
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

        // Task A-02 §2E — immutable engagement mode/rate snapshot on every
        // reservation at creation time (never changes retroactively).
        Reservations_centre::creating(fn ($r) => EngagementSnapshotService::apply(
            $r, EngagementSnapshotService::profileForCentreReservation($r)
        ));
        Reservations_events::creating(fn ($r) => EngagementSnapshotService::apply(
            $r, EngagementSnapshotService::profileForEventReservation($r)
        ));
        Reservations_materielles::creating(fn ($r) => EngagementSnapshotService::apply(
            $r, EngagementSnapshotService::profileForMaterielleReservation($r)
        ));
    }
}
