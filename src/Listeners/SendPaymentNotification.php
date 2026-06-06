<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaNotify\Listeners;

use FelixMuhoro\MpesaNotify\MpesaNotificationManager;
use FelixMuhoro\MpesaNotify\Notifications\PaymentFailedNotification;
use FelixMuhoro\MpesaNotify\Notifications\PaymentSuccessfulNotification;
use FelixMuhoro\MpesaNotify\Notifications\StkPushInitiatedNotification;
use FelixMuhoro\MpesaNotify\ValueObjects\PaymentData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notifiable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Listens to core M-Pesa payment events and dispatches the appropriate
 * notification to the resolved notifiable model.
 *
 * Automatically queued when the application has a queue connection configured.
 */
final class SendPaymentNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /** Queue connection override (null = app default). */
    public ?string $connection = null;

    /** Queue name override. */
    public string $queue = 'notifications';

    /** Max delivery attempts before the job is considered failed. */
    public int $tries = 3;

    /** Seconds to wait before retrying. */
    public int $backoff = 30;

    public function __construct(
        private readonly MpesaNotificationManager $manager,
    ) {}

    /**
     * Handle any of the three M-Pesa core package events.
     *
     * The core package fires events that carry either a $payment array or a
     * public $data / $payload property — we normalise them all here.
     */
    public function handle(object $event): void
    {
        $rawData = $this->extractPayload($event);

        if (empty($rawData)) {
            Log::warning('[MpesaNotify] Received empty payload from event.', [
                'event' => get_class($event),
            ]);
            return;
        }

        $paymentData = PaymentData::fromArray($rawData);

        $notifiable = $this->resolveNotifiable($event, $paymentData);

        if ($notifiable === null) {
            Log::warning('[MpesaNotify] Could not resolve a notifiable for payment.', [
                'transaction_id' => $paymentData->transactionId,
            ]);
            return;
        }

        $notification = match ($paymentData->eventType) {
            MpesaNotificationManager::EVENT_PAYMENT_SUCCESSFUL => new PaymentSuccessfulNotification($paymentData),
            MpesaNotificationManager::EVENT_PAYMENT_FAILED     => new PaymentFailedNotification($paymentData),
            MpesaNotificationManager::EVENT_STK_PUSH_INITIATED => new StkPushInitiatedNotification($paymentData),
            default => new PaymentSuccessfulNotification($paymentData),
        };

        $notifiable->notify($notification);

        Log::info('[MpesaNotify] Notification dispatched.', [
            'type'           => get_class($notification),
            'transaction_id' => $paymentData->transactionId,
        ]);
    }

    /**
     * Handle a failed job.
     */
    public function failed(object $event, Throwable $e): void
    {
        Log::error('[MpesaNotify] SendPaymentNotification job failed.', [
            'event' => get_class($event),
            'error' => $e->getMessage(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Extract a normalised array payload from the incoming event object.
     * The core package may store data under different property names.
     */
    private function extractPayload(object $event): array
    {
        foreach (['data', 'payload', 'payment', 'transaction'] as $prop) {
            if (isset($event->{$prop}) && is_array($event->{$prop})) {
                return $event->{$prop};
            }
        }

        // Some core events expose a toArray() method.
        if (method_exists($event, 'toArray')) {
            return $event->toArray();
        }

        // Last resort: cast the event to array (works for simple stdClass events).
        return (array) $event;
    }

    /**
     * Attempt to resolve a notifiable (Eloquent model or anonymous notifiable)
     * from the event. Falls back to an anonymous notifiable built from the
     * payment data so that e-mail / SMS still works without a user lookup.
     */
    private function resolveNotifiable(object $event, PaymentData $payment): mixed
    {
        // If the event carries a pre-resolved notifiable, use it directly.
        if (isset($event->notifiable)) {
            return $event->notifiable;
        }

        // Try to look up the user by phone via a configured model.
        $modelClass = config('mpesa-notify.user_model');

        if ($modelClass && class_exists($modelClass)) {
            $phoneColumn = config('mpesa-notify.user_phone_column', 'phone_number');
            $user = $modelClass::where($phoneColumn, $payment->phone)->first();

            if ($user !== null) {
                return $user;
            }
        }

        // Fall back to an anonymous notifiable so channels still receive data.
        return $this->makeAnonymousNotifiable($payment);
    }

    /**
     * Build a minimal anonymous notifiable from payment data.
     * This is useful when no User model is configured or matched.
     */
    private function makeAnonymousNotifiable(PaymentData $payment): object
    {
        return new class($payment) {
            use Notifiable;

            public function __construct(private readonly PaymentData $payment) {}

            public function routeNotificationForMail(): string
            {
                return $this->payment->meta['email'] ?? '';
            }

            public function routeNotificationForMpesaSms(): string
            {
                return $this->payment->phone;
            }

            public function routeNotificationForSlack(): ?string
            {
                return config('mpesa-notify.slack.webhook_url');
            }

            public function getKey(): string
            {
                return $this->payment->transactionId;
            }

            public function displayName(): string
            {
                return $this->payment->payerName;
            }
        };
    }
}
