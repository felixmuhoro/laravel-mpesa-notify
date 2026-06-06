<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaNotify\Tests;

use DateTimeImmutable;
use FelixMuhoro\MpesaNotify\Channels\SmsChannel;
use FelixMuhoro\MpesaNotify\MpesaNotificationManager;
use FelixMuhoro\MpesaNotify\MpesaNotifyServiceProvider;
use FelixMuhoro\MpesaNotify\Notifications\PaymentFailedNotification;
use FelixMuhoro\MpesaNotify\Notifications\PaymentSuccessfulNotification;
use FelixMuhoro\MpesaNotify\Notifications\StkPushInitiatedNotification;
use FelixMuhoro\MpesaNotify\ValueObjects\PaymentData;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use RuntimeException;

/**
 * Integration + unit tests for felixmuhoro/laravel-mpesa-notify.
 */
final class NotificationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [MpesaNotifyServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('mpesa-notify', [
            'enabled_channels'   => ['mail', 'database'],
            'channels_per_event' => [],
            'africas_talking'    => [
                'api_key'   => 'test-api-key',
                'username'  => 'sandbox',
                'sender_id' => 'MPESA',
                'sandbox'   => true,
            ],
            'slack'             => ['webhook_url' => ''],
            'user_model'        => null,
            'user_phone_column' => 'phone_number',
            'listen_to_events'  => false,
            'log_table'         => 'mpesa_notification_logs',
        ]);
    }

    // -------------------------------------------------------------------------
    // PaymentData value object tests
    // -------------------------------------------------------------------------

    public function test_payment_data_constructs_from_array(): void
    {
        $data = PaymentData::fromArray([
            'transactionId' => 'QKA12BC3DE',
            'amount'        => 1500.00,
            'currency'      => 'KES',
            'phone'         => '+254712345678',
            'payerName'     => 'John Doe',
            'description'   => 'Order #123',
            'transacted_at' => '2024-01-15 10:30:00',
            'eventType'     => 'payment_successful',
        ]);

        $this->assertSame('QKA12BC3DE', $data->transactionId);
        $this->assertSame(1500.00, $data->amount);
        $this->assertSame('KES', $data->currency);
        $this->assertSame('+254712345678', $data->phone);
        $this->assertTrue($data->isSuccessful());
        $this->assertSame('KES 1,500.00', $data->formattedAmount());
    }

    public function test_payment_data_detects_failed_event(): void
    {
        $data = PaymentData::fromArray([
            'transactionId' => 'QKB99ZZ11',
            'amount'        => 500,
            'currency'      => 'KES',
            'phone'         => '+254700000000',
            'payerName'     => 'Jane Doe',
            'description'   => 'Test',
            'eventType'     => 'payment_failed',
            'resultCode'    => 1032,
        ]);

        $this->assertFalse($data->isSuccessful());
        $this->assertSame(1032, $data->resultCode);
    }

    public function test_payment_data_rejects_empty_transaction_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PaymentData(
            transactionId: '',
            amount:        100,
            currency:      'KES',
            phone:         '+254712345678',
            payerName:     'Test',
            description:   'Test',
            transactedAt:  new DateTimeImmutable(),
            eventType:     'payment_successful',
        );
    }

    public function test_payment_data_to_array_round_trips(): void
    {
        $original = PaymentData::fromArray([
            'transactionId' => 'QKA12BC3DE',
            'amount'        => 2500,
            'currency'      => 'KES',
            'phone'         => '+254712345678',
            'payerName'     => 'Alice',
            'description'   => 'Subscription',
            'eventType'     => 'payment_successful',
            'referenceId'   => 'ORD-001',
        ]);

        $arr = $original->toArray();

        $this->assertArrayHasKey('transaction_id', $arr);
        $this->assertSame('QKA12BC3DE', $arr['transaction_id']);
        $this->assertSame('ORD-001', $arr['reference_id']);
    }

    // -------------------------------------------------------------------------
    // MpesaNotificationManager tests
    // -------------------------------------------------------------------------

    public function test_manager_returns_configured_channels(): void
    {
        $manager = new MpesaNotificationManager([
            'enabled_channels'   => ['mail', 'database'],
            'channels_per_event' => [],
            'africas_talking'    => ['api_key' => '', 'username' => ''],
            'slack'              => ['webhook_url' => ''],
        ]);

        $channels = $manager->channelsFor(MpesaNotificationManager::EVENT_PAYMENT_SUCCESSFUL);

        $this->assertContains('mail', $channels);
        $this->assertContains('database', $channels);
    }

    public function test_manager_excludes_sms_when_no_credentials(): void
    {
        $manager = new MpesaNotificationManager([
            'enabled_channels'   => ['mail', 'mpesa-sms', 'database'],
            'channels_per_event' => [],
            'africas_talking'    => ['api_key' => '', 'username' => ''],
            'slack'              => ['webhook_url' => ''],
        ]);

        $channels = $manager->channelsFor(MpesaNotificationManager::EVENT_PAYMENT_SUCCESSFUL);

        $this->assertNotContains('mpesa-sms', $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_manager_uses_per_event_override(): void
    {
        $manager = new MpesaNotificationManager([
            'enabled_channels'   => ['mail', 'database'],
            'channels_per_event' => [
                'payment_failed' => ['database'],
            ],
            'africas_talking'    => ['api_key' => '', 'username' => ''],
            'slack'              => ['webhook_url' => ''],
        ]);

        $failedChannels     = $manager->channelsFor(MpesaNotificationManager::EVENT_PAYMENT_FAILED);
        $successfulChannels = $manager->channelsFor(MpesaNotificationManager::EVENT_PAYMENT_SUCCESSFUL);

        $this->assertSame(['database'], $failedChannels);
        $this->assertContains('mail', $successfulChannels);
    }

    // -------------------------------------------------------------------------
    // Notification toArray / toSms tests
    // -------------------------------------------------------------------------

    public function test_payment_successful_notification_to_array(): void
    {
        $payment = PaymentData::fromArray([
            'transactionId' => 'QKA12BC3DE',
            'amount'        => 1000,
            'currency'      => 'KES',
            'phone'         => '+254712345678',
            'payerName'     => 'Test User',
            'description'   => 'Order #1',
            'eventType'     => 'payment_successful',
        ]);

        $notification = new PaymentSuccessfulNotification($payment);
        $arr = $notification->toArray(new AnonymousNotifiable());

        $this->assertSame('payment_successful', $arr['type']);
        $this->assertSame('QKA12BC3DE', $arr['transaction_id']);
        $this->assertSame(1000.0, $arr['amount']);
    }

    public function test_payment_successful_sms_message(): void
    {
        $payment = PaymentData::fromArray([
            'transactionId' => 'QKA12BC3DE',
            'amount'        => 1500,
            'currency'      => 'KES',
            'phone'         => '+254712345678',
            'payerName'     => 'Test User',
            'description'   => 'Order',
            'eventType'     => 'payment_successful',
        ]);

        $notification = new PaymentSuccessfulNotification($payment);
        $sms = $notification->toSms(new AnonymousNotifiable());

        $this->assertStringContainsString('KES 1,500.00', $sms);
        $this->assertStringContainsString('QKA12BC3DE', $sms);
        $this->assertLessThanOrEqual(160, strlen($sms), 'SMS body must fit in one segment.');
    }

    public function test_payment_failed_notification_to_array(): void
    {
        $payment = PaymentData::fromArray([
            'transactionId' => 'QKB12FAIL',
            'amount'        => 800,
            'currency'      => 'KES',
            'phone'         => '+254700000000',
            'payerName'     => 'Bob',
            'description'   => 'Sub',
            'eventType'     => 'payment_failed',
            'resultCode'    => 1032,
        ]);

        $notification = new PaymentFailedNotification($payment);
        $arr = $notification->toArray(new AnonymousNotifiable());

        $this->assertSame('payment_failed', $arr['type']);
        $this->assertSame(1032, $arr['result_code']);
    }

    public function test_stk_push_initiated_sms_message(): void
    {
        $payment = PaymentData::fromArray([
            'transactionId' => 'STK9876543',
            'amount'        => 500,
            'currency'      => 'KES',
            'phone'         => '+254711111111',
            'payerName'     => 'Carol',
            'description'   => 'Deposit',
            'eventType'     => 'stk_push_initiated',
        ]);

        $notification = new StkPushInitiatedNotification($payment);
        $sms = $notification->toSms(new AnonymousNotifiable());

        $this->assertStringContainsString('KES 500.00', $sms);
        $this->assertStringContainsString('Deposit', $sms);
    }

    // -------------------------------------------------------------------------
    // SmsChannel tests (mocked HTTP)
    // -------------------------------------------------------------------------

    public function test_sms_channel_dispatches_to_africas_talking(): void
    {
        $mockResponse = [
            'SMSMessageData' => [
                'Message'    => 'Sent to 1/1 Total Cost: KES 0.8000',
                'Recipients' => [
                    [
                        'statusCode' => 101,
                        'number'     => '+254712345678',
                        'status'     => 'Success',
                        'cost'       => 'KES 0.8000',
                        'messageId'  => 'ATXid_abc123',
                    ],
                ],
            ],
        ];

        $mock    = new MockHandler([new Response(201, [], json_encode($mockResponse))]);
        $handler = HandlerStack::create($mock);
        $http    = new Client(['handler' => $handler]);

        $channel = new SmsChannel(
            apiKey:   'test-api-key',
            username: 'sandbox',
            senderId: 'MPESA',
            sandbox:  true,
            http:     $http,
        );

        $payment = PaymentData::fromArray([
            'transactionId' => 'QKA12BC3DE',
            'amount'        => 1000,
            'currency'      => 'KES',
            'phone'         => '+254712345678',
            'payerName'     => 'Test',
            'description'   => 'Order',
            'eventType'     => 'payment_successful',
        ]);

        $notification = new PaymentSuccessfulNotification($payment);

        $notifiable = new class {
            public function routeNotificationForMpesaSms(): string
            {
                return '+254712345678';
            }
        };

        $result = $channel->send($notifiable, $notification);

        $this->assertArrayHasKey('SMSMessageData', $result);
        $this->assertCount(1, $result['SMSMessageData']['Recipients']);
    }

    public function test_sms_channel_rejects_empty_api_key(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SmsChannel(apiKey: '', username: 'sandbox', senderId: '');
    }

    // -------------------------------------------------------------------------
    // Fake notification delivery test
    // -------------------------------------------------------------------------

    public function test_notifications_can_be_faked_and_asserted(): void
    {
        Notification::fake();

        $payment = PaymentData::fromArray([
            'transactionId' => 'QKA12BC3DE',
            'amount'        => 750,
            'currency'      => 'KES',
            'phone'         => '+254712345678',
            'payerName'     => 'Dave',
            'description'   => 'Test',
            'eventType'     => 'payment_successful',
        ]);

        $notifiable = new AnonymousNotifiable();
        $notifiable->route('mail', 'dave@example.com');

        $notifiable->notify(new PaymentSuccessfulNotification($payment));

        Notification::assertSentTo(
            $notifiable,
            PaymentSuccessfulNotification::class,
        );
    }
}
