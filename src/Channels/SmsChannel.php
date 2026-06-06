<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaNotify\Channels;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * SMS notification channel backed by Africa's Talking API.
 *
 * Docs: https://developers.africastalking.com/docs/sms/sending
 */
final class SmsChannel
{
    private const LIVE_ENDPOINT = 'https://api.africastalking.com/version1/messaging';
    private const SANDBOX_ENDPOINT = 'https://api.sandbox.africastalking.com/version1/messaging';

    private Client $http;
    private string $endpoint;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $username,
        private readonly string $senderId,
        private readonly bool $sandbox = false,
        ?Client $http = null,
    ) {
        if (empty($apiKey)) {
            throw new InvalidArgumentException('Africa\'s Talking API key must not be empty.');
        }

        if (empty($username)) {
            throw new InvalidArgumentException('Africa\'s Talking username must not be empty.');
        }

        $this->endpoint = $sandbox ? self::SANDBOX_ENDPOINT : self::LIVE_ENDPOINT;
        $this->http = $http ?? new Client(['timeout' => 15, 'connect_timeout' => 5]);
    }

    /**
     * Send the notification via SMS.
     *
     * The notifiable must expose a routeNotificationForMpesaSms() method
     * returning a phone number in E.164 format, e.g. +254712345678.
     *
     * @throws RuntimeException when the API returns an error status.
     */
    public function send(mixed $notifiable, Notification $notification): array
    {
        if (! method_exists($notification, 'toSms')) {
            return [];
        }

        $message = $notification->toSms($notifiable);

        if (empty($message)) {
            return [];
        }

        $phone = method_exists($notifiable, 'routeNotificationForMpesaSms')
            ? $notifiable->routeNotificationForMpesaSms($notification)
            : ($notifiable->phone_number ?? null);

        if (empty($phone)) {
            Log::warning('[MpesaNotify] SMS channel: no phone number for notifiable.', [
                'notifiable' => get_class($notifiable),
                'id'         => $notifiable->getKey() ?? null,
            ]);

            return [];
        }

        return $this->dispatch($phone, $message);
    }

    /**
     * Dispatch an SMS via Africa's Talking REST API.
     *
     * @param  string  $to  E.164 phone number(s), comma-separated for bulk.
     * @param  string  $message  Plain-text message body (max 160 chars per segment).
     * @return array  Parsed API response data.
     *
     * @throws RuntimeException
     */
    public function dispatch(string $to, string $message): array
    {
        $payload = [
            'username' => $this->username,
            'to'       => $to,
            'message'  => $message,
        ];

        if (! empty($this->senderId)) {
            $payload['from'] = $this->senderId;
        }

        try {
            $response = $this->http->post($this->endpoint, [
                'headers'     => [
                    'apiKey'       => $this->apiKey,
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $payload,
            ]);

            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            $recipients = $body['SMSMessageData']['Recipients'] ?? [];

            foreach ($recipients as $recipient) {
                if (! in_array($recipient['status'], ['Success', 'MessageSent'], true)) {
                    Log::warning('[MpesaNotify] Africa\'s Talking SMS delivery issue.', [
                        'recipient' => $recipient,
                    ]);
                }
            }

            Log::info('[MpesaNotify] SMS sent via Africa\'s Talking.', [
                'to'      => $to,
                'message' => substr($message, 0, 80),
                'count'   => count($recipients),
            ]);

            return $body;
        } catch (GuzzleException $e) {
            Log::error('[MpesaNotify] Africa\'s Talking API request failed.', [
                'error' => $e->getMessage(),
                'to'    => $to,
            ]);

            throw new RuntimeException(
                'Africa\'s Talking SMS dispatch failed: '.$e->getMessage(),
                previous: $e,
            );
        }
    }
}
