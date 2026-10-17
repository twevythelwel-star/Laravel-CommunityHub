<?php

use App\Models\CommunityEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_events', function (Blueprint $table) {
            $table->string('event_type', 32)->default(CommunityEvent::TYPE_EVENT)->after('description');
            $table->string('location', 255)->nullable()->after('event_type');
            $table->boolean('is_all_day')->default(false)->after('location');
            $table->unsignedSmallInteger('remind_before_hours')->default(24)->after('is_all_day');
            $table->timestamp('reminded_at')->nullable()->after('remind_before_hours');
            $table->string('change_summary', 500)->nullable()->after('reminded_at');

            $table->index(['start_date', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::table('community_events', function (Blueprint $table) {
            $table->dropIndex(['start_date', 'event_type']);
            $table->dropColumn([
                'event_type',
                'location',
                'is_all_day',
                'remind_before_hours',
                'reminded_at',
                'change_summary',
            ]);
        });
    }
};
