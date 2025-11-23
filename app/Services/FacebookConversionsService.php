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

        // Clean and hash email
        $email = isset($payload['email']) ? strtolower(trim($payload['email'])) : null;
        $hashedEmail = ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) ? hash('sha256', $email) : null;

        // Clean and hash phone
        $phone = isset($payload['phone']) ? preg_replace('/[^0-9]/', '', $payload['phone']) : null;
        $hashedPhone = ($phone && strlen($phone) >= 10) ? hash('sha256', $phone) : null;

        // ⚠️ CRITICAL: Facebook requires at least one identifier (email or phone)
        if (!$hashedEmail && !$hashedPhone) {
            Log::warning('FacebookConversionsService: No valid email or phone for purchase event', [
                'pixel' => $pixelId,
                'event_id' => $payload['event_id'] ?? null,
                'has_email' => !empty($email),
                'email_valid' => !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL),
                'has_phone' => !empty($phone),
                'phone_length' => strlen($phone ?? ''),
            ]);
            return null;
        }

        // Clean and validate content_ids
        $contentIds = isset($payload['content_ids']) ? array_filter($payload['content_ids']) : null;

        $userData = [
            'client_ip_address' => $payload['client_ip'] ?? null,
            'client_user_agent' => $payload['user_agent'] ?? null,
        ];

        // Only add email/phone if they're valid
        if ($hashedEmail) {
            $userData['em'] = $hashedEmail;
        }
        if ($hashedPhone) {
            $userData['ph'] = $hashedPhone;
        }

        $customData = [
            'currency' => strtoupper($payload['currency'] ?? 'BRL'),
            'value' => isset($payload['value']) ? (float) $payload['value'] : 0.0,
            'content_type' => $payload['content_type'] ?? 'product',
        ];

        // Only add content_ids if they exist
        if (!empty($contentIds)) {
            $customData['content_ids'] = array_values($contentIds);
        }

        $body = [
            'data' => [[
                'event_name' => 'Purchase',
                'event_time' => time(),
                'event_id' => $payload['event_id'] ?? null,
                'event_source_url' => $payload['event_source_url'] ?? url('/'),
                'user_data' => $userData,
                'custom_data' => $customData,
            ]],
            'access_token' => $accessToken,
        ];

        $url = "https://graph.facebook.com/v19.0/{$pixelId}/events";

        try {
            Log::info('FB CAPI sending purchase event', [
                'pixel' => $pixelId,
                'event_id' => $payload['event_id'] ?? null,
                'value' => $customData['value'],
                'currency' => $customData['currency'],
                'has_email' => !empty($hashedEmail),
                'has_phone' => !empty($hashedPhone),
                'content_ids' => !empty($contentIds) ? count($contentIds) : 0,
            ]);

            $resp = $this->http->post($url, [
                'json' => $body,
            ]);

            $data = json_decode((string) $resp->getBody(), true);
            Log::info('FB CAPI response', ['pixel' => $pixelId, 'response' => $data, 'status' => $resp->getStatusCode()]);
            return $data;
        } catch (\Throwable $e) {
            Log::error('FB CAPI error', ['pixel' => $pixelId, 'error' => $e->getMessage(), 'payload' => $body['data'][0] ?? null]);
            return null;
        }
    }
}
