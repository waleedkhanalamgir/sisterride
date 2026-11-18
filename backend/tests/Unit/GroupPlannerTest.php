<?php

namespace Tests\Unit;

use App\Services\FareSplitter;
use App\Services\Geo;
use App\Services\GroupPlanner;
use PHPUnit\Framework\TestCase;

/**
 * Sample coordinates are made up points along a south-west corridor in a city (roughly 12 km long),
 * not real addresses.
 */
class GroupPlannerTest extends TestCase
{
    private const T0 = 1800000000; // an arbitrary departure time (unix seconds)

    private function req(int $id, array $pickup, array $dropoff, int $minutesOffset = 0, ?int $passengerId = null, float $trust = 5.0): array
    {
        return [
            'id' => $id,
            'passenger_id' => $passengerId ?? $id,
            'pickup' => $pickup,
            'dropoff' => $dropoff,
            'depart_at' => self::T0 + $minutesOffset * 60,
            'trust' => $trust,
        ];
    }

    private function corridorRequests(): array
    {
        return [
            $this->req(1, [31.4800, 74.3000], [31.3900, 74.2200]),
            $this->req(2, [31.4750, 74.2950], [31.4000, 74.2300], 5),
            $this->req(3, [31.4720, 74.2900], [31.3950, 74.2250], -4),
        ];
    }

    public function test_three_women_on_the_same_corridor_share_one_group(): void
    {
        $groups = (new GroupPlanner())->plan($this->corridorRequests());

        $this->assertCount(1, $groups);
        $this->assertEqualsCanonicalizing([1, 2, 3], $groups[0]['request_ids']);
        $this->assertCount(6, $groups[0]['stops']);
        // all pickups come before all drop-offs
        $this->assertSame(['pickup', 'pickup', 'pickup', 'dropoff', 'dropoff', 'dropoff'], array_column($groups[0]['stops'], 'type'));
    }

    public function test_nobody_exceeds_the_detour_cap(): void
    {
        $reqs = $this->corridorRequests();
        $group = (new GroupPlanner())->plan($reqs)[0];

        foreach ($reqs as $r) {
            $direct = Geo::roadKm($r['pickup'], $r['dropoff']);
            $this->assertLessThanOrEqual($direct * GroupPlanner::DETOUR_CAP + 0.01, $group['members'][$r['id']]['distance_km']);
        }
    }

    public function test_fare_shares_add_up_for_a_planned_group(): void
    {
        $group = (new GroupPlanner())->plan($this->corridorRequests())[0];
        $splitter = new FareSplitter();
        $total = $splitter->totalFare(80, 45, $group['route_km']);
        $split = $splitter->split($total, array_map(fn ($m) => $m['distance_km'], $group['members']), 7.5);

        $this->assertSame($total, array_sum($split['shares']));
        $this->assertSame($total, $split['commission'] + $split['driver_earning']);
    }

    public function test_opposite_directions_are_not_matched(): void
    {
        $groups = (new GroupPlanner())->plan([
            $this->req(1, [31.4800, 74.3000], [31.3900, 74.2200]),
            $this->req(2, [31.4790, 74.2990], [31.5100, 74.3300]), // heading the other way
        ]);

        $this->assertSame([], $groups);
    }

    public function test_departures_more_than_15_minutes_apart_are_not_matched(): void
    {
        $groups = (new GroupPlanner())->plan([
            $this->req(1, [31.4800, 74.3000], [31.3900, 74.2200]),
            $this->req(2, [31.4750, 74.2950], [31.4000, 74.2300], 40),
        ]);

        $this->assertSame([], $groups);
    }

    public function test_pickups_more_than_1_5_km_apart_are_not_matched(): void
    {
        $groups = (new GroupPlanner())->plan([
            $this->req(1, [31.4800, 74.3000], [31.3900, 74.2200]),
            $this->req(2, [31.4500, 74.2700], [31.3950, 74.2250]), // starts about 4 km further along
        ]);

        $this->assertSame([], $groups);
    }

    public function test_the_same_woman_is_never_matched_with_herself(): void
    {
        $groups = (new GroupPlanner())->plan([
            $this->req(1, [31.4800, 74.3000], [31.3900, 74.2200], 0, 9),
            $this->req(2, [31.4790, 74.2990], [31.3900, 74.2200], 3, 9),
        ]);

        $this->assertSame([], $groups);
    }

    public function test_a_group_never_has_more_than_four_women(): void
    {
        $reqs = [];
        for ($i = 1; $i <= 6; $i++) {
            $reqs[] = $this->req($i, [31.4800 - $i * 0.0005, 74.3000 - $i * 0.0005], [31.3900, 74.2200], $i);
        }

        $groups = (new GroupPlanner())->plan($reqs);

        foreach ($groups as $g) {
            $this->assertLessThanOrEqual(4, count($g['request_ids']));
        }
        $matched = array_merge(...array_column($groups, 'request_ids'));
        $this->assertSame(array_values(array_unique($matched)), $matched, 'no request is in two groups');
    }

    public function test_higher_trust_and_closer_time_score_higher(): void
    {
        $p = new GroupPlanner();
        $base = $this->req(1, [31.4800, 74.3000], [31.3900, 74.2200]);
        $near = $this->req(2, [31.4790, 74.2990], [31.3900, 74.2200], 1, null, 5.0);
        $far = $this->req(3, [31.4790, 74.2990], [31.3900, 74.2200], 14, null, 2.0);

        $this->assertGreaterThan($p->pairScore($base, $far), $p->pairScore($base, $near));
    }

    public function test_solo_plan_for_bike_mode(): void
    {
        $plan = (new GroupPlanner())->soloPlan($this->req(7, [31.4800, 74.3000], [31.4000, 74.2300]));

        $this->assertSame([7], $plan['request_ids']);
        $this->assertSame($plan['route_km'], $plan['members'][7]['distance_km']);
    }
}
