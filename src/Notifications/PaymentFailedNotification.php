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
 * Sent when an M-Pesa STK push is cancelled, times out, or the ResultCode
 * is non-zero, indicating a failed payment.
 */
final class PaymentFailedNotification extends Notification implements ShouldQueue
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
            ->channelsFor(MpesaNotificationManager::EVENT_PAYMENT_FAILED);
    }

    public function eventType(): string
    {
        return MpesaNotificationManager::EVENT_PAYMENT_FAILED;
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
            ->subject('Payment Failed – '.$amount)
            ->markdown('mpesa-notify::mail.payment-failed', [
                'name'        => $name,
                'amount'      => $amount,
                'txId'        => $txId,
                'resultCode'  => $this->payment->resultCode,
                'description' => $this->payment->description,
                'date'        => $date,
                'payment'     => $this->payment,
            ]);
    }

    public function toSms(mixed $notifiable): string
    {
        return sprintf(
            'Your M-Pesa payment of %s was not completed (code: %d). Please retry or contact support.',
            $this->payment->formattedAmount(),
            $this->payment->resultCode ?? 0,
        );
    }

    public function toSlack(mixed $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->error()
            ->content('*Payment Failed* :x:')
            ->attachment(function ($attachment) {
                $attachment
                    ->title('Transaction Details')
                    ->fields([
                        'Amount'      => $this->payment->formattedAmount(),
                        'Phone'       => $this->payment->phone,
                        'Payer'       => $this->payment->payerName,
                        'Result Code' => (string) ($this->payment->resultCode ?? 'N/A'),
                        'Date'        => $this->payment->transactedAt->format('d M Y H:i'),
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
            'type'           => 'payment_failed',
            'transaction_id' => $this->payment->transactionId,
            'amount'         => $this->payment->amount,
            'currency'       => $this->payment->currency,
            'phone'          => $this->payment->phone,
            'payer_name'     => $this->payment->payerName,
            'description'    => $this->payment->description,
            'result_code'    => $this->payment->resultCode,
            'transacted_at'  => $this->payment->transactedAt->format('Y-m-d H:i:s'),
            'reference_id'   => $this->payment->referenceId,
        ];
    }
}
