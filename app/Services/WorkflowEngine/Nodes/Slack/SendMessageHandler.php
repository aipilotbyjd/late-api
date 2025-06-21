<?php

namespace App\Services\WorkflowEngine\Nodes\Slack;

use App\Models\ConnectedAccount;
use App\Services\WorkflowEngine\Nodes\BaseNodeHandler;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendMessageHandler extends BaseNodeHandler
{
    protected static string $type = 'slack.sendMessage';

    protected function process(array $node, array $context = []): array
    {
        try {
            // Get the Slack account for the workflow's user
            $account = ConnectedAccount::where('user_id', $context['user_id'])
                ->where('provider', 'slack')
                ->first();

            if (!$account) {
                throw new \Exception('No connected Slack account found');
            }

            // Get node configuration
            $channel = $this->getNodeData($node, 'channel', $context);
            $message = $this->getNodeData($node, 'message', $context);

            // Send message to Slack
            $response = Http::withToken($account->access_token)
                ->post('https://slack.com/api/chat.postMessage', [
                    'channel' => $channel,
                    'text' => $message,
                    'as_user' => true,
                ]);

            $responseData = $response->json();

            if (!$response->ok() || !($responseData['ok'] ?? false)) {
                throw new \Exception($responseData['error'] ?? 'Failed to send Slack message');
            }

            return [
                'success' => true,
                'data' => $responseData,
                'output' => [
                    'message' => 'Message sent successfully',
                    'channel' => $channel,
                    'ts' => $responseData['ts'] ?? null,
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Slack SendMessage error: ' . $e->getMessage(), [
                'node' => $node,
                'context' => $context,
                'exception' => $e
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'output' => []
            ];
        }
    }

    /**
     * Helper to get node data with variable substitution
     */
    protected function getNodeData(array $node, string $key, array $context, $default = null)
    {
        $value = $node['data'][$key] ?? $default;
        
        // Simple variable substitution: {{variable}}
        if (is_string($value) && preg_match_all('/\{\{(.+?)\}\}/', $value, $matches)) {
            foreach ($matches[1] as $i => $var) {
                $var = trim($var);
                if (array_key_exists($var, $context)) {
                    $value = str_replace($matches[0][$i], $context[$var], $value);
                }
            }
        }
        
        return $value;
    }
}
