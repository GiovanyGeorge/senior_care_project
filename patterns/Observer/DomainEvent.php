<?php
declare(strict_types=1);

final class DomainEvent
{
    public function __construct(
        public string $name,
        public array $payload = []
    ) {
    }
}
