<?php

namespace App\Console\Commands;

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

        $args = $this->buildArgs($class);
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

    /** @return array|null null means "couldn't build args, already reported why" */
    private function buildArgs(string $class): ?array
    {
        $ctor = (new ReflectionClass($class))->getConstructor();
        if (!$ctor) {
            return [];
        }

        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $paramClass = $type->getName();
                if (is_subclass_of($paramClass, Model::class)) {
                    $instance = $paramClass::query()->latest('id')->first();
                    if (!$instance) {
                        $this->error("No {$paramClass} rows in the dev DB to preview with — create one first.");

                        return null;
                    }
                    $args[] = $instance;

                    continue;
                }
            }

            // Scalar constructor arg (code/link/url/etc.) — plausible dummy value.
            $paramName = strtolower($param->getName());
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
