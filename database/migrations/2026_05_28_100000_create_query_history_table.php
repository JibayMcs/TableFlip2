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
        if (Schema::hasTable('query_history')) {
            return;
        }

        Schema::create('query_history', function (Blueprint $table) {
            $table->id();
            $table->string('user_kind', 16);              // 'web' or 'direct_db'
            $table->string('user_identifier', 64)->index();
            $table->string('connection_label')->nullable();
            $table->string('database_name')->nullable();
            $table->text('sql_text');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('status', 16);                  // 'success' or 'error'
            $table->text('error_message')->nullable();
            $table->unsignedInteger('affected_rows')->default(0);
            $table->timestamp('executed_at')->useCurrent();

            $table->index(['user_kind', 'user_identifier', 'executed_at']);
            $table->index('executed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_history');
    }
};
