<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->default('UTC')->after('email_verified_at');
            $table->string('locale', 10)->default('en')->after('timezone');
            $table->string('theme', 20)->default('light')->after('locale');
            $table->boolean('is_active')->default(true)->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'locale', 'theme', 'is_active']);
        });
    }
};
