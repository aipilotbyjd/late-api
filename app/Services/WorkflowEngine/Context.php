<?php

namespace App\Services\WorkflowEngine;

class Context
{
    public function __construct(
        public array $data = [],
        public array $meta = [],
        public array $logs = []
    ) {}

    public function withData(array $new): static {
        return new static([...$this->data, ...$new], $this->meta, $this->logs);
    }
}
