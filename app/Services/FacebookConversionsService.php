<?php
namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class FacebookConversionsService
{
    protected Client $http;

    public function __construct()
    {
        $this->http = new Client(['timeout' => 10]);
    }

    /**
     * Send a Purchase event to Facebook Conversions API for one pixel.
     *
     * @param string $pixelId
     * @param array $payload Example keys: value, currency, event_id, email, phone, client_ip, user_agent, content_ids
     * @return array|null
     */
    public function sendPurchaseEvent(string $pixelId, array $payload): ?array
    {
        $accessToken = env('FB_CAPI_ACCESS_TOKEN');
        if (! $accessToken) {
            Log::warning('FacebookConversionsService: missing FB_CAPI_ACCESS_TOKEN');
            return null;
        }

        $body = [
            'data' => [[
                'event_name' => 'Purchase',
                'event_time' => time(),
                'event_id' => $payload['event_id'] ?? null,
                'event_source_url' => $payload['event_source_url'] ?? null,
                'user_data' => [
                    'em' => $payload['email'] ? hash('sha256', strtolower(trim($payload['email']))) : null,
                    'ph' => $payload['phone'] ? hash('sha256', preg_replace('/[^0-9]/','',$payload['phone'])) : null,
                    'client_ip_address' => $payload['client_ip'] ?? null,
                    'client_user_agent' => $payload['user_agent'] ?? null,
                ],
                'custom_data' => [
                    'currency' => $payload['currency'] ?? 'BRL',
                    'value' => isset($payload['value']) ? (float) $payload['value'] : 0.0,
                    'content_ids' => $payload['content_ids'] ?? null,
                    'content_type' => $payload['content_type'] ?? 'product',
                ],
            ]],
            'access_token' => $accessToken,
        ];

        $url = "https://graph.facebook.com/v17.0/{$pixelId}/events";

        try {
            $resp = $this->http->post($url, [
                'json' => $body,
            ]);

            $data = json_decode((string) $resp->getBody(), true);
            Log::info('FB CAPI response', ['pixel' => $pixelId, 'response' => $data]);
            return $data;
        } catch (\Throwable $e) {
            Log::error('FB CAPI error', ['pixel' => $pixelId, 'error' => $e->getMessage()]);
            return null;
        }
    }
}
