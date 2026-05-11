<?php
declare(strict_types=1);

/**
 * Application event names for the Observer / EventDispatcher.
 */
final class AppEvents
{
    public const VISIT_BOOKED = 'visit.booked';

    public const VISIT_PAYOUT_COMPLETED = 'visit.payout_completed';

    public const VISIT_CANCELLED = 'visit.cancelled';
}
