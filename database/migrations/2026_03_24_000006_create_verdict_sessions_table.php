<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NOTE: The table is named 'verdict_sessions' (not 'sessions') to avoid
        // conflicting with Laravel's built-in session driver table.
        Schema::create('verdict_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique()->comment('Public join code; safe to expose in URLs');
            $table->foreignId('content_set_id')->constrained('content_sets')->cascadeOnDelete();
            $table->foreignId('host_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['waiting', 'active', 'completed'])->default('waiting');
            $table->json('settings')->nullable()->comment('Per-session overrides (e.g. anonymous votes, reveal timing)');
            $table->foreignId('rating_config_id')->constrained('rating_configs');
            $table->timestamps();
            $table->timestamp('expires_at')->nullable();

            $table->index('ulid');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verdict_sessions');
    }
};
