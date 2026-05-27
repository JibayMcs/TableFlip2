<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Breeze user has a bigint id, but TableFlip's direct-DB users carry
     * a UUID identifier (see App\Domain\Auth\DirectDbUser). The Laravel
     * database session handler writes the authenticated user's identifier
     * into sessions.user_id, so the column must accept both.
     */
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->string('user_id', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }
};
