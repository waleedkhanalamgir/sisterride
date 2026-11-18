<?php

namespace App\Services;

use App\Models\GroupMember;
use App\Models\MatchGroup;
use App\Models\RateCard;
use App\Models\RideRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Saves what GroupPlanner decides: groups, fare shares and boarding PINs. */
class MatchingService
{
    public function __construct(
        private GroupPlanner $planner,
        private FareSplitter $splitter,
    ) {
    }

    /**
     * Match all waiting requests (optionally only one mode).
     *
     * @return array{expired:int, groups_created:int, requests_matched:int}
     */
    public function run(?string $mode = null): array
    {
        $stats = ['expired' => 0, 'groups_created' => 0, 'requests_matched' => 0];

        DB::transaction(function () use (&$stats, $mode) {
            // Requests whose departure time has passed without a match are closed.
            $stats['expired'] = RideRequest::where('status', 'requested')
                ->where('depart_at', '<', now())
                ->when($mode, fn ($q) => $q->where('mode', $mode))
                ->update(['status' => 'no_match']);

            foreach (['bike', 'car_rickshaw'] as $m) {
                if ($mode !== null && $mode !== $m) {
                    continue;
                }
                $card = RateCard::where('mode', $m)->where('active', true)->first();
                if (! $card) {
                    continue;
                }

                $pending = RideRequest::with('passenger:id,trust_score,is_suspended,verification_status')
                    ->where('status', 'requested')
                    ->where('mode', $m)
                    ->orderBy('depart_at')
                    ->lockForUpdate()
                    ->get()
                    ->filter(fn ($r) => $r->passenger && ! $r->passenger->is_suspended && $r->passenger->verification_status === 'approved')
                    ->keyBy('id');

                if ($pending->isEmpty()) {
                    continue;
                }

                $inputs = $pending->map(fn ($r) => [
                    'id' => $r->id,
                    'passenger_id' => $r->passenger_id,
                    'pickup' => [$r->pickup_lat, $r->pickup_lng],
                    'dropoff' => [$r->dropoff_lat, $r->dropoff_lng],
                    'depart_at' => $r->depart_at->timestamp,
                    'trust' => (float) $r->passenger->trust_score,
                ])->values()->all();

                // Bike mode: one woman, one rider. Car / rickshaw: groups of 2-4 women.
                $plans = $m === 'bike'
                    ? array_map(fn ($r) => $this->planner->soloPlan($r), $inputs)
                    : $this->planner->plan($inputs);

                foreach ($plans as $plan) {
                    $this->saveGroup($plan, $pending, $card, $m);
                    $stats['groups_created']++;
                    $stats['requests_matched'] += count($plan['request_ids']);
                }
            }
        });

        return $stats;
    }

    /** Cancel one request. Her group is dissolved and the other women go back to waiting. */
    public function cancelRequest(RideRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $request->update(['status' => 'cancelled']);

            $member = GroupMember::where('ride_request_id', $request->id)->where('status', '!=', 'cancelled')->first();
            if (! $member) {
                return;
            }

            $group = MatchGroup::whereKey($member->match_group_id)->lockForUpdate()->first();
            $others = GroupMember::where('match_group_id', $group->id)->where('id', '!=', $member->id)->get();

            GroupMember::where('match_group_id', $group->id)->update(['status' => 'cancelled']);
            $group->update(['status' => 'cancelled']);
            RideRequest::whereIn('id', $others->pluck('ride_request_id'))
                ->where('status', 'matched')
                ->update(['status' => 'requested']);
        });

        $this->run($request->mode); // the other women may be matched into a new group straight away
    }

    private function saveGroup(array $plan, Collection $requests, RateCard $card, string $mode): void
    {
        $total = $this->splitter->totalFare((float) $card->base_fare, (float) $card->per_km, $plan['route_km']);
        $distances = array_map(fn ($m) => max(0.1, $m['distance_km']), $plan['members']);
        $split = $this->splitter->split($total, $distances, (float) $card->commission_pct);

        $stops = array_map(function ($s) use ($requests) {
            $r = $requests[$s['id']];
            $isPickup = $s['type'] === 'pickup';

            return [
                'type' => $s['type'],
                'request_id' => $r->id,
                'address' => $isPickup ? $r->pickup_address : $r->dropoff_address,
                'lat' => $s['point'][0],
                'lng' => $s['point'][1],
            ];
        }, $plan['stops']);

        $departAt = Carbon::createFromTimestamp(
            min(array_map(fn ($id) => $requests[$id]->depart_at->timestamp, $plan['request_ids']))
        );

        $group = MatchGroup::create([
            'mode' => $mode,
            'status' => 'matched',
            'total_distance_km' => $plan['route_km'],
            'total_fare' => $total,
            'commission_amount' => $split['commission'],
            'planned_route' => ['route_km' => $plan['route_km'], 'stops' => $stops],
            'depart_at' => $departAt,
        ]);

        foreach ($plan['members'] as $requestId => $m) {
            $r = $requests[$requestId];
            GroupMember::create([
                'match_group_id' => $group->id,
                'passenger_id' => $r->passenger_id,
                'ride_request_id' => $r->id,
                'pickup_order' => $m['pickup_order'],
                'distance_km' => $m['distance_km'],
                'fare_share' => $split['shares'][$requestId],
                'boarding_pin' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            ]);
        }

        RideRequest::whereIn('id', $plan['request_ids'])->update(['status' => 'matched']);
    }
}
