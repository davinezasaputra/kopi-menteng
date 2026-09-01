<?php

namespace App\Support\Auth;

use App\Domain\Audit\Services\AuditService;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class PinService
{
    public function __construct(private readonly AuditService $audit, private readonly TenantContext $context) {}

    public function verify(string $pin, User $user, string $key): bool
    {
        if (RateLimiter::tooManyAttempts($key, 5)) return false;
        $valid = (bool) $user->pin_hash && Hash::check($pin, $user->pin_hash);
        if (! $valid) {
            RateLimiter::hit($key,60);
            if ($this->context->membership()) $this->audit->record('failed_login','auth.pin',$user,null,['reason'=>'invalid_pin']);
            return false;
        }
        RateLimiter::clear($key);
        return true;
    }

    public function findByPin(string $pin): ?User
    {
        $lookup=hash_hmac('sha256',$pin,(string)config('app.key'));
        return User::query()->where('pin_lookup',$lookup)->first();
    }

    public function hash(string $pin): string { return Hash::make($pin); }
}
