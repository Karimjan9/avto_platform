<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->json('deal_terms')->nullable();
        });
        Schema::table('car_details', function (Blueprint $table) {
            $table->string('generation', 80)->nullable();
            $table->string('trim', 120)->nullable();
            $table->string('technical_condition', 20)->nullable();
            $table->string('gas_equipment', 10)->nullable();
            $table->decimal('power_kw', 8, 2)->nullable();
            $table->decimal('battery_capacity_kwh', 8, 2)->nullable();
            $table->unsignedSmallInteger('range_km')->nullable();
            $table->json('charging_types')->nullable();
            $table->decimal('battery_health_percent', 5, 2)->nullable();
        });
        Schema::table('part_details', function (Blueprint $table) {
            $table->string('category', 30)->nullable()->index();
            $table->string('oem_number', 80)->nullable()->index();
            $table->string('origin', 20)->nullable();
            $table->string('unit', 10)->nullable();
            $table->unsignedSmallInteger('quantity_per_set')->nullable();
            $table->unsignedInteger('stock_quantity')->nullable();
            $table->json('compatible_vehicles')->nullable();
            $table->unsignedSmallInteger('tyre_width_mm')->nullable();
            $table->unsignedTinyInteger('tyre_aspect_ratio')->nullable();
            $table->unsignedTinyInteger('tyre_diameter_inch')->nullable();
            $table->string('tyre_season', 20)->nullable();
            $table->date('manufactured_at')->nullable();
            $table->string('side', 10)->nullable();
            $table->string('position', 10)->nullable();
        });
        Schema::table('moto_details', function (Blueprint $table) {
            $table->string('transmission', 20)->nullable();
            $table->boolean('abs')->nullable();
            $table->decimal('power_kw', 8, 2)->nullable();
            $table->decimal('battery_voltage_v', 7, 2)->nullable();
            $table->decimal('battery_capacity_ah', 8, 2)->nullable();
            $table->unsignedSmallInteger('range_km')->nullable();
        });
        Schema::create('rental_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('daily_price', 16, 2)->nullable();
            $table->decimal('weekly_price', 16, 2)->nullable();
            $table->decimal('monthly_price', 16, 2)->nullable();
            $table->decimal('deposit', 16, 2);
            $table->unsignedSmallInteger('min_days');
            $table->unsignedInteger('daily_mileage_limit_km')->nullable();
            $table->string('driver_option', 20);
        });
        DB::table('listings')->update(['deal_terms' => json_encode(['cash'])]);
        DB::table('part_details')->update(['category' => 'other', 'unit' => 'piece', 'stock_quantity' => 0]);
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_details');
        Schema::table('moto_details', fn (Blueprint $table) => $table->dropColumn(['transmission', 'abs', 'power_kw', 'battery_voltage_v', 'battery_capacity_ah', 'range_km']));
        Schema::table('part_details', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['oem_number']);
            $table->dropColumn(['category', 'oem_number', 'origin', 'unit', 'quantity_per_set', 'stock_quantity', 'compatible_vehicles', 'tyre_width_mm', 'tyre_aspect_ratio', 'tyre_diameter_inch', 'tyre_season', 'manufactured_at', 'side', 'position']);
        });
        Schema::table('car_details', fn (Blueprint $table) => $table->dropColumn(['generation', 'trim', 'technical_condition', 'gas_equipment', 'power_kw', 'battery_capacity_kwh', 'range_km', 'charging_types', 'battery_health_percent']));
        Schema::table('listings', fn (Blueprint $table) => $table->dropColumn('deal_terms'));
    }
};
