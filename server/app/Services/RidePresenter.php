<?php

namespace App\Services;

use App\Models\GroupMember;
use App\Models\MatchGroup;
use App\Models\RideRequest;
use App\Models\User;

/** Shapes ride data for each audience. Passengers see first names only; PINs only for their own seat. */
class RidePresenter
{
    public static function firstName(?string $name): string
    {
        return $name ? explode(' ', trim($name))[0] : 'Rider';
    }

    /** What a passenger sees about her own request. */
    public static function forPassenger(RideRequest $r): array
    {
        $data = [
            'id' => $r->id,
            'mode' => $r->mode,
            'status' => $r->status,
            'pickup_address' => $r->pickup_address,
            'pickup_lat' => $r->pickup_lat,
            'pickup_lng' => $r->pickup_lng,
            'dropoff_address' => $r->dropoff_address,
            'dropoff_lat' => $r->dropoff_lat,
            'dropoff_lng' => $r->dropoff_lng,
            'distance_km' => $r->distance_km,
            'depart_at' => $r->depart_at?->toIso8601String(),
            'recurrence_days' => $r->recurrence_days,
            'group' => null,
        ];

        $member = GroupMember::with(['group.driver.vehicle', 'group.activeMembers.passenger:id,name,trust_score'])
            ->where('ride_request_id', $r->id)
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($member && $member->group) {
            $g = $member->group;
            $accepted = in_array($g->status, ['driver_accepted', 'in_progress', 'completed'], true);

            $data['group'] = [
                'id' => $g->id,
                'status' => $g->status,
                'my_fare_share' => $member->fare_share,
                'my_distance_km' => $member->distance_km,
                'pickup_order' => $member->pickup_order,
                'total_fare' => $g->total_fare,
                'boarding_pin' => $accepted ? $member->boarding_pin : null,
                'co_riders' => $g->activeMembers
                    ->where('passenger_id', '!=', $member->passenger_id)
                    ->map(fn ($m) => [
                        'name' => self::firstName($m->passenger?->name),
                        'trust_score' => $m->passenger?->trust_score,
                    ])->values(),
                'driver' => ($accepted && $g->driver) ? [
                    'name' => self::firstName($g->driver->name),
                    'trust_score' => $g->driver->trust_score,
                    'vehicle_type' => $g->driver->vehicle?->type,
                    'plate_number' => $g->driver->vehicle?->plate_number,
                    'make_model' => $g->driver->vehicle?->make_model,
                ] : null,
            ];
        }

        return $data;
    }

    /** A group as a driver sees it before and after accepting. No passenger names or PINs before accepting. */
    public static function forDriver(MatchGroup $g, bool $detailed = false): array
    {
        $stops = array_map(fn ($s) => [
            'type' => $s['type'],
            'address' => $s['address'],
            'lat' => $s['lat'],
            'lng' => $s['lng'],
        ], $g->planned_route['stops'] ?? []);

        $data = [
            'id' => $g->id,
            'mode' => $g->mode,
            'status' => $g->status,
            'depart_at' => $g->depart_at?->toIso8601String(),
            'route_km' => $g->total_distance_km,
            'total_fare' => $g->total_fare,
            'commission' => $g->commission_amount,
            'driver_earning' => round($g->total_fare - $g->commission_amount, 2),
            'passenger_count' => $g->activeMembers()->count(),
            'stops' => $stops,
        ];

        if ($detailed) {
            $data['passengers'] = $g->activeMembers()->with('passenger:id,name,trust_score')->get()
                ->map(fn ($m) => [
                    'name' => self::firstName($m->passenger?->name),
                    'trust_score' => $m->passenger?->trust_score,
                    'pickup_order' => $m->pickup_order,
                ])->values();
        }

        return $data;
    }

    public static function modeForVehicle(?string $vehicleType): ?string
    {
        return match ($vehicleType) {
            'bike', 'scooty' => 'bike',
            'car', 'rickshaw' => 'car_rickshaw',
            default => null,
        };
    }

    public static function isActiveProvider(User $u): bool
    {
        return $u->isProvider() && $u->active_role === 'provider' && $u->isApproved() && ! $u->is_suspended;
    }
}
