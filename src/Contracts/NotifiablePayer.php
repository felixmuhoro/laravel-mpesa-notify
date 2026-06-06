<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaNotify\Contracts;

/**
 * Interface that Eloquent models (e.g. User, Customer) must implement
 * so the notification system can correctly address each channel.
 *
 * Usage:
 *   class User extends Authenticatable implements NotifiablePayer { ... }
 */
interface NotifiablePayer
{
    /**
     * Return the phone number used to route M-Pesa SMS notifications.
     * Must be in E.164 format, e.g. +254712345678.
     */
    public function routeNotificationForMpesaSms(): string;

    /**
     * Return the e-mail address used to route mail notifications.
     * Return null to suppress mail delivery for this notifiable.
     */
    public function routeNotificationForMail(): ?string;

    /**
     * Return the Slack webhook URL for this notifiable, or null to skip Slack.
     * Set a package-wide webhook in config/mpesa-notify.php instead for a
     * single webhook shared by all users.
     */
    public function routeNotificationForSlack(): ?string;

    /**
     * Human-readable display name used inside notification content.
     */
    public function displayName(): string;
}
