<?php
declare(strict_types=1);

require_once __DIR__ . '/VisitState.php';
require_once __DIR__ . '/AbstractVisitState.php';
require_once __DIR__ . '/TerminalVisitState.php';

/**
 * Factory / registry for visit lifecycle states (State pattern).
 */
final class VisitStateRegistry
{
    private static ?array $states = null;

    /**
     * Normalize UI / DB variants to the canonical status stored in visit_requests.status
     */
    public static function normalize(string $status): string
    {
        $key = strtolower(preg_replace('/[\s\-]+/', '', trim($status)));

        return match ($key) {
            'pending' => 'Pending',
            'accepted', 'confirmed' => 'Accepted',
            'enroute', 'en_route' => 'En_Route',
            'live' => 'Live',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
            'cancelled', 'canceled' => 'Cancelled',
            'rated' => 'Rated',
            default => $status,
        };
    }

    public static function isTerminal(string $status): bool
    {
        $n = self::normalize($status);

        return in_array($n, ['Completed', 'Cancelled', 'Rejected', 'Rated'], true);
    }

    public static function canCancel(string $status): bool
    {
        return !self::isTerminal($status);
    }

    public static function forStatus(string $statusRaw): VisitState
    {
        self::boot();
        $name = self::normalize($statusRaw);

        return self::$states[$name] ?? new TerminalVisitState($name);
    }

    private static function boot(): void
    {
        if (self::$states !== null) {
            return;
        }

        require_once __DIR__ . '/ConcreteVisitState.php';

        self::$states = [
            'Pending' => new ConcreteVisitState('Pending', ['Accepted', 'Rejected']),
            'Accepted' => new ConcreteVisitState('Accepted', ['Live', 'En_Route', 'Completed']),
            'En_Route' => new ConcreteVisitState('En_Route', ['Live', 'Completed']),
            'Live' => new ConcreteVisitState('Live', ['Completed']),
            'Completed' => new TerminalVisitState('Completed'),
            'Rejected' => new TerminalVisitState('Rejected'),
            'Cancelled' => new TerminalVisitState('Cancelled'),
            'Rated' => new TerminalVisitState('Rated'),
        ];
    }

    public static function canTransition(string $fromRaw, string $toRaw): bool
    {
        $from = self::normalize($fromRaw);
        $to = self::normalize($toRaw);
        if ($from === $to) {
            return false;
        }

        $state = self::forStatus($from);

        return in_array($to, $state->allowedNextStatuses(), true);
    }
}
