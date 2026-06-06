<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \App\Services\AI\Adapters\LLMAdapterInterface::class,
            function () {
                return match (config('ai.provider')) {
                    'mock'  => new \App\Services\AI\Adapters\MockAdapter(),
                    default => new \App\Services\AI\Adapters\GroqAdapter(
                        app(\App\Services\AI\RateLimitService::class)
                    ),
                };
            }
        );

        $this->app->bind(
            \App\Services\AI\Weather\WeatherAdapterInterface::class,
            function () {
                return match (config('ai.provider')) {
                    'mock'  => new \App\Services\AI\Weather\MockWeatherAdapter(),
                    default => new \App\Services\AI\Weather\OpenWeatherAdapter(
                        app(\App\Services\AI\RateLimitService::class)
                    ),
                };
            }
        );

        $this->app->singleton(
            \App\Services\AI\GearAssistantService::class,
            fn ($app) => new \App\Services\AI\GearAssistantService(
                $app->make(\App\Services\AI\Adapters\LLMAdapterInterface::class)
            )
        );

        $this->app->bind(
            \App\Services\AI\SafetyService::class,
            fn ($app) => new \App\Services\AI\SafetyService(
                $app->make(\App\Services\AI\Adapters\LLMAdapterInterface::class)
            )
        );

        $this->app->singleton(
            \App\Services\AI\Matching\KMeansClusterer::class,
            fn () => new \App\Services\AI\Matching\KMeansClusterer(k: 4)
        );

        $this->app->singleton(
            \App\Services\AI\Matching\DBSCANClusterer::class,
            fn () => new \App\Services\AI\Matching\DBSCANClusterer(epsilon: 0.3, minPoints: 2)
        );

        $this->app->singleton(
            \App\Services\AI\Matching\VectorBuilder::class,
            fn () => new \App\Services\AI\Matching\VectorBuilder()
        );

        $this->app->bind(
            \App\Services\AI\GroupMatchingService::class,
            fn ($app) => new \App\Services\AI\GroupMatchingService(
                $app->make(\App\Services\AI\Adapters\LLMAdapterInterface::class),
                $app->make(\App\Services\AI\Matching\KMeansClusterer::class),
                $app->make(\App\Services\AI\Matching\DBSCANClusterer::class),
                $app->make(\App\Services\AI\Matching\VectorBuilder::class),
            )
        );

        $this->app->bind(
            \App\Services\AI\DynamicPricingService::class,
            fn ($app) => new \App\Services\AI\DynamicPricingService(
                $app->make(\App\Services\AI\Adapters\LLMAdapterInterface::class)
            )
        );

        $this->app->bind(
            \App\Services\AI\ExplainabilityService::class,
            fn ($app) => new \App\Services\AI\ExplainabilityService(
                $app->make(\App\Services\AI\Adapters\LLMAdapterInterface::class)
            )
        );

        $this->app->singleton(
            \App\Services\AI\ProfileExtractorService::class,
            fn () => new \App\Services\AI\ProfileExtractorService()
        );

        $this->app->singleton(
            \App\Services\AI\IntentExtractorService::class,
            fn ($app) => new \App\Services\AI\IntentExtractorService(
                $app->make(\App\Services\AI\Adapters\LLMAdapterInterface::class)
            )
        );

        $this->app->singleton(
            \App\Services\AI\CentreLookupService::class,
            fn () => new \App\Services\AI\CentreLookupService()
        );

        $this->app->singleton(
            \App\Services\AI\ConversationManager::class,
            fn ($app) => new \App\Services\AI\ConversationManager(
                $app->make(\App\Services\AI\Adapters\LLMAdapterInterface::class)
            )
        );

        $this->app->singleton(
            \App\Services\AI\ConversationStateService::class,
            fn () => new \App\Services\AI\ConversationStateService()
        );

        $this->app->singleton(
            \App\Services\AI\BehavioralProfileService::class,
            fn () => new \App\Services\AI\BehavioralProfileService()
        );

        $this->app->singleton(
            \App\Services\AI\GroupACollectorService::class,
            fn ($app) => new \App\Services\AI\GroupACollectorService(
                $app->make(\App\Services\AI\ConversationStateService::class)
            )
        );

        $this->app->singleton(
            \App\Services\AI\BookingPreparationService::class,
            fn () => new \App\Services\AI\BookingPreparationService()
        );

        $this->app->singleton(
            \App\Services\AI\ConfirmationClassifierService::class,
            fn ($app) => new \App\Services\AI\ConfirmationClassifierService(
                $app->make(\App\Services\AI\Adapters\LLMAdapterInterface::class)
            )
        );
    }

    public function boot(): void
    {
        // ── Model Observers: behavioral profile cache invalidation ────────────
        // Each observer calls BehavioralProfileService::invalidate() when the
        // relevant user activity changes, ensuring the recommendation engine
        // always reflects the latest data on the next compute() call.
        \App\Models\Reservations_centre::observe(\App\Observers\ReservationCentreObserver::class);
        \App\Models\Reservations_materielles::observe(\App\Observers\ReservationMaterielleObserver::class);
        \App\Models\Feedback::observe(\App\Observers\FeedbackObserver::class);
        \App\Models\Favorite::observe(\App\Observers\FavoriteObserver::class);

        // Per-user (or per-IP) throttle limiter for all AI routes.
        // Each user can make at most 10 AI requests/minute and 100/day.
        RateLimiter::for('ai', function (Request $request) {
            $by = $request->user()?->id ?: $request->ip();
            return [
                Limit::perMinute(10)->by($by),
                Limit::perDay(100)->by($by),
            ];
        });

        // Weather endpoint: more generous since it's lightweight and public.
        RateLimiter::for('weather', function (Request $request) {
            $by = $request->user()?->id ?: $request->ip();
            return [
                Limit::perMinute(30)->by($by),
                Limit::perDay(500)->by($by),
            ];
        });

        // Safety quick-risk: public IP-based, generous limit for listing cards.
        RateLimiter::for('safety', function (Request $request) {
            return [
                Limit::perMinute(60)->by($request->ip()),
                Limit::perDay(2000)->by($request->ip()),
            ];
        });
        if (config('filesystems.default') === 'r2') {
            config(['filesystems.disks.public' => config('filesystems.disks.r2')]);
        }
    }
}
