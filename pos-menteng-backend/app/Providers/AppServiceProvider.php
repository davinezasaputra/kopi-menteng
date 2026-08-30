<?php

namespace App\Providers;

use App\Models\Leave;
use App\Models\OperationalExpense;
use App\Models\Order;
use App\Models\Payroll;
use App\Models\RestockHistory;
use App\Observers\LeaveObserver;
use App\Observers\OpExObserver;
use App\Observers\PayrollObserver;
use App\Observers\RestockObserver;
use App\Observers\SaleObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Order::observe(SaleObserver::class);
        OperationalExpense::observe(OpExObserver::class);
        Payroll::observe(PayrollObserver::class);
        RestockHistory::observe(RestockObserver::class);
        Leave::observe(LeaveObserver::class);
    }
}
