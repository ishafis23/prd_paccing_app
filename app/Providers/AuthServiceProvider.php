<?php

namespace App\Providers;

use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentChannel;
use App\Models\ServiceCatalog;
use App\Models\ServiceReminder;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Team;
use App\Policies\CustomerAcUnitPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\IncomePolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentChannelPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ServiceCatalogPolicy;
use App\Policies\ServiceReminderPolicy;
use App\Policies\StockItemPolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\TeamPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        Customer::class => CustomerPolicy::class,
        CustomerAcUnit::class => CustomerAcUnitPolicy::class,
        ServiceCatalog::class => ServiceCatalogPolicy::class,
        Order::class => OrderPolicy::class,
        Payment::class => PaymentPolicy::class,
        PaymentChannel::class => PaymentChannelPolicy::class,
        StockItem::class => StockItemPolicy::class,
        StockMovement::class => StockMovementPolicy::class,
        ServiceReminder::class => ServiceReminderPolicy::class,
        Expense::class => ExpensePolicy::class,
        Income::class => IncomePolicy::class,
        Team::class => TeamPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Owner = super admin: lolos semua otorisasi.
        Gate::before(function ($user) {
            return $user->hasRole(RoleName::Owner->value) ? true : null;
        });
    }
}
