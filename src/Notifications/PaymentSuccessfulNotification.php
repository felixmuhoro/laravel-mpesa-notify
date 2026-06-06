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
 * Sent when M-Pesa confirms a completed payment (STK callback ResultCode 0,
 * or a C2B payment validated and confirmed).
 */
final class PaymentSuccessfulNotification extends Notification implements ShouldQueue
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
            ->channelsFor(MpesaNotificationManager::EVENT_PAYMENT_SUCCESSFUL);
    }

    public function eventType(): string
    {
        return MpesaNotificationManager::EVENT_PAYMENT_SUCCESSFUL;
    }

    // -------------------------------------------------------------------------
    // Channel payloads
    // -------------------------------------------------------------------------

    public function toMail(mixed $notifiable): MailMessage
    {
        $name   = method_exists($notifiable, 'displayName') ? $notifiable->displayName() : 'Customer';
        $amount = $this->payment->formattedAmount();
        $txId   = $this->payment->transactionId;
        $date   = $this->payment->transactedAt->format('d M Y, H:i');

        return (new MailMessage)
            ->subject('Payment Received – '.$amount)
            ->markdown('mpesa-notify::mail.payment-successful', [
                'name'        => $name,
                'amount'      => $amount,
                'txId'        => $txId,
                'description' => $this->payment->description,
                'date'        => $date,
                'payment'     => $this->payment,
            ]);
    }

    /**
     * Returns a plain-text SMS body (max 160 chars for a single segment).
     */
    public function toSms(mixed $notifiable): string
    {
        return sprintf(
            'M-Pesa payment of %s received. Ref: %s. Date: %s. Thank you!',
            $this->payment->formattedAmount(),
            $this->payment->transactionId,
            $this->payment->transactedAt->format('d/m/Y H:i'),
        );
    }

    public function toSlack(mixed $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->success()
            ->content('*Payment Received* :white_check_mark:')
            ->attachment(function ($attachment) {
                $attachment
                    ->title('Transaction Details')
                    ->fields([
                        'Amount'     => $this->payment->formattedAmount(),
                        'Reference'  => $this->payment->transactionId,
                        'Phone'      => $this->payment->phone,
                        'Payer'      => $this->payment->payerName,
                        'Date'       => $this->payment->transactedAt->format('d M Y H:i'),
                    ]);
            });
    }

    /**
     * Persisted to the "notifications" table by Laravel's database channel.
     */
    public function toDatabase(mixed $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'type'           => 'payment_successful',
            'transaction_id' => $this->payment->transactionId,
            'amount'         => $this->payment->amount,
            'currency'       => $this->payment->currency,
            'phone'          => $this->payment->phone,
            'payer_name'     => $this->payment->payerName,
            'description'    => $this->payment->description,
            'transacted_at'  => $this->payment->transactedAt->format('Y-m-d H:i:s'),
            'reference_id'   => $this->payment->referenceId,
        ];
    }
}
