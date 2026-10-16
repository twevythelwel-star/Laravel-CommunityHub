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
                if (! Schema::hasColumn('notifications', 'community_id')) {
                    $table->foreignId('community_id')->nullable()->after('id')->constrained('communities')->nullOnDelete();
                }
                if (! Schema::hasColumn('notifications', 'target_type')) {
                    $table->string('target_type', 32)->default('entire_community')->after('target_roles');
                }
                if (! Schema::hasColumn('notifications', 'target_criteria')) {
                    $table->json('target_criteria')->nullable()->after('target_type');
                }
                if (! Schema::hasColumn('notifications', 'channels')) {
                    $table->json('channels')->nullable()->after('target_criteria');
                }
                if (! Schema::hasColumn('notifications', 'status')) {
                    $table->string('status', 32)->default('published')->after('channels')->index();
                }
                if (! Schema::hasColumn('notifications', 'scheduled_for')) {
                    $table->timestamp('scheduled_for')->nullable()->after('status')->index();
                }
                if (! Schema::hasColumn('notifications', 'sent_at')) {
                    $table->timestamp('sent_at')->nullable()->after('scheduled_for');
                }
                if (! Schema::hasColumn('notifications', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('sent_at');
                }
                if (! Schema::hasColumn('notifications', 'approved_by')) {
                    $table->foreignId('approved_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('notifications', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by');
                }
                if (! Schema::hasColumn('notifications', 'stats')) {
                    $table->json('stats')->nullable()->after('approved_at');
                }
                if (! Schema::hasColumn('notifications', 'category')) {
                    $table->string('category', 48)->default('general')->after('content');
                }
                if (! Schema::hasColumn('notifications', 'priority')) {
                    $table->string('priority', 24)->default('normal')->after('category');
                }
            });
        }

        if (Schema::hasTable('notification_deliveries')) {
            Schema::table('notification_deliveries', function (Blueprint $table) {
                if (! Schema::hasColumn('notification_deliveries', 'notification_id')) {
                    $table->foreignId('notification_id')->nullable()->after('invoice_id')->constrained('notifications')->cascadeOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notification_deliveries')) {
            Schema::table('notification_deliveries', function (Blueprint $table) {
                if (Schema::hasColumn('notification_deliveries', 'notification_id')) {
                    $table->dropConstrainedForeignId('notification_id');
                }
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $columns = [
                    'priority', 'category',
                    'stats', 'approved_at', 'approved_by', 'cancelled_at',
                    'sent_at', 'scheduled_for', 'status', 'channels',
                    'target_criteria', 'target_type', 'community_id',
                ];

                // Indexes first: SQLite will not drop an indexed column.
                foreach (['status', 'scheduled_for'] as $indexed) {
                    if (Schema::hasColumn('notifications', $indexed)) {
                        $table->dropIndex([$indexed]);
                    }
                }

                foreach ($columns as $column) {
                    if (Schema::hasColumn('notifications', $column)) {
                        if (in_array($column, ['community_id', 'approved_by'])) {
                            $table->dropConstrainedForeignId($column);
                        } else {
                            $table->dropColumn($column);
                        }
                    }
                }
            });
        }
    }
};
