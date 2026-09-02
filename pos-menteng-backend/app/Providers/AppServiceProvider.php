<?php

namespace App\Providers;

use App\Models\Leave;
use App\Observers\LeaveObserver;
use App\Domain\Hrm\Services\PayrollAutomationService;
use App\Domain\Hrm\Services\ScopedPayrollAutomationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext());
        $this->app->scoped(PayrollAutomationService::class, fn ($app) => $app->make(ScopedPayrollAutomationService::class));
    }

    public function boot(): void
    {
        RateLimiter::for('erp', function (Request $request) {
            $key = $request->user() ? 'user:'.$request->user()->id : 'ip:'.$request->ip();
            return [Limit::perMinute((int) env('ERP_API_RATE_LIMIT', 120))->by($key)];
        });
        RateLimiter::for('erp-login', fn (Request $request) => Limit::perMinute((int) env('ERP_LOGIN_RATE_LIMIT', 10))->by($request->ip()));
        Leave::observe(LeaveObserver::class);
    }
}
