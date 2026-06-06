<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaNotify\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Immutable value object that carries all details about an M-Pesa payment event.
 *
 * Constructed from the raw callback / STK response data provided by the
 * felixmuhoro/laravel-mpesa core package events.
 */
final readonly class PaymentData
{
    public function __construct(
        /** M-Pesa transaction reference, e.g. QKA12BC3DE */
        public string $transactionId,

        /** Gross amount settled by M-Pesa, in KES. */
        public float $amount,

        /** Currency code, defaults to KES. */
        public string $currency,

        /** Paying customer's phone number in E.164 format. */
        public string $phone,

        /** Human-readable name returned by M-Pesa. */
        public string $payerName,

        /** Short description / payment reason. */
        public string $description,

        /** Timestamp of the transaction. */
        public DateTimeImmutable $transactedAt,

        /** payment_successful | payment_failed | stk_push_initiated */
        public string $eventType,

        /** Raw M-Pesa result code, null for initiated events. */
        public ?int $resultCode = null,

        /** Optional: internal order / reference ID in your system. */
        public ?string $referenceId = null,

        /** Catch-all for extra metadata from the M-Pesa callback. */
        public array $meta = [],
    ) {
        if (empty($transactionId)) {
            throw new InvalidArgumentException('transactionId must not be empty.');
        }

        if ($amount < 0) {
            throw new InvalidArgumentException('amount must be non-negative.');
        }
    }

    /**
     * Build a PaymentData instance from a raw M-Pesa callback / event payload array.
     *
     * Accepts both the camelCase format returned by the STK callback parser and
     * the snake_case format used internally by the core package events.
     */
    public static function fromArray(array $data): self
    {
        $transactedAt = match (true) {
            isset($data['transactedAt']) && $data['transactedAt'] instanceof DateTimeImmutable
                => $data['transactedAt'],
            isset($data['transacted_at']) && is_string($data['transacted_at'])
                => new DateTimeImmutable($data['transacted_at']),
            isset($data['transactedAt']) && is_string($data['transactedAt'])
                => new DateTimeImmutable($data['transactedAt']),
            default => new DateTimeImmutable(),
        };

        return new self(
            transactionId: $data['transactionId'] ?? $data['transaction_id'] ?? '',
            amount:        (float) ($data['amount'] ?? 0),
            currency:      $data['currency'] ?? 'KES',
            phone:         $data['phone'] ?? $data['phoneNumber'] ?? $data['phone_number'] ?? '',
            payerName:     $data['payerName'] ?? $data['payer_name'] ?? 'Customer',
            description:   $data['description'] ?? $data['AccountReference'] ?? 'M-Pesa Payment',
            transactedAt:  $transactedAt,
            eventType:     $data['eventType'] ?? $data['event_type'] ?? 'payment_successful',
            resultCode:    isset($data['resultCode']) ? (int) $data['resultCode'] : null,
            referenceId:   $data['referenceId'] ?? $data['reference_id'] ?? null,
            meta:          $data['meta'] ?? [],
        );
    }

    /** Format amount as a localised string, e.g. "KES 1,500.00". */
    public function formattedAmount(): string
    {
        return sprintf('%s %s', $this->currency, number_format($this->amount, 2));
    }

    /** Whether the event represents a completed (successful) payment. */
    public function isSuccessful(): bool
    {
        return $this->eventType === 'payment_successful'
            && ($this->resultCode === null || $this->resultCode === 0);
    }

    /** Convert back to a plain array (useful for database JSON columns). */
    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'amount'         => $this->amount,
            'currency'       => $this->currency,
            'phone'          => $this->phone,
            'payer_name'     => $this->payerName,
            'description'    => $this->description,
            'transacted_at'  => $this->transactedAt->format('Y-m-d H:i:s'),
            'event_type'     => $this->eventType,
            'result_code'    => $this->resultCode,
            'reference_id'   => $this->referenceId,
            'meta'           => $this->meta,
        ];
    }
}
