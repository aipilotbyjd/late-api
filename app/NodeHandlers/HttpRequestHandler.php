<?php

namespace App\NodeHandlers;

use Illuminate\Support\Facades\Http;
use App\Services\WorkflowEngine\Context;

class HttpRequestHandler extends BaseNodeHandler
{
    public function handle(array $config, Context $context): Context
    {
        $response = Http::withHeaders($config['headers'] ?? [])
            ->send($config['method'] ?? 'GET', $config['url'], [
                'json' => $config['body'] ?? []
            ]);

        return $context->withData(['response' => $response->json()]);
    }
}
