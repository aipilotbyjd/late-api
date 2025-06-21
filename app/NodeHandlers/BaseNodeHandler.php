<?php

namespace App\NodeHandlers;

use App\Services\WorkflowEngine\Context;

abstract class BaseNodeHandler
{
    abstract public function handle(array $config, Context $context): Context;

    public function retries(): int
    {
        return 0;
    }
    public function timeout(): int
    {
        return 0;
    }
}
