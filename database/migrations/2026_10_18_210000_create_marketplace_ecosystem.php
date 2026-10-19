<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_makes', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80)->unique();
            $t->timestamps();
        });
        Schema::create('vehicle_models', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_make_id')->constrained()->restrictOnDelete();
            $t->string('section', 20);
            $t->string('name', 80);
            $t->timestamps();
            $t->unique(['vehicle_make_id', 'section', 'name']);
        });
        Schema::create('vehicle_generations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_model_id')->constrained()->restrictOnDelete();
            $t->string('name', 80);
            $t->unsignedSmallInteger('year_from');
            $t->unsignedSmallInteger('year_to')->nullable();
            $t->timestamps();
            $t->unique(['vehicle_model_id', 'name']);
        });
        Schema::create('vehicle_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_generation_id')->constrained()->restrictOnDelete();
            $t->string('name', 120);
            $t->string('engine_code', 80)->nullable();
            $t->unsignedInteger('engine_cc')->nullable();
            $t->string('fuel_type', 20);
            $t->decimal('power_kw', 8, 2)->nullable();
            $t->timestamps();
            $t->unique(['vehicle_generation_id', 'name']);
        });
        Schema::create('garage_vehicles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_variant_id')->constrained()->restrictOnDelete();
            $t->string('name', 100);
            $t->unsignedSmallInteger('year');
            $t->unsignedInteger('mileage_km')->nullable();
            $t->timestamps();
        });
        Schema::create('business_sections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('section', 20);
            $t->foreign('section')->references('code')->on('marketplace_sections')->restrictOnDelete();
            $t->unique(['business_id', 'section']);
        });
        DB::table('businesses')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('business_sections')->insert(['business_id' => $row->id, 'section' => match ($row->type) {
                    'car_dealership' => 'cars', 'parts_store' => 'parts', 'moto_dealership' => 'moto'
                }]);
            }
        });
        Schema::create('business_branches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->string('city', 100);
            $t->string('address', 255);
            $t->string('phone', 20)->nullable();
            $t->json('working_hours')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('business_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->json('permissions');
            $t->timestamps();
            $t->unique(['business_id', 'user_id']);
        });
        Schema::create('part_products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('identity_key', 64)->unique();
            $t->string('title', 160);
            $t->string('manufacturer', 120);
            $t->string('part_number', 80);
            $t->string('article_key', 80)->index();
            $t->string('oem_number', 80)->nullable()->index();
            $t->string('category', 30)->index();
            $t->json('attributes')->nullable();
            $t->string('status', 20)->default('pending');
            $t->text('moderation_reason')->nullable();
            $t->timestamps();
        });
        Schema::create('part_fitments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('part_product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_variant_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('year_from')->nullable();
            $t->unsignedSmallInteger('year_to')->nullable();
            $t->unique(['part_product_id', 'vehicle_variant_id']);
        });
        Schema::table('listings', function (Blueprint $t) {
            $t->foreignId('vehicle_variant_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('part_product_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('business_branch_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('part_details', function (Blueprint $t) {
            $t->boolean('delivery_available')->default(false);
            $t->boolean('pickup_available')->default(true);
            $t->text('delivery_terms')->nullable();
            $t->unsignedTinyInteger('warranty_months')->nullable();
            $t->unsignedSmallInteger('return_days')->nullable();
            $t->text('return_terms')->nullable();
            $t->dateTime('stock_updated_at')->nullable();
            $t->unsignedInteger('stock_version')->default(0);
        });
        Schema::create('part_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_variant_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('vehicle_year');
            $t->string('title', 160);
            $t->text('description');
            $t->string('category', 30);
            $t->string('part_number', 80)->nullable();
            $t->string('city', 100);
            $t->string('status', 20)->default('open');
            $t->dateTime('expires_at');
            $t->timestamps();
            $t->index(['status', 'expires_at']);
        });
        Schema::create('part_quotes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('part_request_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->foreignId('listing_id')->nullable()->constrained()->restrictOnDelete();
            $t->decimal('price', 16, 2);
            $t->char('currency', 3);
            $t->text('message');
            $t->string('status', 20)->default('pending');
            $t->timestamps();
            $t->unique(['part_request_id', 'business_id']);
        });
        Schema::create('business_inquiries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('listing_id')->nullable()->constrained()->restrictOnDelete();
            $t->text('message');
            $t->text('reply')->nullable();
            $t->string('status', 20)->default('open');
            $t->timestamps();
        });
        Schema::create('saved_searches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->json('filters');
            $t->boolean('is_active')->default(true);
            $t->dateTime('last_checked_at')->nullable();
            $t->timestamps();
            $t->index(['is_active', 'last_checked_at', 'id']);
        });
        Schema::create('saved_search_matches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('saved_search_id')->constrained()->cascadeOnDelete();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['saved_search_id', 'listing_id']);
        });
        Schema::create('marketplace_notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type', 50);
            $t->string('title', 160);
            $t->json('data');
            $t->string('deduplication_key', 191)->nullable()->unique();
            $t->dateTime('read_at')->nullable();
            $t->string('push_status', 20)->default('pending');
            $t->timestamps();
            $t->index(['user_id', 'read_at', 'created_at']);
            $t->index(['push_status', 'created_at']);
        });
        Schema::create('push_devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('device_id', 100);
            $t->string('platform', 10);
            $t->text('token');
            $t->string('token_hash', 64)->unique();
            $t->dateTime('last_seen_at');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['user_id', 'device_id']);
        });
        Schema::create('push_deliveries', function (Blueprint $t) {
            $t->id();
            $t->uuid('marketplace_notification_id');
            $t->foreign('marketplace_notification_id', 'push_delivery_notification_fk')->references('id')->on('marketplace_notifications')->cascadeOnDelete();
            $t->foreignId('push_device_id')->constrained()->cascadeOnDelete();
            $t->dateTime('sent_at')->nullable();
            $t->unique(['marketplace_notification_id', 'push_device_id'], 'push_delivery_unique');
        });
        Schema::create('verification_records', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 30);
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('listing_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('checked_by')->constrained('users')->restrictOnDelete();
            $t->string('evidence_reference', 255);
            $t->string('verified_value', 64)->nullable();
            $t->string('status', 20);
            $t->dateTime('checked_at');
            $t->dateTime('expires_at')->nullable();
            $t->timestamps();
            $t->unique(['kind', 'user_id']);
            $t->unique(['kind', 'business_id']);
            $t->unique(['kind', 'listing_id']);
        });
        Schema::create('listing_promotions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('listing_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('part_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->foreignId('business_branch_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('status', 20)->default('pending');
            $t->uuid('client_request_id')->nullable();
            $t->string('request_hash', 64)->nullable();
            $t->unique(['user_id', 'client_request_id']);
            $t->char('currency', 3);
            $t->decimal('total', 16, 2);
            $t->string('fulfillment', 10);
            $t->string('recipient_name', 100);
            $t->string('contact_phone', 20);
            $t->text('delivery_address')->nullable();
            $t->dateTime('expires_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'expires_at']);
        });
        Schema::create('part_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('part_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('listing_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->decimal('unit_price', 16, 2);
            $t->json('snapshot');
            $t->unique(['part_order_id', 'listing_id']);
        });
        Schema::create('rental_reservations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('listing_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('source', 20)->default('booking');
            $t->uuid('client_request_id')->nullable();
            $t->string('request_hash', 64)->nullable();
            $t->unique(['user_id', 'client_request_id']);
            $t->string('status', 20)->default('pending');
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->dateTime('expires_at')->nullable();
            $t->json('snapshot')->nullable();
            $t->text('private_note')->nullable();
            $t->timestamps();
            $t->index(['listing_id', 'starts_at', 'ends_at'], 'rental_interval_index');
            $t->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        foreach (['rental_reservations', 'part_order_items', 'part_orders', 'listing_promotions', 'verification_records', 'push_deliveries', 'push_devices', 'marketplace_notifications', 'saved_search_matches', 'saved_searches', 'business_inquiries', 'part_quotes', 'part_requests'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('part_details', fn (Blueprint $t) => $t->dropColumn(['delivery_available', 'pickup_available', 'delivery_terms', 'warranty_months', 'return_days', 'return_terms', 'stock_updated_at', 'stock_version']));
        Schema::table('listings', function (Blueprint $t) {
            foreach (['vehicle_variant_id', 'part_product_id', 'business_branch_id'] as $column) {
                $t->dropConstrainedForeignId($column);
            }
        });
        foreach (['part_fitments', 'part_products', 'business_members', 'business_branches', 'business_sections', 'garage_vehicles', 'vehicle_variants', 'vehicle_generations', 'vehicle_models', 'vehicle_makes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
