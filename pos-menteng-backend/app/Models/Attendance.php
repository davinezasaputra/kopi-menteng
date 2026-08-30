<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $guarded = [];
    protected $casts = [
        'tanggal' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leave(): BelongsTo
    {
        return $this->belongsTo(Leave::class);
    }

    /**
     * Check if attendance is for a leave day
     */
    public function isLeave(): bool
    {
        return in_array($this->status, ['izin', 'sakit', 'cuti_tahunan', 'libur_nasional', 'lainnya']);
    }

    /**
     * Check if attendance is marked present (hadir)
     */
    public function isPresent(): bool
    {
        return $this->status === 'hadir';
    }

    /**
     * Check if attendance is late
     */
    public function isLate(): bool
    {
        return $this->status === 'terlambat' || $this->late_minute > 0;
    }
}
