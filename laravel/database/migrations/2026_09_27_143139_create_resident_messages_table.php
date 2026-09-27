<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Each resident's own in-app inbox: messages meant for one person, such as a
 | visitor arriving or a booking being cancelled.
 |
 | Separate from `notifications`, which is the community notice board that
 | everyone reads by role. A row here is visible to its user_id alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 64);
            $table->string('title');
            $table->text('body');
            $table->string('action_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_messages');
    }
};
