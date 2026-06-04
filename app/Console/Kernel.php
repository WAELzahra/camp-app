<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('events:send-reminders')->dailyAt('08:00');
        $schedule->command('users:clean-status')->everyFiveMinutes();
        $schedule->command('ai:warm-weather --limit=50')->dailyAt('05:00');
        $schedule->command('ai:cluster-profiles')->weeklyOn(1, '02:00');
    }


    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
