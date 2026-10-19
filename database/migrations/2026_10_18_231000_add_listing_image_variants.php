<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_images', function (Blueprint $table) {
            $table->string('thumbnail_path')->nullable();
            $table->string('display_path')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('listing_images', fn (Blueprint $table) => $table->dropColumn(['thumbnail_path', 'display_path', 'width', 'height']));
    }
};
