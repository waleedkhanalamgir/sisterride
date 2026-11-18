<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Fare maths from the pitch deck:
 *   share_i = total_fare * distance_i / sum(distances)
 *   commission is taken out of the total fare, never added on top per passenger.
 * All money is handled in whole rupees; rounding leftovers go to the longest trip.
 */
class FareSplitter
{
    public function totalFare(float $baseFare, float $perKm, float $routeKm): int
    {
        return (int) round($baseFare + $perKm * $routeKm);
    }

    /**
     * @param  int    $totalFare      total fare in rupees
     * @param  array  $distancesKm    passenger id => km travelled by that passenger
     * @param  float  $commissionPct  e.g. 7.5 for 7.5%
     * @return array{shares: array<int|string,int>, commission: int, driver_earning: int}
     */
    public function split(int $totalFare, array $distancesKm, float $commissionPct): array
    {
        if ($totalFare < 0 || $commissionPct < 0 || $commissionPct > 100) {
            throw new InvalidArgumentException('Invalid fare or commission.');
        }
        if (count($distancesKm) === 0) {
            throw new InvalidArgumentException('At least one passenger is required.');
        }
        foreach ($distancesKm as $km) {
            if ($km <= 0) {
                throw new InvalidArgumentException('Every distance must be greater than zero.');
            }
        }

        $sum = array_sum($distancesKm);
        $shares = [];
        foreach ($distancesKm as $id => $km) {
            $shares[$id] = (int) round($totalFare * $km / $sum);
        }

        // Fix rounding so shares always add up to the total fare exactly.
        $diff = $totalFare - array_sum($shares);
        if ($diff !== 0) {
            $longest = array_keys($distancesKm, max($distancesKm))[0];
            $shares[$longest] += $diff;
        }

        $commission = (int) round($totalFare * $commissionPct / 100);

        return [
            'shares' => $shares,
            'commission' => $commission,
            'driver_earning' => $totalFare - $commission,
        ];
    }
}
