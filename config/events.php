<?php
declare(strict_types=1);

require_once __DIR__ . '/../patterns/Observer/DomainEvent.php';
require_once __DIR__ . '/../patterns/Observer/ObserverInterface.php';
require_once __DIR__ . '/../patterns/Observer/AppEvents.php';
require_once __DIR__ . '/../patterns/Observer/EventDispatcher.php';
require_once __DIR__ . '/../patterns/Observer/NotificationObserver.php';

if (!defined('CARENEST_EVENTS_BOOTSTRAPPED')) {
    define('CARENEST_EVENTS_BOOTSTRAPPED', true);
    EventDispatcher::getInstance()->subscribe(new NotificationObserver());
}
