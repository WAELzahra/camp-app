<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Dumps the resolved values of every variable email templates depend on,
 * in whichever environment it's run — the point being to catch a config
 * key silently falling back to a hardcoded/wrong default (as happened with
 * mail.support_email before it existed) BEFORE a real email goes out with it.
 * Run this after any deploy that touches mail config: `php artisan mail:check-config`.
 */
class CheckMailConfig extends Command
{
    protected $signature = 'mail:check-config';

    protected $description = 'Print the resolved mail-related config values so you can catch a wrong/missing one before it reaches a real email.';

    public function handle(): int
    {
        $rows = [
            ['mail.default (transport)', config('mail.default')],
            ['mail.from.address', config('mail.from.address')],
            ['mail.from.name', config('mail.from.name')],
            ['mail.support_email', config('mail.support_email')],
            ['app.name', config('app.name')],
            ['app.frontend_url', config('app.frontend_url')],
            ['app.url', config('app.url')],
            ['app.locale', config('app.locale')],
        ];

        $this->table(['Key', 'Resolved value'], $rows);

        $blank = collect($rows)->first(fn ($r) => empty($r[1]));
        if ($blank) {
            $this->error("{$blank[0]} is empty — check your .env for this environment.");

            return self::FAILURE;
        }

        $this->info('All mail-related config values are set.');

        return self::SUCCESS;
    }
}
