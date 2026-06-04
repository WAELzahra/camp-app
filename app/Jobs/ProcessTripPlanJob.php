<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AI\TripPlannerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessTripPlanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly int    $userId,
        public readonly string $message,
        public readonly string $jobId,
    ) {}

    public function handle(): void
    {
        /** @var TripPlannerService $service */
        $service = app(TripPlannerService::class);

        $user = User::findOrFail($this->userId);

        $result = $service->plan($user, $this->message);

        Cache::put('ai:job:' . $this->jobId, [
            'status' => 'done',
            'result' => $result,
        ], 3600);
    }

    public function failed(Throwable $e): void
    {
        Cache::put('ai:job:' . $this->jobId, [
            'status' => 'failed',
            'error'  => $e->getMessage(),
        ], 3600);

        Log::error('trip_plan_job_failed', [
            'job_id' => $this->jobId,
            'error'  => $e->getMessage(),
        ]);
    }
}
