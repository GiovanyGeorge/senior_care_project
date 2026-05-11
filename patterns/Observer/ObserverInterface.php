<?php
declare(strict_types=1);

require_once __DIR__ . '/DomainEvent.php';

interface ObserverInterface
{
    public function handle(DomainEvent $event): void;
}
