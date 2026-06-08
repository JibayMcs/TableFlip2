<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent : Docker volumes persist across deploys, so the table
        // may already exist when the migrations log has been reset.
        if (Schema::hasTable('exports')) {
            return;
        }

        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->string('user_kind', 16);          // 'direct_db' since CP1 (kept for retro-compat, dropped in CP3)
            $table->string('user_identifier', 64)->index();
            $table->string('database_name')->nullable();

            $table->string('format', 8);              // 'csv' | 'sql' | 'json'
            $table->json('options')->nullable();      // format-specific knobs

            $table->string('source_kind', 16);        // 'table' | 'raw_sql'
            $table->json('source_payload');           // table+filters+sort OR raw SQL

            $table->string('status', 16)->default('pending'); // pending/running/completed/failed
            $table->string('file_name');
            $table->string('file_path')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedBigInteger('byte_size')->default(0);
            $table->text('error_message')->nullable();

            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            $table->index(['user_kind', 'user_identifier', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
