<?php

namespace App\Jobs;

use App\Domain\Hrm\Services\PayrollAutomationService;
use App\Models\PayrollNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;
use Throwable;

class SendPayrollNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $notificationId)
    {
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping('payroll-notification:' . $this->notificationId)];
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(PayrollAutomationService $service): void
    {
        $notification = PayrollNotification::find($this->notificationId);
        if (! $notification) {
            return;
        }

        $service->sendNotification($notification);
    }

    public function failed(Throwable $exception): void
    {
        $notification = PayrollNotification::find($this->notificationId);
        if (! $notification) {
            return;
        }

        $notification->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
