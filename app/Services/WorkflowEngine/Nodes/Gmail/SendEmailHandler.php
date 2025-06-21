<?php

namespace App\Services\WorkflowEngine\Nodes\Gmail;

use App\Models\ConnectedAccount;
use App\Services\WorkflowEngine\Nodes\BaseNodeHandler;
use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Illuminate\Support\Facades\Log;

class SendEmailHandler extends BaseNodeHandler
{
    protected static string $type = 'gmail.sendEmail';

    protected function process(array $node, array $context = []): array
    {
        try {
            // Get the Gmail account for the workflow's user
            $account = ConnectedAccount::where('user_id', $context['user_id'])
                ->where('provider', 'google')
                ->first();

            if (!$account) {
                throw new \Exception('No connected Gmail account found');
            }

            // Get node configuration
            $to = $this->getNodeData($node, 'to', $context);
            $subject = $this->getNodeData($node, 'subject', $context, '');
            $body = $this->getNodeData($node, 'body', $context, '');
            $from = $account->email;

            // Validate required fields
            if (empty($to)) {
                throw new \Exception('Recipient email address is required');
            }

            // Create Gmail client
            $client = $this->getGmailClient($account);
            $service = new Gmail($client);

            // Create message
            $message = $this->createMessage($from, $to, $subject, $body);
            
            // Send message
            $sentMessage = $service->users_messages->send('me', $message);

            return [
                'success' => true,
                'data' => [
                    'message' => 'Email sent successfully',
                    'messageId' => $sentMessage->getId(),
                    'threadId' => $sentMessage->getThreadId(),
                ],
                'output' => [
                    'message' => 'Email sent successfully',
                    'messageId' => $sentMessage->getId(),
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Gmail SendEmail error: ' . $e->getMessage(), [
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
     * Create a Gmail client with the given account
     */
    protected function getGmailClient(ConnectedAccount $account): Client
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setAccessToken([
            'access_token' => $account->access_token,
            'refresh_token' => $account->refresh_token,
            'expires_in' => $account->expires_at ? $account->expires_at->diffInSeconds(now()) : 0,
        ]);

        // Refresh token if expired
        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($account->refresh_token);
            
            // Save the new access token
            $token = $client->getAccessToken();
            $account->access_token = $token['access_token'];
            $account->expires_at = now()->addSeconds($token['expires_in']);
            $account->save();
        }

        return $client;
    }

    /**
     * Create a base64 encoded email message
     */
    protected function createMessage(string $from, string $to, string $subject, string $messageText): Message
    {
        $boundary = uniqid(rand(), true);
        $rawMessage = "";
        
        // Headers
        $rawMessage .= "From: {$from}\r\n";
        $rawMessage .= "To: {$to}\r\n";
        $rawMessage .= "Subject: {$subject}\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";
        $rawMessage .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $rawMessage .= "\r\n";
        
        // Body
        $rawMessage .= $messageText;
        
        // Encode message
        $encodedMessage = base64_encode($rawMessage);
        $encodedMessage = str_replace(['+', '/', '='], ['-', '_', ''], $encodedMessage); // URL safe encoding
        
        $message = new Message();
        $message->setRaw($encodedMessage);
        
        return $message;
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
