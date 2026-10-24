<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preflight before schema/data changes. Never choose which account owns an
        // ambiguous legacy number or silently remove its existing contact data.
        $phones = [];
        $updates = [];
        foreach (DB::table('users')->whereNotNull('phone')->orderBy('id')->get(['id', 'phone']) as $user) {
            $legacy = trim($user->phone);
            if (! preg_match('/^\+?[0-9\s().-]+$/D', $legacy)) {
                continue;
            }
            $digits = preg_replace('/[^0-9]/', '', $legacy);
            $normalized = strlen($digits) === 9 && ! str_starts_with($legacy, '+') ? '+998'.$digits : (preg_match('/^998[0-9]{9}$/D', $digits) ? '+'.$digits : null);
            if ($normalized === null) {
                continue; // Keep unsupported legacy contact data unverified.
            }
            if (isset($phones[$normalized])) {
                throw new RuntimeException('Seller profile migration stopped: users '.$phones[$normalized].' and '.$user->id.' share a normalized phone number. Resolve account ownership before migrating.');
            }
            $phones[$normalized] = $user->id;
            if ($normalized !== $user->phone) {
                $updates[$user->id] = $normalized;
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('seller_type', 20)->nullable();
            $table->timestamp('seller_onboarded_at')->nullable();
            $table->string('city', 100)->nullable();
        });

        DB::transaction(function () use ($updates) {
            foreach ($updates as $id => $phone) {
                DB::table('users')->where('id', $id)->update(['phone' => $phone]);
            }
        });
        // users_phone_unique already exists; canonical numbers retain that index.
    }

    public function down(): void
    {
        if (DB::table('users')->whereNull('email')->exists()) {
            throw new RuntimeException('Cannot remove phone-only seller profiles while users without email exist.');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_verified_at', 'seller_type', 'seller_onboarded_at', 'city']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
