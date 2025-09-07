<?php

namespace App\Services;

use App\Constants\OrderStatus;
use App\Dto\CartDto;
use App\Dto\TotalsDto;

class CheckoutService
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private OrderService $orderService,
    ) {}

    public function initSubscriptionCheckout(string $planSlug)
    {
        $subscription = $this->subscriptionService->findNewByPlanSlugAndUser($planSlug, auth()->id());
        if ($subscription === null) {
            $subscription = $this->subscriptionService->create($planSlug, auth()->id());
        }

        return $subscription;
    }

    public function initLocalSubscriptionCheckout(string $planSlug)
    {
        $subscription = $this->subscriptionService->findNewByPlanSlugAndUser($planSlug, auth()->id());
        if ($subscription === null) {
            $subscription = $this->subscriptionService->create($planSlug, auth()->id(), localSubscription: true);
        }

        return $subscription;
    }

    public function initProductCheckout(CartDto $cartDto, TotalsDto $totalsDto)
    {
        $user = auth()->user();

        $isLocalOrder = $totalsDto->amountDue === 0; // If amount due is zero, it's a local order (no payment provider needed)

        $order = null;
        if ($cartDto->orderId !== null) {
            $order = $this->orderService->findNewByIdForUser($cartDto->orderId, $user);
        }

        if ($order === null) {
            $order = $this->orderService->create(
                $user,
                isLocal: $isLocalOrder,
            );
        }

        $this->orderService->refreshOrder($cartDto, $order);

        if (! $isLocalOrder) {
            $order->status = OrderStatus::PENDING->value;
            $order->save();
        }

        return $order;
    }
}
