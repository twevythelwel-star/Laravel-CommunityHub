<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maintenance_issues')) {
            Schema::create('maintenance_issues', function (Blueprint $table) {
                $table->id();
                $table->string('reference_number', 32)->unique();
                $table->foreignId('community_id')->nullable()->constrained('communities')->nullOnDelete();
                $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
                $table->string('category', 64); // potholes, streetlights, drainage, water_leaks, waste_collection, damaged_common_areas, other
                $table->string('title', 255);
                $table->text('description');
                $table->string('location', 255);
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('priority', 32)->default('medium'); // low, medium, high, emergency
                $table->string('status', 32)->default('reported'); // reported, triaged, assigned, in_progress, resolved, closed, cancelled
                $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('assigned_at')->nullable();
                $table->json('photos')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['community_id', 'status']);
                $table->index(['reporter_id', 'status']);
                $table->index(['category', 'status']);
                $table->index('priority');
            });
        }

        if (! Schema::hasTable('maintenance_issue_updates')) {
            Schema::create('maintenance_issue_updates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('maintenance_issue_id')->constrained('maintenance_issues')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32)->nullable();
                $table->text('note')->nullable();
                $table->boolean('is_internal')->default(false);
                $table->json('photos')->nullable();
                $table->timestamps();

                $table->index('maintenance_issue_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_issue_updates');
        Schema::dropIfExists('maintenance_issues');
    }
};
