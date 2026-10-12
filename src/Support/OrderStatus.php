<?php
namespace Lucas\HiPayFin\Support;

/**
 * HiPayFin Order Statuses & Lifecycle Constants
 * 
 * Reference: Section 5.1 & 5.2 in HIPAYFIN_API_DOCUMENTATION_VN.md
 */
class OrderStatus
{
    // Intermediate Statuses
    const CREATED            = 'CREATED';
    const PENDING_APPROVAL   = 'PENDING_APPROVAL';
    const PENDING_EXECUTION  = 'PENDING_EXECUTION';
    const ROUTING            = 'ROUTING';
    const PROVIDER_INITIATED = 'PROVIDER_INITIATED';
    const AWAITING_PAYMENT   = 'AWAITING_PAYMENT';
    const PROVIDER_PENDING   = 'PROVIDER_PENDING';
    const RECONCILING        = 'RECONCILING';
    const HELD               = 'HELD';

    // Terminal Statuses
    const CONFIRMED          = 'CONFIRMED';
    const FAILED             = 'FAILED';
    const EXPIRED            = 'EXPIRED';
    const REJECTED           = 'REJECTED';
    const REVERSED           = 'REVERSED';

    /**
     * Check if a status is terminal (cannot transition further under normal flows).
     * Note: EXPIRED is semi-terminal because HiPayFin still allows late payment transition: EXPIRED -> CONFIRMED.
     *
     * @param string $status
     * @return bool
     */
    public static function isTerminal($status)
    {
        $status = strtoupper(trim((string)$status));
        return in_array($status, array(
            self::CONFIRMED,
            self::FAILED,
            self::EXPIRED,
            self::REJECTED,
            self::REVERSED,
        ), true);
    }

    /**
     * Check whether an order status update can overwrite an existing status.
     * Prevents out-of-order webhooks from downgrading a CONFIRMED order.
     *
     * @param string $currentStatus
     * @param string $newStatus
     * @return bool
     */
    public static function canTransition($currentStatus, $newStatus)
    {
        $current = strtoupper(trim((string)$currentStatus));
        $new = strtoupper(trim((string)$newStatus));

        if ($current === $new) {
            return false;
        }

        // CONFIRMED is final; only manual REVERSED can overwrite CONFIRMED
        if ($current === self::CONFIRMED) {
            return $new === self::REVERSED;
        }

        // EXPIRED can still transition to CONFIRMED (Late Payment credit)
        if ($current === self::EXPIRED && $new === self::CONFIRMED) {
            return true;
        }

        return true;
    }
}
