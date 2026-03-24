<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_type_id')->constrained('content_types')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('visibility', ['system', 'public', 'private'])->default('private');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_sets');
    }
};
