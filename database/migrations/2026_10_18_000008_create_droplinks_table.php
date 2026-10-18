<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('droplinks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 16);
            $table->foreignId('attachment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('token', 40)->unique();
            $table->string('label', 120)->nullable();
            $table->string('password_hash')->nullable();
            $table->dateTime('expires_at');
            $table->unsignedSmallInteger('max_downloads')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->dateTime('last_accessed_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at', 'expires_at']);
            $table->index('attachment_id');
            $table->index('card_id');
            $table->index('expires_at');
        });

        // CHECK constraints are only enforced on MySQL/MariaDB/PostgreSQL.
        // SQLite (used by the test suite) cannot add constraints via ALTER TABLE.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            DB::statement("ALTER TABLE droplinks ADD CONSTRAINT chk_droplink_target CHECK (
                (target_type = 'file' AND attachment_id IS NOT NULL AND card_id IS NULL) OR
                (target_type = 'card' AND card_id IS NOT NULL AND attachment_id IS NULL)
            )");
            DB::statement('ALTER TABLE droplinks ADD CONSTRAINT chk_droplink_max CHECK (max_downloads IS NULL OR max_downloads >= 1)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('droplinks');
    }
};
