<?php
declare(strict_types=1);

require_once __DIR__ . '/AbstractVisitState.php';

final class TerminalVisitState extends AbstractVisitState
{
    public function __construct(string $name)
    {
        parent::__construct($name, []);
    }
}
