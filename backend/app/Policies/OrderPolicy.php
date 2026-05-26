<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->is_admin || in_array($user->getKey(), [$order->buyer_id, $order->seller_id], true);
    }

    public function confirmReceived(User $user, Order $order): bool
    {
        return $order->buyer_id === $user->getKey() && $order->status === OrderStatus::PaidPendingRelease->value;
    }

    public function sellerView(User $user, Order $order): bool
    {
        return $user->is_admin || $order->seller_id === $user->getKey();
    }

    public function buyerView(User $user, Order $order): bool
    {
        return $user->is_admin || $order->buyer_id === $user->getKey();
    }

    public function adminManage(User $user): bool
    {
        return (bool) $user->is_admin;
    }
}
