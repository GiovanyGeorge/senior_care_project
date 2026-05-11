<?php
declare(strict_types=1);

require_once __DIR__ . '/ObserverInterface.php';
require_once __DIR__ . '/DomainEvent.php';
require_once __DIR__ . '/AppEvents.php';
require_once __DIR__ . '/../../models/Notification.php';

/**
 * Observer that persists user-visible notifications for domain events.
 */
final class NotificationObserver implements ObserverInterface
{
    public function handle(DomainEvent $event): void
    {
        match ($event->name) {
            AppEvents::VISIT_BOOKED => $this->onVisitBooked($event->payload),
            AppEvents::VISIT_PAYOUT_COMPLETED => $this->onVisitPayoutCompleted($event->payload),
            AppEvents::VISIT_CANCELLED => $this->onVisitCancelled($event->payload),
            default => null,
        };
    }

    private function onVisitBooked(array $p): void
    {
        $visitId = (int)($p['visit_id'] ?? 0);
        $palUserId = isset($p['pal_user_id']) ? (int)$p['pal_user_id'] : 0;
        $notification = new Notification();

        if ($palUserId > 0) {
            $notification->create($palUserId, 'New Visit Request', 'A new visit request is waiting for your response.');
        }

        foreach ($p['proxy_user_ids'] ?? [] as $proxyUserId) {
            $id = (int)$proxyUserId;
            if ($id > 0) {
                $notification->create(
                    $id,
                    'Senior Requested Service',
                    'A linked senior requested a new service. Visit #' . $visitId . ' is pending.'
                );
            }
        }
    }

    private function onVisitPayoutCompleted(array $p): void
    {
        $palUserId = (int)($p['pal_user_id'] ?? 0);
        $seniorUserId = (int)($p['senior_user_id'] ?? 0);
        $notification = new Notification();
        if ($palUserId > 0) {
            $notification->create($palUserId, 'Payout Completed', 'Your service payout was added to your SilverPoints balance.');
        }
        if ($seniorUserId > 0) {
            $notification->create($seniorUserId, 'Visit Completed', 'Your visit has been completed successfully.');
        }
    }

    private function onVisitCancelled(array $p): void
    {
        $palUserId = (int)($p['pal_user_id'] ?? 0);
        $seniorUserId = (int)($p['senior_user_id'] ?? 0);
        $cancelledBy = (string)($p['cancelled_by'] ?? 'senior');
        $notification = new Notification();

        if ($palUserId > 0) {
            $palMessage = $cancelledBy === 'pal'
                ? 'You cancelled a service.'
                : 'A scheduled service was cancelled.';
            $notification->create($palUserId, 'Service Cancelled', $palMessage);
        }

        if ($seniorUserId > 0) {
            $seniorMessage = $cancelledBy === 'pal'
                ? 'A pal cancelled your service.'
                : 'Your service was cancelled.';
            $notification->create($seniorUserId, 'Service Cancelled', $seniorMessage);
        }
    }
}
