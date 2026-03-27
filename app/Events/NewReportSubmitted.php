<?php

namespace App\Events;

use App\Models\Report;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewReportSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Report $report;

    public function __construct(Report $report)
    {
        $this->report = $report;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin-alerts')];
    }

    public function broadcastWith(): array
    {
        return [
            'id'          => $this->report->id,
            'report_type' => $this->report->report_type,
            'subject'     => $this->report->subject,
            'status'      => $this->report->status,
            'priority'    => $this->report->priority,
            'created_at'  => $this->report->created_at?->toISOString(),
            'reporter'    => $this->report->reporter
                ? [
                    'id'         => $this->report->reporter->id,
                    'first_name' => $this->report->reporter->first_name,
                    'last_name'  => $this->report->reporter->last_name,
                ]
                : null,
        ];
    }

    public function broadcastAs(): string
    {
        return 'report.submitted';
    }
}
