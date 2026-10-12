<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Support\OrderStatus;
use Lucas\HiPayFin\Support\WebhookEvent;

class OrderStatusTest
{
    public function testOrderStatusConstantsAndTerminal()
    {
        if (!OrderStatus::isTerminal(OrderStatus::CONFIRMED)) {
            throw new \Exception('CONFIRMED must be terminal');
        }
        if (!OrderStatus::isTerminal(OrderStatus::FAILED)) {
            throw new \Exception('FAILED must be terminal');
        }
        if (!OrderStatus::isTerminal(OrderStatus::EXPIRED)) {
            throw new \Exception('EXPIRED must be terminal');
        }
        if (OrderStatus::isTerminal(OrderStatus::AWAITING_PAYMENT)) {
            throw new \Exception('AWAITING_PAYMENT is not terminal');
        }
        return true;
    }

    public function testStatusTransitionPrecedence()
    {
        // 1. Critical late payment: EXPIRED -> CONFIRMED MUST be allowed!
        if (!OrderStatus::canTransition(OrderStatus::EXPIRED, OrderStatus::CONFIRMED)) {
            throw new \Exception('Transition EXPIRED -> CONFIRMED must be permitted for late payments');
        }

        // 2. Prevent downgrade: CONFIRMED cannot be downgraded to FAILED or EXPIRED
        if (OrderStatus::canTransition(OrderStatus::CONFIRMED, OrderStatus::FAILED)) {
            throw new \Exception('CONFIRMED must never be downgraded to FAILED');
        }
        if (OrderStatus::canTransition(OrderStatus::CONFIRMED, OrderStatus::EXPIRED)) {
            throw new \Exception('CONFIRMED must never be downgraded to EXPIRED');
        }

        // 3. Reversal: CONFIRMED can transition to REVERSED
        if (!OrderStatus::canTransition(OrderStatus::CONFIRMED, OrderStatus::REVERSED)) {
            throw new \Exception('CONFIRMED must be allowed to transition to REVERSED');
        }

        // 4. Same status
        if (OrderStatus::canTransition(OrderStatus::CONFIRMED, OrderStatus::CONFIRMED)) {
            throw new \Exception('Same status transition must return false');
        }
        return true;
    }

    public function testWebhookEventConstants()
    {
        if (WebhookEvent::PAYMENT_CONFIRMED !== 'payment.confirmed') {
            throw new \Exception('PAYMENT_CONFIRMED mismatch');
        }
        if (WebhookEvent::PAYMENT_REJECTED !== 'payment.rejected') {
            throw new \Exception('PAYMENT_REJECTED mismatch');
        }
        if (WebhookEvent::PAYOUT_PROCESSED !== 'payout.processed') {
            throw new \Exception('PAYOUT_PROCESSED mismatch');
        }
        if (WebhookEvent::PAYOUT_BLOCKED !== 'payout.blocked') {
            throw new \Exception('PAYOUT_BLOCKED mismatch');
        }
        return true;
    }
}
