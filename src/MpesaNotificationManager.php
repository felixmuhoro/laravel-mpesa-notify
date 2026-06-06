<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaNotify;

use InvalidArgumentException;

/**
 * Determines which notification channels to activate per M-Pesa event type,
 * driven entirely by the values in config/mpesa-notify.php.
 *
 * Channel identifiers returned here map to standard Laravel channel names
 * ("mail", "database", "slack") plus the custom "mpesa-sms" channel.
 */
final class MpesaNotificationManager
{
    /** Known event type identifiers. */
    public const EVENT_PAYMENT_SUCCESSFUL  = 'payment_successful';
    public const EVENT_PAYMENT_FAILED      = 'payment_failed';
    public const EVENT_STK_PUSH_INITIATED  = 'stk_push_initiated';

    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Return the list of active channel identifiers for a given event type.
     *
     * The list is built by intersecting the globally-enabled channels with the
     * per-event overrides (if any). If an event has no explicit override the
     * global list is used as-is.
     *
     * @param  string  $eventType  One of the EVENT_* constants.
     * @return string[]  e.g. ['mail', 'mpesa-sms', 'database']
     */
    public function channelsFor(string $eventType): array
    {
        $globalEnabled = $this->config['enabled_channels'] ?? ['mail', 'database'];

        $perEvent = $this->config['channels_per_event'][$eventType] ?? null;

        $active = $perEvent !== null ? $perEvent : $globalEnabled;

        // Filter to channels that are actually configured/credentialed.
        return array_values(array_filter($active, $this->isChannelUsable(...)));
    }

    /**
     * Check whether a specific channel is enabled in config.
     */
    public function isChannelEnabled(string $channel): bool
    {
        return in_array($channel, $this->config['enabled_channels'] ?? [], true);
    }

    /**
     * Return all supported event types.
     *
     * @return string[]
     */
    public function supportedEvents(): array
    {
        return [
            self::EVENT_PAYMENT_SUCCESSFUL,
            self::EVENT_PAYMENT_FAILED,
            self::EVENT_STK_PUSH_INITIATED,
        ];
    }

    /**
     * Check that a channel has the minimum required configuration present.
     */
    private function isChannelUsable(string $channel): bool
    {
        return match ($channel) {
            'mpesa-sms' => ! empty($this->config['africas_talking']['api_key'])
                && ! empty($this->config['africas_talking']['username']),

            'slack'     => ! empty($this->config['slack']['webhook_url'])
                || ! empty(env('LOG_SLACK_WEBHOOK_URL')),

            'mail'      => true,   // always available in any Laravel app
            'database'  => true,   // always available
            default     => false,
        };
    }
}
