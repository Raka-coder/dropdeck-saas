<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('droplink_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('droplink_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attachment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 24);
            $table->string('blocked_reason', 16)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at');

            $table->index(['droplink_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('droplink_accesses');
    }
};
