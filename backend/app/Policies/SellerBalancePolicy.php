<?php

namespace App\Policies;

use App\Models\SellerBalance;
use App\Models\User;

class SellerBalancePolicy
{
    public function view(User $user, SellerBalance $balance): bool
    {
        return $user->is_admin || $balance->seller_id === $user->getKey();
    }
}
