<?php

namespace App\Domain\Hrm\Services;

use App\Domain\Audit\Services\AuditService;
use App\Jobs\SendPayrollNotificationJob;
use App\Models\Payroll;
use App\Models\PayrollAutomationConfig;
use App\Models\PayrollNotification;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PayrollAutomationService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditService $audit,
    ) {
    }

    public function getConfig(): PayrollAutomationConfig
    {
        return PayrollAutomationConfig::firstOrCreate(
            ['tenant_id' => $this->context->tenantId()],
            [
                'enable_auto_fill' => true,
                'enable_whatsapp_notification' => true,
                'whatsapp_recipient_employee' => true,
                'whatsapp_recipient_manager' => true,
                'notification_timing' => 'immediate',
                'message_template' => 'Slip gaji periode {period} untuk {employee_name} terlampir.',
            ],
        );
    }

    public function updateConfig(array $attributes): PayrollAutomationConfig
    {
        $config = $this->getConfig();
        $old = $config->toArray();

        $config->fill([
            'enable_auto_fill' => (bool) ($attributes['enable_auto_fill'] ?? $config->enable_auto_fill),
            'enable_whatsapp_notification' => (bool) ($attributes['enable_whatsapp_notification'] ?? $config->enable_whatsapp_notification),
            'whatsapp_recipient_employee' => (bool) ($attributes['whatsapp_recipient_employee'] ?? $config->whatsapp_recipient_employee),
            'whatsapp_recipient_manager' => (bool) ($attributes['whatsapp_recipient_manager'] ?? $config->whatsapp_recipient_manager),
            'manager_phone' => $attributes['manager_phone'] ?? $config->manager_phone,
            'notification_timing' => $attributes['notification_timing'] ?? $config->notification_timing,
            'message_template' => $attributes['message_template'] ?? $config->message_template,
        ]);
        $config->save();

        $this->audit->record('updated', 'hrm.payroll_automation_config', $config, $old, $config->fresh()->toArray());

        return $config->fresh();
    }

    public function autoFillPayroll(Payroll $payroll): array
    {
        $this->assertPayrollAccessible($payroll);

        $payroll->loadMissing('employee');
        $employee = $payroll->employee;
        if (! $employee) {
            throw new RuntimeException('Karyawan payroll tidak ditemukan.');
        }

        $period = $this->periodStart($payroll->period);
        $periodEnd = $period->copy()->endOfMonth()->toDateString();
        $attendance = $employee->attendances()
            ->whereBetween('tanggal', [$period->toDateString(), $periodEnd])
            ->get(['tanggal', 'status', 'late_minute']);

        $baseSalary = (float) ($employee->base_sallary ?? $payroll->base_salary ?? 0);
        $allowance = (float) ($payroll->allowance ?? 0);
        $manualDeduction = (float) ($payroll->deduction ?? 0);
        $attendanceDeduction = $this->calculateDeductions($employee, $period, $attendance, $baseSalary);
        $deduction = max(0, $manualDeduction + $attendanceDeduction);
        $total = max(0, $baseSalary + $allowance - $deduction);

        $old = $payroll->toArray();
        $payroll->update([
            'base_salary' => $baseSalary,
            'allowance' => $allowance,
            'deduction' => $deduction,
            'total_salary' => $total,
        ]);

        $summary = [
            'period' => $period->format('Y-m'),
            'base_salary' => $baseSalary,
            'allowance' => $allowance,
            'manual_deduction' => $manualDeduction,
            'attendance_deduction' => $attendanceDeduction,
            'deduction' => $deduction,
            'total_salary' => $total,
            'attendance_days' => $attendance->count(),
            'late_days' => $attendance->filter(fn ($row) => in_array((string) ($row->status ?? ''), ['terlambat', 'late'], true) || (int) ($row->late_minute ?? 0) > 0)->count(),
            'late_minutes' => (int) $attendance->sum('late_minute'),
            'absence_days' => $attendance->filter(fn ($row) => in_array((string) ($row->status ?? ''), ['absence', 'absen', 'alpha', 'tidak_hadir'], true))->count(),
            'sick_days' => $attendance->filter(fn ($row) => in_array((string) ($row->status ?? ''), ['sakit', 'sick'], true))->count(),
        ];

        $this->audit->record('auto_filled', 'hrm.payroll', $payroll, $old, $payroll->fresh()->toArray());

        return $summary;
    }

    public function calculateDeductions($employee, Carbon $period, $attendance = null, float $baseSalary = 0): float
    {
        if (! Schema::hasTable('attendance_penalties')) {
            return 0.0;
        }

        $attendance = $attendance ?? $employee->attendances()
            ->whereBetween('tanggal', [$period->toDateString(), $period->copy()->endOfMonth()->toDateString()])
            ->get(['status', 'late_minute']);

        $lateMinutes = (int) $attendance->sum('late_minute');
        $absenceDays = $attendance->filter(fn ($row) => in_array((string) ($row->status ?? ''), ['absence', 'absen', 'alpha', 'tidak_hadir'], true))->count();
        $penalty = 0.0;

        if ($lateMinutes > 0) {
            $rule = \DB::table('attendance_penalties')
                ->where('tenant_id', $this->context->tenantId())
                ->where('penalty_type', 'late')
                ->where('is_active', true)
                ->orderByDesc('penalty_amount')
                ->get()
                ->first(function ($row) use ($lateMinutes) {
                    return $this->thresholdMinutes((string) $row->duration_threshold) <= $lateMinutes;
                });

            if ($rule) {
                $penalty += $this->penaltyAmount($rule, $baseSalary);
            }
        }

        if ($absenceDays > 0) {
            $rule = \DB::table('attendance_penalties')
                ->where('tenant_id', $this->context->tenantId())
                ->where('penalty_type', 'absence')
                ->where('is_active', true)
                ->orderByDesc('penalty_amount')
                ->get()
                ->first();

            if ($rule) {
                $penalty += $absenceDays * $this->penaltyAmount($rule, $baseSalary);
            }
        }

        return round(max(0, $penalty), 2);
    }

    public function generatePayrollPDF(Payroll $payroll): string
    {
        $this->assertPayrollAccessible($payroll);
        $payroll->loadMissing('employee');

        $attendanceSummary = $this->attendanceSummary($payroll);
        $path = 'payroll/' . $this->context->tenantId() . '/' . $payroll->id . '.pdf';

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('payroll.slip', [
                'payroll' => $payroll,
                'attendanceSummary' => $attendanceSummary,
            ]);
            Storage::disk('public')->put($path, $pdf->output());
        } else {
            Storage::disk('public')->put($path, $this->minimalPdf([
                'KOPI MENTENG - SLIP GAJI',
                'Periode: ' . (string) $payroll->period,
                'Karyawan: ' . ($payroll->employee?->name ?? 'Karyawan'),
                'Jabatan: ' . ($payroll->employee?->position ?? '-'),
                'Gaji Pokok: Rp ' . number_format((float) $payroll->base_salary, 0, ',', '.'),
                'Tunjangan: Rp ' . number_format((float) $payroll->allowance, 0, ',', '.'),
                'Potongan: Rp ' . number_format((float) $payroll->deduction, 0, ',', '.'),
                'Total Gaji: Rp ' . number_format((float) $payroll->total_salary, 0, ',', '.'),
                'Kehadiran: ' . $attendanceSummary['attendance_days'] . ' hari',
                'Terlambat: ' . $attendanceSummary['late_minutes'] . ' menit',
                'Ketidakhadiran: ' . $attendanceSummary['absence_days'] . ' hari',
            ]));
        }

        return $path;
    }

    public function handlePaidPayroll(Payroll $payroll): array
    {
        $this->assertPayrollAccessible($payroll);

        $config = $this->getConfig();
        if (! $config->enable_whatsapp_notification) {
            return ['status' => 'disabled', 'notifications' => 0];
        }

        $pdfPath = $this->generatePayrollPDF($payroll);
        return $this->queuePayrollNotifications($payroll, $pdfPath, $config);
    }

    public function queuePayrollNotifications(Payroll $payroll, string $pdfPath, ?PayrollAutomationConfig $config = null): array
    {
        $this->assertPayrollAccessible($payroll);
        $config ??= $this->getConfig();
        $payroll->loadMissing('employee');

        $template = (string) ($config->message_template ?: 'Slip gaji periode {period} untuk {employee_name} terlampir.');
        $message = strtr($template, [
            '{period}' => (string) $payroll->period,
            '{employee_name}' => (string) ($payroll->employee?->name ?? 'Karyawan'),
        ]);

        $recipients = [];
        if ($config->whatsapp_recipient_employee) {
            $recipients['employee'] = $payroll->employee?->WA;
        }
        if ($config->whatsapp_recipient_manager) {
            $recipients['manager'] = $config->manager_phone;
        }

        $created = [];
        foreach ($recipients as $type => $phone) {
            $notification = PayrollNotification::updateOrCreate(
                ['payroll_id' => $payroll->id, 'recipient_type' => $type],
                [
                    'recipient_phone' => $phone ? $this->normalizePhone((string) $phone) : null,
                    'message_content' => $message,
                    'pdf_file_path' => $pdfPath,
                    'provider' => strtolower((string) env('WHATSAPP_PROVIDER', 'twilio')),
                    'provider_status' => null,
                    'status' => 'pending',
                    'error_message' => null,
                ],
            );

            $created[] = $notification;
            $job = new SendPayrollNotificationJob($notification->id);

            if ($config->notification_timing === 'next_day') {
                $job->delay(Carbon::now('Asia/Jakarta')->addDay()->setTime(9, 0));
            }

            dispatch($job);
        }

        $this->audit->record('notification_queued', 'hrm.payroll', $payroll, null, [
            'notification_count' => count($created),
            'pdf_file_path' => $pdfPath,
        ]);

        return [
            'status' => 'queued',
            'notifications' => count($created),
            'pdf_file_path' => $pdfPath,
            'items' => collect($created)->map(fn (PayrollNotification $row) => $row->fresh())->values(),
        ];
    }

    public function sendNotification(PayrollNotification $notification): PayrollNotification
    {
        $notification->loadMissing('payroll.employee');
        $payroll = $notification->payroll;
        $this->assertPayrollAccessible($payroll);

        $notification->update([
            'status' => 'processing',
            'attempts' => ((int) $notification->attempts) + 1,
            'last_attempt_at' => now(),
            'error_message' => null,
        ]);

        try {
            $provider = strtolower((string) ($notification->provider ?: env('WHATSAPP_PROVIDER', 'twilio')));
            $result = match ($provider) {
                'twilio' => $this->sendViaTwilio($notification),
                default => throw new RuntimeException('Provider WhatsApp tidak didukung: ' . $provider),
            };

            $notification->update([
                'status' => 'sent',
                'provider_message_id' => $result['message_id'] ?? null,
                'provider_status' => $result['status'] ?? 'queued',
                'sent_at' => now(),
                'error_message' => null,
            ]);

            $this->audit->record('notification_sent', 'hrm.payroll_notification', $notification, null, $notification->fresh()->toArray());

            return $notification->fresh();
        } catch (\Throwable $e) {
            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $this->audit->record('notification_failed', 'hrm.payroll_notification', $notification, null, [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function syncNotificationStatus(PayrollNotification $notification): PayrollNotification
    {
        $this->assertPayrollAccessible($notification->payroll()->firstOrFail());

        if (strtolower((string) $notification->provider) !== 'twilio' || ! $notification->provider_message_id) {
            return $notification->fresh();
        }

        $sid = (string) env('TWILIO_ACCOUNT_SID');
        $token = (string) env('TWILIO_AUTH_TOKEN');
        if ($sid === '' || $token === '') {
            return $notification->fresh();
        }

        $response = Http::withBasicAuth($sid, $token)
            ->get('https://api.twilio.com/2010-04-01/Accounts/' . $sid . '/Messages/' . rawurlencode($notification->provider_message_id) . '.json');

        if ($response->successful()) {
            $providerStatus = (string) ($response->json('status') ?? '');
            $notification->update([
                'provider_status' => $providerStatus ?: $notification->provider_status,
                'status' => $this->localStatusFromProvider($providerStatus),
                'sent_at' => in_array($providerStatus, ['sent', 'delivered', 'read'], true) ? ($notification->sent_at ?? now()) : $notification->sent_at,
            ]);
        }

        return $notification->fresh();
    }

    private function sendViaTwilio(PayrollNotification $notification): array
    {
        $accountSid = (string) env('TWILIO_ACCOUNT_SID');
        $authToken = (string) env('TWILIO_AUTH_TOKEN');
        $from = (string) env('TWILIO_WHATSAPP_FROM');

        if ($accountSid === '' || $authToken === '' || $from === '') {
            throw new RuntimeException('Konfigurasi Twilio belum lengkap. Set TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, dan TWILIO_WHATSAPP_FROM.');
        }

        $phone = $this->normalizePhone((string) $notification->recipient_phone);
        if ($phone === '') {
            throw new RuntimeException('Nomor WhatsApp penerima belum diisi.');
        }

        if (! $notification->pdf_file_path || ! Storage::disk('public')->exists($notification->pdf_file_path)) {
            throw new RuntimeException('File PDF payroll tidak ditemukan.');
        }

        $mediaUrl = $this->publicMediaUrl($notification->pdf_file_path);
        if (filter_var($mediaUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('URL media PDF tidak valid. Set APP_URL atau WHATSAPP_MEDIA_BASE_URL yang dapat diakses provider.');
        }

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->asForm()
            ->post('https://api.twilio.com/2010-04-01/Accounts/' . $accountSid . '/Messages.json', [
                'From' => str_starts_with($from, 'whatsapp:') ? $from : 'whatsapp:' . $from,
                'To' => 'whatsapp:' . $phone,
                'MediaUrl' => $mediaUrl,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Twilio gagal mengirim payroll PDF: ' . ($response->json('message') ?? $response->body()));
        }

        return [
            'message_id' => $response->json('sid'),
            'status' => $response->json('status', 'queued'),
        ];
    }

    private function attendanceSummary(Payroll $payroll): array
    {
        $payroll->loadMissing('employee');
        $period = $this->periodStart($payroll->period);
        $attendance = $payroll->employee?->attendances()
            ->whereBetween('tanggal', [$period->toDateString(), $period->copy()->endOfMonth()->toDateString()])
            ->get(['status', 'late_minute']) ?? collect();

        return [
            'attendance_days' => $attendance->count(),
            'late_days' => $attendance->filter(fn ($row) => in_array((string) ($row->status ?? ''), ['terlambat', 'late'], true) || (int) ($row->late_minute ?? 0) > 0)->count(),
            'late_minutes' => (int) $attendance->sum('late_minute'),
            'absence_days' => $attendance->filter(fn ($row) => in_array((string) ($row->status ?? ''), ['absence', 'absen', 'alpha', 'tidak_hadir'], true))->count(),
            'sick_days' => $attendance->filter(fn ($row) => in_array((string) ($row->status ?? ''), ['sakit', 'sick'], true))->count(),
        ];
    }

    private function assertPayrollAccessible(Payroll $payroll): void
    {
        $query = Payroll::query()
            ->where('id', $payroll->id)
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->where('branch_id', $this->context->branchId());

        if (! $query->exists()) {
            abort(404, 'Payroll tidak ditemukan pada context aktif.');
        }
    }

    private function periodStart(string $period): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m', substr($period, 0, 7), 'Asia/Jakarta')->startOfMonth();
        } catch (\Throwable) {
            throw new RuntimeException('Periode payroll harus menggunakan format YYYY-MM.');
        }
    }

    private function penaltyAmount(object $row, float $baseSalary): float
    {
        if ((string) $row->penalty_type_payment === 'percentage_of_salary') {
            return max(0, $baseSalary * ((float) $row->penalty_amount / 100));
        }

        return max(0, (float) $row->penalty_amount);
    }

    private function thresholdMinutes(string $threshold): int
    {
        if (preg_match('/(\d+)\s*hour/i', $threshold, $matches)) {
            return (int) $matches[1] * 60;
        }
        if (preg_match('/(\d+)\s*minute/i', $threshold, $matches)) {
            return (int) $matches[1];
        }
        if (preg_match('/full[_ -]?day/i', $threshold)) {
            return 8 * 60;
        }

        return 0;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';
        if ($phone === '') {
            return '';
        }
        if (str_starts_with($phone, '00')) {
            return '+' . substr($phone, 2);
        }
        if (str_starts_with($phone, '0')) {
            return '+62' . substr($phone, 1);
        }
        if (! str_starts_with($phone, '+')) {
            return '+' . $phone;
        }

        return $phone;
    }

    private function publicMediaUrl(string $path): string
    {
        $base = rtrim((string) env('WHATSAPP_MEDIA_BASE_URL', config('app.url')), '/');
        $relative = ltrim(Storage::disk('public')->url($path), '/');

        return $base . '/' . $relative;
    }

    private function localStatusFromProvider(string $status): string
    {
        return match ($status) {
            'queued', 'sending' => 'processing',
            'sent', 'delivered', 'read' => 'sent',
            'failed', 'undelivered' => 'failed',
            default => 'pending',
        };
    }

    private function minimalPdf(array $lines): string
    {
        $safeLines = array_map(function ($line) {
            $line = preg_replace('/[^\x20-\x7E]/', '?', (string) $line) ?? '';
            return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
        }, $lines);

        $stream = "BT\n/F1 11 Tf\n50 780 Td\n14 TL\n";
        foreach ($safeLines as $index => $line) {
            if ($index > 0) {
                $stream .= "T*\n";
            }
            $stream .= '(' . $line . ") Tj\n";
        }
        $stream .= "ET";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . count($objects) + 1 . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < count($offsets); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }
}
