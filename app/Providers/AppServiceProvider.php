<?php

namespace App\Providers;

use App\Models\CourseSubscription;
use App\Models\Transaction;
use App\Models\WalletTransaction;
use App\Observers\CourseSubscriptionObserver;
use App\Observers\TransactionObserver;
use App\Observers\WalletTransactionObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        CourseSubscription::observe(CourseSubscriptionObserver::class);
        Transaction::observe(TransactionObserver::class);
        WalletTransaction::observe(WalletTransactionObserver::class);
    }
}
