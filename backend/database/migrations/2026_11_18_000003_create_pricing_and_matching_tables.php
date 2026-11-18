<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_cards', function (Blueprint $table) {
            $table->id();
            $table->string('mode')->unique();            // bike | car_rickshaw
            $table->decimal('base_fare', 8, 2);
            $table->decimal('per_km', 8, 2);
            $table->decimal('commission_pct', 4, 2);     // e.g. 7.50 means 7.5%
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Coordinates are plain decimals so this runs on SQLite and PostgreSQL.
        // Switch to PostGIS geography columns later for faster matching queries.
        Schema::create('ride_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passenger_id')->constrained('users')->cascadeOnDelete();
            $table->string('mode');                      // bike | car_rickshaw
            $table->string('pickup_address');
            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);
            $table->string('dropoff_address');
            $table->decimal('dropoff_lat', 10, 7);
            $table->decimal('dropoff_lng', 10, 7);
            $table->decimal('distance_km', 6, 2);
            $table->dateTime('depart_at');
            $table->json('recurrence_days')->nullable(); // e.g. [1,2,3,4,5] for Mon-Fri
            $table->unsignedTinyInteger('seats')->default(1);
            $table->string('status')->default('requested'); // requested | matched | cancelled | no_match | completed
            $table->timestamps();
            $table->index(['mode', 'status', 'depart_at']);
        });

        Schema::create('match_groups', function (Blueprint $table) {
            $table->id();
            $table->string('mode');
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('matched'); // matched | driver_accepted | in_progress | completed | cancelled
            $table->decimal('total_distance_km', 6, 2)->default(0);
            $table->decimal('total_fare', 8, 2)->default(0);
            $table->decimal('commission_amount', 8, 2)->default(0);
            $table->json('planned_route')->nullable();
            $table->dateTime('depart_at');
            $table->timestamps();
        });

        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('passenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ride_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('pickup_order')->default(1);
            $table->decimal('distance_km', 6, 2);
            $table->decimal('fare_share', 8, 2);
            $table->string('boarding_pin', 4);
            $table->string('status')->default('waiting'); // waiting | picked_up | dropped_off | cancelled
            $table->timestamps();
            $table->unique(['match_group_id', 'passenger_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('match_groups');
        Schema::dropIfExists('ride_requests');
        Schema::dropIfExists('rate_cards');
    }
};
