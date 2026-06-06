<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the mpesa_notification_logs table.
 *
 * This table acts as a full audit trail for every M-Pesa notification
 * dispatched by the package, independently of Laravel's built-in
 * notifications table. It records delivery outcomes per channel.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('mpesa-notify.log_table', 'mpesa_notification_logs');

        Schema::create($table, function (Blueprint $table) {
            $table->id();

            // The M-Pesa transaction reference, e.g. QKA12BC3DE.
            $table->string('transaction_id', 32)->index();

            // The type of event that triggered this notification.
            $table->enum('event_type', [
                'payment_successful',
                'payment_failed',
                'stk_push_initiated',
            ])->index();

            // Amount and currency.
            $table->decimal('amount', 12, 2)->default(0);
            $table->char('currency', 3)->default('KES');

            // Payer details (partially masked in application layer if needed).
            $table->string('phone', 20)->nullable()->index();
            $table->string('payer_name', 120)->nullable();

            // Channels that were active for this notification (JSON array).
            $table->json('channels')->nullable();

            // Per-channel delivery result: {"mail": "sent", "sms": "failed", ...}
            $table->json('delivery_status')->nullable();

            // Optional link to the notifiable (e.g. users table).
            $table->nullableMorphs('notifiable');

            // Optional internal reference.
            $table->string('reference_id', 64)->nullable()->index();

            // Arbitrary extra data from the callback payload.
            $table->json('meta')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        $table = config('mpesa-notify.log_table', 'mpesa_notification_logs');
        Schema::dropIfExists($table);
    }
};
