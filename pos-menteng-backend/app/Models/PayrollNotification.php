<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollNotification extends Model
{
    protected $fillable = [
        'payroll_id',
        'recipient_type',
        'recipient_phone',
        'message_content',
        'pdf_file_path',
        'provider',
        'provider_message_id',
        'provider_status',
        'status',
        'attempts',
        'last_attempt_at',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'last_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }
}
