<?php

namespace App\Services\WorkflowEngine;

class WorkflowGraph
{
    public function __construct(
        public array $nodes,
        public array $connections
    ) {}

    public function findStartNodes(): array
    {
        $targets = collect($this->connections)->pluck('target')->unique();
        return collect($this->nodes)
            ->pluck('id')
            ->filter(fn($id) => !$targets->contains($id))
            ->values()
            ->all();
    }
}
