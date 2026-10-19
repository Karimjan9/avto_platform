<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->index(['status', 'deleted_at', 'published_at', 'id'], 'listings_public_recent_index');
            $table->index(['section', 'status', 'deleted_at', 'published_at', 'id'], 'listings_section_recent_index');
            $table->index(['status', 'deleted_at', 'currency', 'price', 'id'], 'listings_public_price_index');
            $table->index(['user_id', 'deleted_at', 'id'], 'listings_cabinet_recent_index');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            foreach (['listings_public_recent_index', 'listings_section_recent_index', 'listings_public_price_index', 'listings_cabinet_recent_index'] as $name) {
                $table->dropIndex($name);
            }
        });
    }
};
