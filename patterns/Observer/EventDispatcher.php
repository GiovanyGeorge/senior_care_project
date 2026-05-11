<?php
declare(strict_types=1);

require_once __DIR__ . '/ObserverInterface.php';
require_once __DIR__ . '/DomainEvent.php';

/**
 * Observer pattern: central subject that notifies subscribed observers.
 */
final class EventDispatcher
{
    private static ?self $instance = null;

    /** @var list<ObserverInterface> */
    private array $observers = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function subscribe(ObserverInterface $observer): void
    {
        $this->observers[] = $observer;
    }

    public function dispatch(DomainEvent $event): void
    {
        foreach ($this->observers as $observer) {
            $observer->handle($event);
        }
    }

    /** @internal testing / rare reset */
    public static function resetForTesting(): void
    {
        self::$instance = null;
    }
}
