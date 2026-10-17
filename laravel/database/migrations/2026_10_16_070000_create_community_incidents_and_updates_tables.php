<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_number')->unique();
            $table->string('title');
            $table->string('category')->default('general');
            $table->string('severity')->default('medium');
            $table->string('status')->default('reported');
            $table->string('location');
            $table->text('summary');
            $table->text('sensitive_details')->nullable();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('all_clear_at')->nullable();
            $table->foreignId('all_clear_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('all_clear_message')->nullable();
            $table->boolean('broadcast_sent')->default(false);
            $table->timestamps();
        });

        Schema::create('community_incident_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('community_incidents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->string('status_after');
            $table->boolean('is_internal')->default(false);
            $table->boolean('broadcast_sent')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_incident_updates');
        Schema::dropIfExists('community_incidents');
    }
};
