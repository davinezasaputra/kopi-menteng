<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAutomationConfig extends Model
{
    protected $table = 'payroll_automation_config';

    protected $fillable = [
        'tenant_id',
        'enable_auto_fill',
        'enable_whatsapp_notification',
        'whatsapp_recipient_employee',
        'whatsapp_recipient_manager',
        'manager_phone',
        'notification_timing',
        'message_template',
    ];

    protected $casts = [
        'enable_auto_fill' => 'boolean',
        'enable_whatsapp_notification' => 'boolean',
        'whatsapp_recipient_employee' => 'boolean',
        'whatsapp_recipient_manager' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Organization\Models\Tenant::class);
    }
}
