<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-recipient delivery tracking for announcements.
 *
 * An announcement is a row in `notifications`: title, content, category,
 * priority, audience (target_type + target_criteria), channels, status,
 * schedule, expiry and stats all live there, so the notice board, the
 * dashboard home and the residents' feed read one record.
 *
 * What it lacked is a per-recipient record: who it was sent to, on which
 * channels, what each channel answered and whether it failed. That is this
 * table, one row per (notification, user), which is also what stops a
 * resident from receiving the same announcement twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcement_recipients')) {
            Schema::create('announcement_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('recipient_name')->nullable();
                $table->string('recipient_email')->nullable();
                $table->string('recipient_phone')->nullable();
                $table->string('delivery_state', 32)->default('pending'); // pending, delivered, partially_delivered, failed
                $table->json('attempted_channels')->nullable();
                $table->json('channel_outcomes')->nullable();
                $table->text('failure_details')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();

                // One delivery per resident per announcement.
                $table->unique(['notification_id', 'user_id']);
                $table->index(['notification_id', 'delivery_state']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_recipients');
    }
};
