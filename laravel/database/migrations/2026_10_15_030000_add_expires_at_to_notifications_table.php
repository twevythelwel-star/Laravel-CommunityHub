<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications') && ! Schema::hasColumn('notifications', 'expires_at')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->after('published_at')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'expires_at')) {
            Schema::table('notifications', function (Blueprint $table) {
                // The index first: SQLite will not drop an indexed column.
                $table->dropIndex(['expires_at']);
                $table->dropColumn('expires_at');
            });
        }
    }
};
