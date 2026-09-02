<?php

namespace App\Providers;

use App\Models\Leave;
use App\Models\OperationalExpense;
use App\Models\Order;
use App\Models\RestockHistory;
use App\Observers\LeaveObserver;
use App\Observers\OpExObserver;
use App\Observers\RestockObserver;
use App\Observers\SaleObserver;
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
    }

    public function boot(): void
    {
        RateLimiter::for('erp', function (Request $request) {
            $key = $request->user()
                ? 'user:'.$request->user()->id
                : 'ip:'.$request->ip();

            return [
                Limit::perMinute((int) env('ERP_API_RATE_LIMIT', 120))->by($key),
            ];
        });

        RateLimiter::for('erp-login', fn (Request $request) =>
            Limit::perMinute((int) env('ERP_LOGIN_RATE_LIMIT', 10))->by($request->ip())
        );

        Order::observe(SaleObserver::class);
        OperationalExpense::observe(OpExObserver::class);
        RestockHistory::observe(RestockObserver::class);
        Leave::observe(LeaveObserver::class);
    }
}
