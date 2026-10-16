<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (! Schema::hasColumn('notifications', 'requires_acknowledgement')) {
                    $table->boolean('requires_acknowledgement')->default(false)->after('stats')->index();
                }
                if (! Schema::hasColumn('notifications', 'acknowledgement_deadline')) {
                    $table->timestamp('acknowledgement_deadline')->nullable()->after('requires_acknowledgement');
                }
            });
        }

        if (Schema::hasTable('announcement_recipients')) {
            Schema::table('announcement_recipients', function (Blueprint $table) {
                if (! Schema::hasColumn('announcement_recipients', 'read_at')) {
                    $table->timestamp('read_at')->nullable()->after('delivered_at');
                }
                if (! Schema::hasColumn('announcement_recipients', 'acknowledged_at')) {
                    $table->timestamp('acknowledged_at')->nullable()->after('read_at');
                }
                if (! Schema::hasColumn('announcement_recipients', 'acknowledgement_note')) {
                    $table->text('acknowledgement_note')->nullable()->after('acknowledged_at');
                }

                $table->index(['notification_id', 'read_at']);
                $table->index(['notification_id', 'acknowledged_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('announcement_recipients')) {
            Schema::table('announcement_recipients', function (Blueprint $table) {
                $table->dropIndex(['notification_id', 'read_at']);
                $table->dropIndex(['notification_id', 'acknowledged_at']);
                $table->dropColumn(['read_at', 'acknowledged_at', 'acknowledgement_note']);
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropColumn(['requires_acknowledgement', 'acknowledgement_deadline']);
            });
        }
    }
};
