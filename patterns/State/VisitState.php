<?php
declare(strict_types=1);

/**
 * State pattern: each visit status is a state object with allowed transitions.
 */
interface VisitState
{
    /** Canonical DB status string, e.g. Pending, Accepted */
    public function getName(): string;

    /** @return list<string> */
    public function allowedNextStatuses(): array;
}
