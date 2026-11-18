<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->unique()->after('email');
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('gender')->default('female');
            // role = what the account is; active_role = which side of the app she is using now
            $table->string('role')->default('passenger');        // passenger | provider | admin
            $table->string('active_role')->default('passenger'); // passenger | provider | admin
            $table->string('provider_type')->nullable();         // bike_rider | car_driver | rickshaw_driver
            $table->string('verification_status')->default('unsubmitted'); // unsubmitted | pending | approved | rejected
            $table->decimal('trust_score', 3, 2)->default(5.00);
            $table->boolean('is_suspended')->default(false);
            $table->string('language', 5)->default('en');
            $table->string('fcm_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'phone_verified_at', 'gender', 'role', 'active_role', 'provider_type',
                'verification_status', 'trust_score', 'is_suspended', 'language', 'fcm_token',
            ]);
        });
    }
};
