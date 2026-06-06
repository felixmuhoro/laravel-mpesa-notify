<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaNotify\Channels;

use FelixMuhoro\MpesaNotify\MpesaNotificationManager;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Custom Laravel notification channel that fans out to configured sub-channels
 * (mail, mpesa-sms, slack, database) based on the package configuration and
 * the event type carried by the notification.
 *
 * Register this channel in your notification's via() array as 'mpesa-notify'.
 */
final class MpesaNotificationChannel
{
    public function __construct(
        private readonly MpesaNotificationManager $manager,
        private readonly SmsChannel $smsChannel,
    ) {}

    /**
     * Send the notification through all configured channels.
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        $eventType = method_exists($notification, 'eventType')
            ? $notification->eventType()
            : 'payment_successful';

        $channels = $this->manager->channelsFor($eventType);

        foreach ($channels as $channel) {
            try {
                $this->dispatchToChannel($channel, $notifiable, $notification);
            } catch (\Throwable $e) {
                Log::error('[MpesaNotify] Failed to send via channel.', [
                    'channel'  => $channel,
                    'event'    => $eventType,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }

    private function dispatchToChannel(string $channel, mixed $notifiable, Notification $notification): void
    {
        match ($channel) {
            'mpesa-sms' => $this->smsChannel->send($notifiable, $notification),
            default     => $notifiable->notify($notification),
        };
    }
}
