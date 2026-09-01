<?php

namespace App\Support\Auth;

use App\Models\User;
use App\Domain\Audit\Services\AuditService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class PinService
{
    public function __construct(private readonly AuditService $audit) {}

    public function verify(string $pin, User $user, string $key): bool
    {
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return false;
        }

        $valid = $user->pin_hash && Hash::check($pin, $user->pin_hash);
        if (! $valid) {
            RateLimiter::hit($key, 60);
            $this->audit->record('failed_login', 'auth.pin', $user, null, ['reason' => 'invalid_pin']);
            return false;
        }

        RateLimiter::clear($key);
        return true;
    }

    public function hash(string $pin): string
    {
        return Hash::make($pin);
    }
}
