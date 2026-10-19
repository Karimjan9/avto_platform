<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_sections', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('name');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('marketplace_sections')->insert([
            ['code' => 'cars', 'name' => 'Mashinalar', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'parts', 'name' => 'Zapchastlar', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'moto', 'name' => 'Moto', 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->string('name', 160);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->string('region', 100)->nullable();
            $table->string('city', 100);
            $table->string('address', 255)->nullable();
            $table->string('phone', 20);
            $table->string('status', 20)->default('pending');
            $table->text('moderation_reason')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['type', 'status', 'city']);
        });

        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('business_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('section', 20);
            $table->foreign('section')->references('code')->on('marketplace_sections')->restrictOnDelete();
            $table->string('title', 160);
            $table->text('description');
            $table->string('condition', 10);
            $table->string('offer_type', 10)->default('sale');
            $table->string('rental_period', 10)->nullable();
            $table->decimal('price', 16, 2);
            $table->char('currency', 3)->default('UZS');
            $table->boolean('is_negotiable')->default(false);
            $table->string('region', 100)->nullable();
            $table->string('city', 100);
            $table->string('contact_phone', 20);
            $table->string('status', 20)->default('draft');
            $table->text('moderation_reason')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['section', 'status', 'published_at']);
            $table->index(['user_id', 'status', 'id']);
            $table->index(['business_id', 'status']);
            $table->index(['currency', 'price']);
            $table->index(['city', 'condition', 'offer_type']);
        });

        Schema::create('car_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('brand', 80);
            $table->string('model', 80);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('mileage_km')->nullable();
            $table->unsignedInteger('engine_cc')->nullable();
            $table->string('fuel_type', 20)->nullable();
            $table->string('transmission', 20)->nullable();
            $table->string('body_type', 20)->nullable();
            $table->string('drive_type', 10)->nullable();
            $table->string('color', 50)->nullable();
            $table->index(['brand', 'model', 'year']);
        });

        Schema::create('part_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('manufacturer', 120)->nullable();
            $table->string('part_number', 80)->nullable()->index();
            $table->json('compatible_brands')->nullable();
            $table->json('compatible_models')->nullable();
        });

        Schema::create('moto_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('brand', 80);
            $table->string('model', 80);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('mileage_km')->nullable();
            $table->unsignedInteger('engine_cc')->nullable();
            $table->string('fuel_type', 20)->nullable();
            $table->index(['type', 'brand', 'year']);
        });

        Schema::create('listing_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['listing_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_images');
        Schema::dropIfExists('moto_details');
        Schema::dropIfExists('part_details');
        Schema::dropIfExists('car_details');
        Schema::dropIfExists('listings');
        Schema::dropIfExists('businesses');
        Schema::dropIfExists('marketplace_sections');
    }
};
