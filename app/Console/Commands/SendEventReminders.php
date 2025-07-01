<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Events;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders'; // commande artisan

    protected $description = 'Envoyer des mails de rappel pour événements à venir';

    public function handle()
{
    $targetDate = now()->addDay()->toDateString();

    $events = Events::whereDate('date_sortie', $targetDate)->get();

    foreach ($events as $event) {
        $this->sendRemindersForEvent($event);
    }

    $this->info('Mails de rappel envoyés');
}
}
