<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\SellerBalance;
use App\Models\SellerPayoutAccount;
use App\Policies\OrderPolicy;
use App\Policies\SellerBalancePolicy;
use App\Policies\SellerPayoutAccountPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Order::class => OrderPolicy::class,
        SellerBalance::class => SellerBalancePolicy::class,
        SellerPayoutAccount::class => SellerPayoutAccountPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
