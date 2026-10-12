<?php
namespace Lucas\HiPayFin\Support;

/**
 * HiPayFin Webhook Event Constants
 * 
 * Reference: Section 9.3 in HIPAYFIN_API_DOCUMENTATION_VN.md
 */
class WebhookEvent
{
    const PAYMENT_CONFIRMED = 'payment.confirmed';
    const PAYMENT_REJECTED  = 'payment.rejected';
    const PAYOUT_PROCESSED  = 'payout.processed';
    const PAYOUT_BLOCKED    = 'payout.blocked';
}
