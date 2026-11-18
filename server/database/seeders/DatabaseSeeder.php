<?php

namespace Database\Seeders;

use App\Models\RateCard;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Starter rate cards (rupees). Change them from the admin panel later.
        RateCard::updateOrCreate(['mode' => 'bike'], [
            'base_fare' => 40, 'per_km' => 18, 'commission_pct' => 7.5, 'active' => true,
        ]);
        RateCard::updateOrCreate(['mode' => 'car_rickshaw'], [
            'base_fare' => 80, 'per_km' => 45, 'commission_pct' => 7.5, 'active' => true,
        ]);

        // Demo admin: change the password before any real use.
        $admin = User::updateOrCreate(['phone' => '03000000000'], [
            'name' => 'SisterRide Admin',
            'email' => 'admin@sisterride.test',
            'password' => 'password123',
            'gender' => 'female',
            'role' => User::ROLE_ADMIN,
            'active_role' => User::ROLE_ADMIN,
            'verification_status' => 'approved',
        ]);
        $admin->wallet()->firstOrCreate([], ['balance' => 0]);
    }
}
