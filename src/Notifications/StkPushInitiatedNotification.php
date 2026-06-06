<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaNotify\Notifications;

use FelixMuhoro\MpesaNotify\MpesaNotificationManager;
use FelixMuhoro\MpesaNotify\ValueObjects\PaymentData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Sent immediately after an STK Push has been dispatched to the customer's
 * handset, informing them to check their phone for the M-Pesa PIN prompt.
 */
final class StkPushInitiatedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PaymentData $payment,
    ) {}

    // -------------------------------------------------------------------------
    // Channel routing
    // -------------------------------------------------------------------------

    /**
     * @return string[]
     */
    public function via(mixed $notifiable): array
    {
        return app(MpesaNotificationManager::class)
            ->channelsFor(MpesaNotificationManager::EVENT_STK_PUSH_INITIATED);
    }

    public function eventType(): string
    {
        return MpesaNotificationManager::EVENT_STK_PUSH_INITIATED;
    }

    // -------------------------------------------------------------------------
    // Channel payloads
    // -------------------------------------------------------------------------

    public function toMail(mixed $notifiable): MailMessage
    {
        $name   = method_exists($notifiable, 'displayName') ? $notifiable->displayName() : 'Customer';
        $amount = $this->payment->formattedAmount();

        return (new MailMessage)
            ->subject('M-Pesa Payment Request – '.$amount)
            ->greeting('Hello '.$name.',')
            ->line('We have sent an M-Pesa payment request of **'.$amount.'** to your phone ('.substr($this->payment->phone, -4).').')
            ->line('Please enter your M-Pesa PIN when prompted to complete the payment.')
            ->line('Reference: '.$this->payment->description)
            ->salutation('Thank you.');
    }

    public function toSms(mixed $notifiable): string
    {
        return sprintf(
            'M-Pesa request of %s sent to your phone. Enter your PIN to complete. Ref: %s.',
            $this->payment->formattedAmount(),
            $this->payment->description,
        );
    }

    public function toSlack(mixed $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->info()
            ->content('*STK Push Initiated* :iphone:')
            ->attachment(function ($attachment) {
                $attachment
                    ->title('Awaiting customer PIN entry')
                    ->fields([
                        'Amount'      => $this->payment->formattedAmount(),
                        'Phone'       => $this->payment->phone,
                        'Description' => $this->payment->description,
                        'Initiated'   => $this->payment->transactedAt->format('d M Y H:i'),
                    ]);
            });
    }

    public function toDatabase(mixed $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'type'           => 'stk_push_initiated',
            'transaction_id' => $this->payment->transactionId,
            'amount'         => $this->payment->amount,
            'currency'       => $this->payment->currency,
            'phone'          => $this->payment->phone,
            'description'    => $this->payment->description,
            'transacted_at'  => $this->payment->transactedAt->format('Y-m-d H:i:s'),
            'reference_id'   => $this->payment->referenceId,
        ];
    }
}
