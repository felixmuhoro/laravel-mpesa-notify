<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled Notification Channels
    |--------------------------------------------------------------------------
    | List the channels that should be used by default for all M-Pesa events.
    | Supported: "mail", "mpesa-sms", "slack", "database"
    |
    | You can override this list per-event using the channels_per_event map
    | below.
    |
    */
    'enabled_channels' => array_filter(array_unique(array_merge(
        ['mail', 'database'],
        env('MPESA_NOTIFY_CHANNELS') ? explode(',', env('MPESA_NOTIFY_CHANNELS')) : [],
    ))),

    /*
    |--------------------------------------------------------------------------
    | Per-Event Channel Overrides
    |--------------------------------------------------------------------------
    | Optionally specify a different channel set for each event type.
    | Omit an event to fall back to enabled_channels above.
    |
    | Supported event keys:
    |   payment_successful | payment_failed | stk_push_initiated
    |
    */
    'channels_per_event' => [
        // 'payment_successful' => ['mail', 'mpesa-sms', 'slack', 'database'],
        // 'payment_failed'     => ['mail', 'mpesa-sms', 'database'],
        // 'stk_push_initiated' => ['mpesa-sms'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Africa's Talking SMS Configuration
    |--------------------------------------------------------------------------
    | Credentials for the Africa's Talking SMS gateway.
    | https://developers.africastalking.com/docs/sms
    |
    */
    'africas_talking' => [
        'api_key'   => env('AT_API_KEY', ''),
        'username'  => env('AT_USERNAME', 'sandbox'),
        'sender_id' => env('AT_SENDER_ID', ''),

        /*
         | Set to true to use the Africa's Talking sandbox endpoint.
         | Username must be set to "sandbox" when using the sandbox.
         */
        'sandbox'   => (bool) env('AT_SANDBOX', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Slack Webhook
    |--------------------------------------------------------------------------
    | A workspace-level incoming webhook URL. Individual notifiables can
    | override this via routeNotificationForSlack().
    |
    */
    'slack' => [
        'webhook_url' => env('MPESA_NOTIFY_SLACK_WEBHOOK', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | User Model Resolution
    |--------------------------------------------------------------------------
    | If set, the listener will attempt to look up a user by phone number
    | and deliver the notification to that Eloquent model.
    |
    | user_phone_column – the DB column that stores the E.164 phone number.
    |
    */
    'user_model'        => env('MPESA_NOTIFY_USER_MODEL', 'App\Models\User'),
    'user_phone_column' => env('MPESA_NOTIFY_PHONE_COLUMN', 'phone_number'),

    /*
    |--------------------------------------------------------------------------
    | Auto-listen to Core Package Events
    |--------------------------------------------------------------------------
    | When true, the service provider automatically wires up event listeners
    | for the PaymentSuccessful, PaymentFailed, and StkPushInitiated events
    | fired by felixmuhoro/laravel-mpesa.
    |
    | Set to false if you want to register listeners manually in your
    | EventServiceProvider.
    |
    */
    'listen_to_events' => (bool) env('MPESA_NOTIFY_AUTO_LISTEN', true),

    /*
    |--------------------------------------------------------------------------
    | Notification Log Table
    |--------------------------------------------------------------------------
    | Name of the table created by this package's migration for storing
    | a full audit trail of all dispatched M-Pesa notifications.
    |
    */
    'log_table' => env('MPESA_NOTIFY_LOG_TABLE', 'mpesa_notification_logs'),

];
