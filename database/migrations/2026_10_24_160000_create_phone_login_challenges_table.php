<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_login_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phone', 16);
            $table->foreignId('bound_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->char('session_hash', 64);
            $table->char('ip_hash', 64);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['phone', 'created_at']);
            $table->index(['ip_hash', 'created_at']);
            $table->index(['phone', 'sent_at']);
            $table->index(['ip_hash', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_login_challenges');
    }
};
