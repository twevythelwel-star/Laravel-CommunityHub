<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets any feature's notifications record their deliveries against what they are about
 * (a maintenance issue, a facility booking…), alongside the existing invoice and
 * announcement links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('notification_deliveries', 'subject_type')) {
                $table->nullableMorphs('subject');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('notification_deliveries', 'subject_type')) {
                $table->dropMorphs('subject');
            }
        });
    }
};
