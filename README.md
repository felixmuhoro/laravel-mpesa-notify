# felixmuhoro/laravel-mpesa-notify

Multi-channel notifications for M-Pesa payment events in Laravel 10/11/12/13.

Listens to events from [`felixmuhoro/laravel-mpesa`](https://github.com/felixmuhoro/laravel-mpesa) and fans them out to **mail**, **SMS via Africa's Talking**, **Slack**, and the **database** — fully configurable per event type.

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | ^8.1 |
| Laravel | 10, 11, 12, or 13 |
| felixmuhoro/laravel-mpesa | ^1.2 |

---

## Installation

```bash
composer require felixmuhoro/laravel-mpesa-notify
```

Publish the config file:

```bash
php artisan vendor:publish --tag=mpesa-notify-config
```

Optionally publish migrations and views:

```bash
php artisan vendor:publish --tag=mpesa-notify-migrations
php artisan vendor:publish --tag=mpesa-notify-views
php artisan migrate
```

---

## Configuration

Add the following variables to your `.env`:

```env
# Channels to enable globally (comma-separated)
# Supported: mail, mpesa-sms, slack, database
MPESA_NOTIFY_CHANNELS=mail,mpesa-sms,database

# Africa's Talking credentials (required for mpesa-sms channel)
AT_API_KEY=your_africas_talking_api_key
AT_USERNAME=your_username
AT_SENDER_ID=MPESA
AT_SANDBOX=false

# Slack incoming webhook (required for slack channel)
MPESA_NOTIFY_SLACK_WEBHOOK=https://hooks.slack.com/services/...

# Optional: auto-resolve the notifiable by phone from your User model
MPESA_NOTIFY_USER_MODEL=App\Models\User
MPESA_NOTIFY_PHONE_COLUMN=phone_number

# Set to false to register event listeners manually
MPESA_NOTIFY_AUTO_LISTEN=true
```

Full config reference: [`config/mpesa-notify.php`](config/mpesa-notify.php).

---

## Channels

| Channel | Identifier | Driver |
|---|---|---|
| Email | `mail` | Laravel built-in mailer |
| SMS | `mpesa-sms` | Africa's Talking REST API |
| Slack | `slack` | Laravel Slack notification |
| Database | `database` | Laravel `notifications` table |

### Per-event channel override

```php
// config/mpesa-notify.php
'channels_per_event' => [
    'payment_successful' => ['mail', 'mpesa-sms', 'slack', 'database'],
    'payment_failed'     => ['mail', 'mpesa-sms', 'database'],
    'stk_push_initiated' => ['mpesa-sms'],
],
```

---

## Notifiable Model Setup

Your `User` (or any notifiable model) should implement the `NotifiablePayer` interface:

```php
use FelixMuhoro\MpesaNotify\Contracts\NotifiablePayer;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements NotifiablePayer
{
    use Notifiable;

    public function routeNotificationForMpesaSms(): string
    {
        return $this->phone_number; // E.164 format, e.g. +254712345678
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->email;
    }

    public function routeNotificationForSlack(): ?string
    {
        return null; // Use the global webhook from config
    }

    public function displayName(): string
    {
        return $this->name;
    }
}
```

---

## How It Works

1. `felixmuhoro/laravel-mpesa` fires one of three events:
   - `PaymentSuccessful`
   - `PaymentFailed`
   - `StkPushInitiated`

2. `SendPaymentNotification` listener picks it up (auto-wired by the service provider).

3. The listener:
   - Extracts the payment payload into a `PaymentData` value object.
   - Looks up the notifiable `User` by phone number (configurable).
   - Dispatches the appropriate `Notification` class.

4. The notification's `via()` method asks `MpesaNotificationManager` which channels are active for this event type.

5. Each channel's payload method is called (`toMail`, `toSms`, `toSlack`, `toDatabase`).

---

## Notifications

| Class | Event |
|---|---|
| `PaymentSuccessfulNotification` | `PaymentSuccessful` |
| `PaymentFailedNotification` | `PaymentFailed` |
| `StkPushInitiatedNotification` | `StkPushInitiated` |

All three implement `ShouldQueue` and are dispatched on the `notifications` queue.

---

## Manual Dispatch

You can send a notification manually without relying on events:

```php
use FelixMuhoro\MpesaNotify\Notifications\PaymentSuccessfulNotification;
use FelixMuhoro\MpesaNotify\ValueObjects\PaymentData;

$payment = PaymentData::fromArray([
    'transactionId' => 'QKA12BC3DE',
    'amount'        => 1500,
    'currency'      => 'KES',
    'phone'         => '+254712345678',
    'payerName'     => 'John Doe',
    'description'   => 'Order #123',
    'eventType'     => 'payment_successful',
]);

$user->notify(new PaymentSuccessfulNotification($payment));
```

---

## SMS via Africa's Talking

The `SmsChannel` hits the Africa's Talking v1 messaging API directly using Guzzle:

- **Live endpoint**: `https://api.africastalking.com/version1/messaging`
- **Sandbox endpoint**: `https://api.sandbox.africastalking.com/version1/messaging`

Set `AT_SANDBOX=true` and `AT_USERNAME=sandbox` to use the sandbox during development.

All recipients and statuses are logged to Laravel's default log channel. A `RuntimeException` is thrown on HTTP failure so the queued job retries automatically.

---

## Audit Log Table

The migration creates `mpesa_notification_logs` with the following columns:

| Column | Description |
|---|---|
| `transaction_id` | M-Pesa transaction reference |
| `event_type` | `payment_successful` / `payment_failed` / `stk_push_initiated` |
| `amount` / `currency` | Settlement amount |
| `phone` / `payer_name` | Payer details |
| `channels` | JSON array of active channels |
| `delivery_status` | JSON map of channel to status |
| `notifiable_*` | Polymorphic link to User / model |
| `reference_id` | Internal order reference |
| `meta` | Extra callback metadata |

---

## Testing

```bash
composer install
vendor/bin/phpunit
```

The test suite uses Orchestra Testbench and Guzzle's `MockHandler` — no real API calls are made.

---

## License

MIT
