<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('google_id', 64)->nullable()->unique();
            $table->string('avatar_url', 512)->nullable();
            $table->unsignedBigInteger('storage_quota_bytes')->default(2147483648);
            $table->unsignedBigInteger('storage_used_bytes')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['google_id', 'avatar_url', 'storage_quota_bytes', 'storage_used_bytes']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
