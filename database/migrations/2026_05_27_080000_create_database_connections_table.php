<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('driver', 32);
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('database')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable(); // encrypted at rest via cast
            $table->json('options')->nullable();
            $table->boolean('ssl')->default(false);
            $table->string('color', 16)->default('#71717a');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_connections');
    }
};
