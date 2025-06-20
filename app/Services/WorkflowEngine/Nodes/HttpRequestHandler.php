<?php

namespace App\Services\WorkflowEngine\Nodes;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HttpRequestHandler extends BaseNodeHandler
{
    protected static string $type = 'http-request';

    protected function process(array $node, array $context = []): array
    {
        $config = $node['config'] ?? [];
        $method = strtoupper($config['method'] ?? 'GET');
        $url = $this->replacePlaceholders($config['url'] ?? '', $context);
        $headers = $this->prepareHeaders($config['headers'] ?? [], $context);
        $body = $this->prepareBody($config['body'] ?? null, $context);
        $options = [];

        // Prepare request options
        if (!empty($headers)) {
            $options['headers'] = $headers;
        }

        // Add body for non-GET requests
        if ($method !== 'GET' && !empty($body)) {
            $options[$this->getBodyType($config)] = $body;
        }

        // Add authentication if configured
        if (isset($config['auth'])) {
            $auth = $config['auth'];
            if (isset($auth['type']) && $auth['type'] === 'basic') {
                $options['auth'] = [
                    $this->replacePlaceholders($auth['username'] ?? '', $context),
                    $this->replacePlaceholders($auth['password'] ?? '', $context)
                ];
            } elseif (isset($auth['type']) && $auth['type'] === 'bearer') {
                $options['headers']['Authorization'] = 'Bearer ' . $this->replacePlaceholders($auth['token'] ?? '', $context);
            }
        }

        // Make the request
        $httpResponse = Http::withOptions([
            'verify' => $config['verify_ssl'] ?? true,
            'timeout' => $config['timeout'] ?? 30,
        ])->send($method, $url, $options);

        return [
            'status' => $httpResponse->status(),
            'headers' => $httpResponse->headers(),
            'body' => $httpResponse->json() ?? $httpResponse->body(),
        ];
    }
    
    protected function replacePlaceholders(string $text, array $context): string
    {
        return preg_replace_callback('/\{\{(.+?)\}\}/', function ($matches) use ($context) {
            $key = trim($matches[1]);
            return data_get($context, $key, $matches[0]);
        }, $text);
    }
    
    protected function prepareHeaders(array $headers, array $context): array
    {
        $prepared = [];
        foreach ($headers as $key => $value) {
            $prepared[$key] = $this->replacePlaceholders($value, $context);
        }
        return $prepared;
    }
    
    /**
     * Determine the body type based on content type header
     */
    protected function getBodyType(array $config): string
    {
        $contentType = strtolower($config['headers']['Content-Type'] ?? $config['headers']['content-type'] ?? 'application/json');
        
        if (str_contains($contentType, 'form')) {
            return 'form_params';
        }
        
        if (str_contains($contentType, 'multipart')) {
            return 'multipart';
        }
        
        return 'json';
    }
    
    /**
     * Prepare the request body
     */
    protected function prepareBody($body, array $context)
    {
        if ($body === null) {
            return null;
        }
        
        if (is_string($body)) {
            return $this->replacePlaceholders($body, $context);
        }
        
        if (is_array($body)) {
            $result = [];
            foreach ($body as $key => $value) {
                $processedKey = is_string($key) ? $this->replacePlaceholders($key, $context) : $key;
                $processedValue = is_array($value) 
                    ? $this->prepareBody($value, $context)
                    : (is_string($value) ? $this->replacePlaceholders($value, $context) : $value);
                
                $result[$processedKey] = $processedValue;
            }
            return $result;
        }
        
        return $body;
    }
}
