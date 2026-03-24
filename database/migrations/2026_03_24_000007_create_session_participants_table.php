<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identity constraint design note
     * ─────────────────────────────────────────────────────────────────────────
     * Every participant must supply EITHER a registered user_id OR a guest_token
     * — exactly one must be non-null (XOR).
     *
     * WHY application-layer enforcement instead of a DB CHECK constraint:
     * MySQL 8.0 prohibits referencing foreign-key columns inside CHECK
     * constraints (error 3823).  Both `user_id` and `guest_token` are involved
     * in FK relationships, so a native CHECK cannot be used portably.
     *
     * The invariant is therefore enforced in:
     *   1. SessionParticipant model — boot() / creating() observer validates XOR.
     *   2. The controller/service that creates participants before calling save().
     *   3. TypeScript discriminated union (SessionParticipant) prevents
     *      constructing an invalid payload on the frontend.
     */
    public function up(): void
    {
        Schema::create('session_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                ->constrained('verdict_sessions')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_token')->nullable()->index();
            $table->string('display_name');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('last_seen_at')->nullable();

            // Prevent a single registered user from joining the same session twice.
            $table->unique(['session_id', 'user_id']);
            // Prevent the same guest token from joining a session twice.
            $table->unique(['session_id', 'guest_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_participants');
    }
};
