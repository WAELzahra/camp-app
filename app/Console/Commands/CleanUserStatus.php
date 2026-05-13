<?php
// app/Console/Commands/CleanUserStatus.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class CleanUserStatus extends Command
{
    protected $signature = 'users:clean-status';
    protected $description = 'Mark users as offline if inactive';

    public function handle()
    {
        // Mark users as offline if they haven't been active in 10 minutes
        User::where('last_login_at', '<', now()->subMinutes(10))
            ->where('is_active', true)
            ->update(['is_active' => false]);
            
        $this->info('User status cleaned successfully.');
    }
}