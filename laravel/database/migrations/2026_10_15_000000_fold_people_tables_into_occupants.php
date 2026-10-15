<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the parallel people model created by
 * 2026_10_13_010000_create_people_and_access_architecture_tables: the
 * `people`, `property_relationships` and `access_authorizations` tables and
 * the person_id, access_authorization_id and property_id columns it added to
 * gate_passes.
 *
 * Everything in them was a one-time copy of users, occupants and visitors
 * made by that migration's backfill. Nothing in the application wrote to
 * them afterwards, so they drifted from the records they copied as soon as a
 * lease ended or a member was removed. The people allowed on a property are
 * `occupants` (renters, household members, staff), with owners on
 * users/properties, guests on visitors, and delegates and company
 * representatives in their own tables.
 *
 * down() restores the empty structure; the copied rows can be regenerated
 * from the source tables if ever needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('gate_passes', 'person_id')) {
            Schema::table('gate_passes', function (Blueprint $table) {
                $table->dropForeign(['person_id']);
                $table->dropForeign(['access_authorization_id']);
                $table->dropForeign(['property_id']);
                $table->dropColumn(['person_id', 'access_authorization_id', 'property_id']);
            });
        }

        Schema::dropIfExists('access_authorizations');
        Schema::dropIfExists('property_relationships');
        Schema::dropIfExists('people');
    }

    public function down(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email', 150)->nullable()->index();
            $table->string('phone', 50)->nullable()->index();
            $table->string('id_type', 50)->nullable();
            $table->string('id_number', 100)->nullable();
            $table->date('id_expiry')->nullable();
            $table->string('id_image_url')->nullable();
            $table->string('photo_url')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('property_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('role_label', 100)->default('Occupant');
            $table->string('status', 30)->default('active');
            $table->foreignId('sponsor_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('sponsor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'type', 'status']);
            $table->index(['person_id', 'property_id']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('access_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('property_relationship_id')->nullable()->constrained('property_relationships')->nullOnDelete();
            $table->string('scope', 30)->default('property');
            $table->json('allowed_zones')->nullable();
            $table->json('permissions')->nullable();
            $table->json('access_rules')->nullable();
            $table->string('approval_status', 30)->default('approved');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('valid_from');
            $table->dateTime('valid_until')->nullable();
            $table->string('status', 30)->default('active');
            $table->boolean('is_revoked')->default(false);
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revocation_reason')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'status', 'is_revoked']);
            $table->index(['property_id', 'status']);
            $table->index(['scope', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::table('gate_passes', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->after('user_id')->constrained('people')->nullOnDelete();
            $table->foreignId('access_authorization_id')->nullable()->after('person_id')->constrained('access_authorizations')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->after('access_authorization_id')->constrained('properties')->nullOnDelete();
        });
    }
};
