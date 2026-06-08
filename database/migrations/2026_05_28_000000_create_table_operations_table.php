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
        if (Schema::hasTable('table_operations')) {
            return;
        }

        Schema::create('table_operations', function (Blueprint $table) {
            $table->id();
            // 'web' (Breeze) or 'direct_db' (phpMyAdmin-style session)
            $table->string('user_kind', 16);
            // user_id for web users, UUID for direct_db users
            $table->string('user_identifier', 64)->index();
            // Denormalised connection label so the log stays readable even if
            // the underlying connection is later renamed or deleted.
            $table->string('connection_label')->nullable();
            $table->string('database_name');
            $table->string('schema_name')->nullable();
            $table->string('table_name');
            $table->string('operation', 16); // insert | update | delete
            $table->text('sql_text');
            $table->json('bindings')->nullable();
            $table->unsignedInteger('affected_rows')->default(0);
            $table->timestamp('performed_at')->useCurrent();

            $table->index(['database_name', 'table_name', 'performed_at']);
            $table->index(['operation', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_operations');
    }
};
