<?php

namespace Tests\Unit\Nodes;

use App\Services\WorkflowEngine\Nodes\HttpRequestHandler;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpRequestHandlerTest extends TestCase
{
    /** @test */
    public function it_makes_http_requests()
    {
        Http::fake([
            'example.com/*' => Http::response(['success' => true], 200),
        ]);

        $handler = new HttpRequestHandler();
        
        $node = [
            'id' => 'test-node',
            'type' => 'http-request',
            'name' => 'Test Request',
            'config' => [
                'method' => 'GET',
                'url' => 'https://example.com/api/test',
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ],
        ];

        $context = [
            'execution_id' => 'test-execution',
            'some_data' => 'test',
        ];

        $result = $handler->handle($node, $context);

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['success']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/api/test' &&
                   $request->hasHeader('Accept', 'application/json');
        });
    }

    /** @test */
    public function it_handles_request_errors()
    {
        Http::fake([
            'example.com/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $handler = new HttpRequestHandler();
        
        $node = [
            'id' => 'test-node',
            'type' => 'http-request',
            'name' => 'Test Request',
            'config' => [
                'method' => 'GET',
                'url' => 'https://example.com/not-found',
            ],
        ];

        $result = $handler->handle($node, []);

        $this->assertEquals(404, $result['status']);
        $this->assertEquals('Not Found', $result['body']['error']);
    }

    /** @test */
    public function it_supports_basic_auth()
    {
        Http::fake([
            'example.com/*' => Http::response([], 200),
        ]);

        $handler = new HttpRequestHandler();
        
        $node = [
            'id' => 'test-node',
            'type' => 'http-request',
            'config' => [
                'method' => 'GET',
                'url' => 'https://example.com/api/secure',
                'auth' => [
                    'type' => 'basic',
                    'username' => 'user',
                    'password' => 'pass',
                ],
            ],
        ];

        $handler->handle($node, []);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Basic ' . base64_encode('user:pass'));
        });
    }

    /** @test */
    public function it_replaces_placeholders_in_url()
    {
        Http::fake();

        $handler = new HttpRequestHandler();
        
        $node = [
            'id' => 'test-node',
            'type' => 'http-request',
            'config' => [
                'method' => 'GET',
                'url' => 'https://example.com/api/{{ resource }}/{{ id }}',
            ],
        ];

        $context = [
            'resource' => 'users',
            'id' => 123,
        ];

        $handler->handle($node, $context);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/api/users/123';
        });
    }
}
