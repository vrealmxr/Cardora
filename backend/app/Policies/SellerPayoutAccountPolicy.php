<?php

namespace App\Policies;

use App\Models\SellerPayoutAccount;
use App\Models\User;

class SellerPayoutAccountPolicy
{
    public function view(User $user, SellerPayoutAccount $account): bool
    {
        return $user->is_admin || $account->seller_id === $user->getKey();
    }

    public function update(User $user, SellerPayoutAccount $account): bool
    {
        return $user->is_admin || $account->seller_id === $user->getKey();
    }
}
