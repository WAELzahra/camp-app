<?php

namespace App\Console\Commands;

use App\Models\Annonce;
use App\Models\Reservations_centre;
use App\Models\Reservations_materielles;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Dev-only helper: sends any app/Mail mailable through the local mailer
 * (Mailpit in dev — nothing actually leaves the machine) using real rows
 * from the dev DB for its Eloquent-model constructor args, so the template
 * renders with realistic data instead of placeholder text.
 */
class PreviewMail extends Command
{
    protected $signature = 'mail:preview {class? : Short class name from app/Mail, e.g. ReservationCreatedToCamper} {--to=preview@example.test}';

    protected $description = 'Send a mailable with real (or dummy) data through the local mailer so you can see how it renders (open http://localhost:8025 for Mailpit).';

    public function handle(): int
    {
        $classes = collect(glob(app_path('Mail/*.php')))
            ->map(fn ($f) => 'App\\Mail\\'.basename($f, '.php'))
            ->filter(fn ($c) => class_exists($c) && is_subclass_of($c, Mailable::class))
            ->values();

        $name = $this->argument('class');

        if (!$name) {
            $this->info('Available mailables:');
            $classes->each(fn ($c) => $this->line('  '.class_basename($c)));
            $this->line('');
            $this->line('Run: php artisan mail:preview <Name>');

            return self::SUCCESS;
        }

        $class = "App\\Mail\\{$name}";
        if (!$classes->contains($class)) {
            $this->error("Unknown mailable: {$name}. Run without an argument to list available ones.");

            return self::FAILURE;
        }

        $args = $this->buildArgs($class, $name);
        if ($args === null) {
            return self::FAILURE;
        }

        $mailable = new $class(...$args);
        $to = $this->option('to');
        Mail::to($to)->send($mailable);

        // Mailables implementing ShouldQueue get queued instead of sent immediately
        // (even via ->send() — that's Mailer::sendMailable()'s own behavior), so for
        // a preview we want it delivered right now: drain exactly the one job.
        if ($mailable instanceof ShouldQueue) {
            Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
        }

        $this->info("Sent {$name} to {$to} — check Mailpit at http://localhost:8025");

        return self::SUCCESS;
    }

    /**
     * Several mailables take an untyped `$reservation`/`$user`/`$annonce` (no
     * class type-hint on the constructor param), so reflection alone can't
     * tell what model to load — it used to fall back to the literal string
     * "preview", which then crashed the template the first time it dereferenced
     * a property on it (e.g. "Attempt to read property [x] on string"). This
     * map is consulted first for exactly those known cases.
     *
     * @var array<string, array<string, class-string<Model>>>
     */
    private const UNTYPED_PARAM_OVERRIDES = [
        'CenterReservationCancellation' => ['center' => User::class, 'reservation' => Reservations_centre::class],
        'ReservationConfirmedToCamper' => ['reservation' => Reservations_materielles::class],
        'ReservationCanceledByCenter' => ['user' => User::class, 'center' => User::class, 'reservation' => Reservations_centre::class],
        'ReservationCanceledByUser' => ['center' => User::class, 'user' => User::class, 'reservation' => Reservations_centre::class],
        'UserReservationCancellation' => ['user' => User::class, 'reservation' => Reservations_centre::class],
        'ReservationRejected' => ['user' => User::class, 'reservation' => Reservations_centre::class],
        'NewReservationToFournisseur' => ['reservation' => Reservations_materielles::class, 'camper' => User::class],
        'ReservationRejectedToUser' => ['user' => User::class],
        'AnnonceDeactivatedNotification' => ['annonce' => Annonce::class],
        'RequestAnnonceActivation' => ['annonce' => Annonce::class],
    ];

    /**
     * These mailables only ever get constructed with a reservation whose
     * cancellation timestamp/reason was just set in the same request — a
     * plain "latest row" can easily land on one that was never cancelled,
     * so prefer a row with this column populated when one exists.
     *
     * @var array<string, string>
     */
    private const PREFER_NOT_NULL = [
        'CenterReservationCancellation' => 'canceled_at',
        'ReservationCanceledByCenter' => 'canceled_at',
        'ReservationCanceledByUser' => 'canceled_at',
        'UserReservationCancellation' => 'canceled_at',
        'ReservationRejected' => 'reason',
    ];

    /** @return array|null null means "couldn't build args, already reported why" */
    private function buildArgs(string $class, string $shortName): ?array
    {
        $ctor = (new ReflectionClass($class))->getConstructor();
        if (!$ctor) {
            return [];
        }

        $overrides = self::UNTYPED_PARAM_OVERRIDES[$shortName] ?? [];
        $preferColumn = self::PREFER_NOT_NULL[$shortName] ?? null;

        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            $paramName = strtolower($param->getName());

            $modelClass = null;
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && is_subclass_of($type->getName(), Model::class)) {
                $modelClass = $type->getName();
            } elseif (isset($overrides[$param->getName()])) {
                $modelClass = $overrides[$param->getName()];
            }

            if ($modelClass) {
                $instance = null;
                if ($preferColumn && $paramName === 'reservation') {
                    $instance = $modelClass::query()->whereNotNull($preferColumn)->latest('id')->first();
                }
                $instance ??= $modelClass::query()->latest('id')->first();
                if (!$instance) {
                    if ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();

                        continue;
                    }
                    $this->error("No {$modelClass} rows in the dev DB to preview with — create one first.");

                    return null;
                }
                $args[] = $instance;

                continue;
            }

            // Scalar constructor arg (code/link/url/etc.) — plausible dummy value.
            $args[] = match (true) {
                str_contains($paramName, 'code') => 'PREVIEW-123456',
                str_contains($paramName, 'link') || str_contains($paramName, 'url') => 'https://example.test/preview',
                $param->isDefaultValueAvailable() => $param->getDefaultValue(),
                default => 'preview',
            };
        }

        return $args;
    }
}
