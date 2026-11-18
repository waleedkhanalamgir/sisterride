<?php

namespace Database\Seeders;

use App\Models\RideRequest;
use App\Models\User;
use App\Services\MatchingService;
use Illuminate\Database\Seeder;

/**
 * Demo data for trying out matching:  php artisan db:seed --class=DemoSeeder
 * Creates 3 approved women on one corridor wanting a car / rickshaw in 30-35 minutes, plus an approved
 * car driver (password for all: password123). The coordinates are sample points, not real addresses.
 * Change them to roads in your own city if you want a more realistic demo.
 */
class DemoSeeder extends Seeder
{
    public function run(MatchingService $matching): void
    {
        $this->call(DatabaseSeeder::class); // rate cards and admin

        $women = [
            ['Ayesha Khan',  '03001110001', [31.4800, 74.3000], [31.3900, 74.2200], 30],
            ['Hira Malik',   '03001110002', [31.4750, 74.2950], [31.4000, 74.2300], 35],
            ['Sana Iqbal',   '03001110003', [31.4720, 74.2900], [31.3950, 74.2250], 26],
        ];

        foreach ($women as [$name, $phone, $from, $to, $minutes]) {
            $u = User::updateOrCreate(['phone' => $phone], [
                'name' => $name, 'password' => 'password123', 'gender' => 'female',
                'role' => 'passenger', 'active_role' => 'passenger', 'verification_status' => 'approved',
            ]);
            $u->wallet()->firstOrCreate([], ['balance' => 0]);

            RideRequest::updateOrCreate(['passenger_id' => $u->id, 'status' => 'requested'], [
                'mode' => 'car_rickshaw',
                'pickup_address' => 'Sample pickup ('.$from[0].', '.$from[1].')',
                'pickup_lat' => $from[0], 'pickup_lng' => $from[1],
                'dropoff_address' => 'Sample drop-off ('.$to[0].', '.$to[1].')',
                'dropoff_lat' => $to[0], 'dropoff_lng' => $to[1],
                'distance_km' => round(\App\Services\Geo::roadKm($from, $to), 2),
                'depart_at' => now()->addMinutes($minutes),
                'seats' => 1,
            ]);
        }

        $driver = User::updateOrCreate(['phone' => '03001110009'], [
            'name' => 'Imran Driver', 'password' => 'password123', 'gender' => 'male',
            'role' => 'provider', 'active_role' => 'provider', 'provider_type' => 'car_driver',
            'verification_status' => 'approved',
        ]);
        $driver->wallet()->firstOrCreate([], ['balance' => 0]);
        $driver->vehicle()->firstOrCreate(['plate_number' => 'DEMO-123'], [
            'type' => 'car', 'make_model' => 'Suzuki Cultus', 'seats' => 4,
            'license_image_path' => 'demo/none', 'photo_path' => 'demo/none', 'status' => 'approved',
        ]);

        $stats = $matching->run('car_rickshaw');
        $this->command?->info("Demo ready. Matching: {$stats['groups_created']} group(s), {$stats['requests_matched']} request(s) matched.");
        $this->command?->info('Log in as 03001110001 (passenger) or 03001110009 (driver); password: password123');
    }
}
