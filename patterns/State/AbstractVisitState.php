<?php
declare(strict_types=1);

require_once __DIR__ . '/VisitState.php';

abstract class AbstractVisitState implements VisitState
{
    public function __construct(
        protected readonly string $name,
        /** @var list<string> */
        protected readonly array $allowedNext
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function allowedNextStatuses(): array
    {
        return $this->allowedNext;
    }
}
