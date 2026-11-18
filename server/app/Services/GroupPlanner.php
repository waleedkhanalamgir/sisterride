<?php

namespace App\Services;

/**
 * Pure matching logic (no database), so it can be tested on its own.
 *
 * Scoring formula from the pitch deck:
 *   score = 0.45 * route overlap + 0.25 * time closeness + 0.20 * pickup proximity + 0.10 * trust
 * Hard filters: same mode (handled by the caller), departure within +-15 min, pickups within 1.5 km,
 * routes going the same way, and a 20% detour cap for every woman in the group.
 *
 * A request is: ['id'=>int, 'passenger_id'=>int, 'pickup'=>[lat,lng], 'dropoff'=>[lat,lng],
 *                'depart_at'=>unix seconds, 'trust'=>0..5]
 */
class GroupPlanner
{
    public const MIN_GROUP = 2;
    public const MAX_GROUP = 4;
    public const TIME_WINDOW_MIN = 15;
    public const PICKUP_RADIUS_KM = 1.5;
    public const CORRIDOR_KM = 1.5;   // how far a pickup/drop-off may sit from the other woman's route
    public const DETOUR_CAP = 1.20;   // nobody rides more than 120% of her direct distance
    public const MIN_OVERLAP = 0.6;   // assumption: at least 60% of the shorter trip must lie on the longer route

    public const W_OVERLAP = 0.45;
    public const W_TIME = 0.25;
    public const W_PICKUP = 0.20;
    public const W_TRUST = 0.10;

    /**
     * @param  array<int,array> $requests
     * @return array<int,array>  planned groups, see routePlan() for the shape (plus 'request_ids')
     */
    public function plan(array $requests): array
    {
        usort($requests, fn ($a, $b) => [$a['depart_at'], $a['id']] <=> [$b['depart_at'], $b['id']]);

        $used = [];
        $groups = [];

        foreach ($requests as $seed) {
            if (isset($used[$seed['id']])) {
                continue;
            }

            $candidates = [];
            foreach ($requests as $other) {
                if ($other['id'] === $seed['id'] || isset($used[$other['id']])) {
                    continue;
                }
                $score = $this->pairScore($seed, $other);
                if ($score !== null) {
                    $candidates[] = ['score' => $score, 'req' => $other];
                }
            }
            usort($candidates, fn ($x, $y) => ($y['score'] <=> $x['score']) ?: ($x['req']['id'] <=> $y['req']['id']));

            $group = [$seed];
            $plan = null;
            foreach ($candidates as $cand) {
                if (count($group) >= self::MAX_GROUP) {
                    break;
                }
                $trial = array_merge($group, [$cand['req']]);
                if (! $this->allPairsCompatible($trial)) {
                    continue;
                }
                $trialPlan = $this->routePlan($trial);
                if ($trialPlan === null) {
                    continue; // somebody would be taken too far out of her way
                }
                $group = $trial;
                $plan = $trialPlan;
            }

            if ($plan !== null && count($group) >= self::MIN_GROUP) {
                $ids = array_column($group, 'id');
                foreach ($ids as $id) {
                    $used[$id] = true;
                }
                $groups[] = $plan + ['request_ids' => $ids];
            }
        }

        return $groups;
    }

    /** A group of one (bike mode: one woman, one rider). */
    public function soloPlan(array $r): array
    {
        $km = round(Geo::roadKm($r['pickup'], $r['dropoff']), 2);

        return [
            'request_ids' => [$r['id']],
            'route_km' => $km,
            'members' => [$r['id'] => ['distance_km' => $km, 'pickup_order' => 1]],
            'stops' => [
                ['type' => 'pickup', 'id' => $r['id'], 'point' => $r['pickup']],
                ['type' => 'dropoff', 'id' => $r['id'], 'point' => $r['dropoff']],
            ],
        ];
    }

    /** Score between two requests (0..1), or null when they may not share a ride. */
    public function pairScore(array $a, array $b): ?float
    {
        if ($a['passenger_id'] === $b['passenger_id']) {
            return null;
        }

        $minutes = abs($a['depart_at'] - $b['depart_at']) / 60;
        if ($minutes > self::TIME_WINDOW_MIN) {
            return null;
        }

        $pickupKm = Geo::haversineKm($a['pickup'], $b['pickup']);
        if ($pickupKm > self::PICKUP_RADIUS_KM) {
            return null;
        }

        $overlap = $this->overlap($a, $b);
        if ($overlap === null) {
            return null;
        }

        $timeCloseness = 1 - $minutes / self::TIME_WINDOW_MIN;
        $pickupProximity = 1 - $pickupKm / self::PICKUP_RADIUS_KM;
        $trust = (($a['trust'] + $b['trust']) / 2) / 5;

        return self::W_OVERLAP * $overlap
            + self::W_TIME * $timeCloseness
            + self::W_PICKUP * $pickupProximity
            + self::W_TRUST * max(0.0, min(1.0, $trust));
    }

    /**
     * How much of the shorter trip lies along the longer trip (0..1), or null if the two
     * trips are not going the same way or one strays too far from the other's route.
     */
    public function overlap(array $a, array $b): ?float
    {
        $lenA = Geo::haversineKm($a['pickup'], $a['dropoff']);
        $lenB = Geo::haversineKm($b['pickup'], $b['dropoff']);
        [$long, $short, $lenS] = $lenA >= $lenB ? [$a, $b, $lenB] : [$b, $a, $lenA];
        if ($lenS < 0.3) {
            return null; // too short to share meaningfully
        }

        $origin = $long['pickup'];
        $d = Geo::plane($long['dropoff'], $origin);
        $n = sqrt($d[0] ** 2 + $d[1] ** 2);
        if ($n < 1e-9) {
            return null;
        }
        $u = [$d[0] / $n, $d[1] / $n];

        $p1 = Geo::plane($short['pickup'], $origin);
        $p2 = Geo::plane($short['dropoff'], $origin);
        $t1 = $p1[0] * $u[0] + $p1[1] * $u[1];
        $t2 = $p2[0] * $u[0] + $p2[1] * $u[1];
        $off1 = abs($p1[0] * $u[1] - $p1[1] * $u[0]);
        $off2 = abs($p2[0] * $u[1] - $p2[1] * $u[0]);

        if ($off1 > self::CORRIDOR_KM || $off2 > self::CORRIDOR_KM) {
            return null;
        }
        if ($t1 < -self::CORRIDOR_KM || $t2 > $n + self::CORRIDOR_KM) {
            return null;
        }
        if ($t2 <= $t1) {
            return null; // going the opposite way
        }

        $overlap = min(1.0, (min($t2, $n) - max($t1, 0.0)) / $lenS);

        return $overlap >= self::MIN_OVERLAP ? $overlap : null;
    }

    private function allPairsCompatible(array $group): bool
    {
        for ($i = 0; $i < count($group); $i++) {
            for ($j = $i + 1; $j < count($group); $j++) {
                if ($this->pairScore($group[$i], $group[$j]) === null) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * One vehicle route for the group: all pickups in order along the longest trip, then all drop-offs.
     * Returns null if any woman would ride more than DETOUR_CAP times her direct distance.
     *
     * @return array{route_km: float, members: array<int,array{distance_km: float, pickup_order: int}>, stops: array}|null
     */
    public function routePlan(array $group): ?array
    {
        // The longest trip gives the direction of travel.
        usort($group, fn ($a, $b) => Geo::haversineKm($b['pickup'], $b['dropoff']) <=> Geo::haversineKm($a['pickup'], $a['dropoff']));
        $axis = $group[0];
        $d = Geo::plane($axis['dropoff'], $axis['pickup']);
        $n = sqrt($d[0] ** 2 + $d[1] ** 2);
        if ($n < 1e-9) {
            return null;
        }
        $u = [$d[0] / $n, $d[1] / $n];
        $along = function (array $p) use ($axis, $u): float {
            $q = Geo::plane($p, $axis['pickup']);

            return $q[0] * $u[0] + $q[1] * $u[1];
        };

        $pickups = $group;
        usort($pickups, fn ($a, $b) => [$along($a['pickup']), $a['id']] <=> [$along($b['pickup']), $b['id']]);
        $dropoffs = $group;
        usort($dropoffs, fn ($a, $b) => [$along($a['dropoff']), $a['id']] <=> [$along($b['dropoff']), $b['id']]);

        $stops = [];
        foreach ($pickups as $r) {
            $stops[] = ['type' => 'pickup', 'id' => $r['id'], 'point' => $r['pickup']];
        }
        foreach ($dropoffs as $r) {
            $stops[] = ['type' => 'dropoff', 'id' => $r['id'], 'point' => $r['dropoff']];
        }

        $cum = [0.0];
        for ($i = 1; $i < count($stops); $i++) {
            $cum[$i] = $cum[$i - 1] + Geo::roadKm($stops[$i - 1]['point'], $stops[$i]['point']);
        }

        $index = [];
        foreach ($stops as $i => $s) {
            $index[$s['type']][$s['id']] = $i;
        }

        $members = [];
        foreach ($pickups as $order => $r) {
            $ride = $cum[$index['dropoff'][$r['id']]] - $cum[$index['pickup'][$r['id']]];
            $direct = Geo::roadKm($r['pickup'], $r['dropoff']);
            if ($direct <= 0 || $ride > $direct * self::DETOUR_CAP) {
                return null;
            }
            $members[$r['id']] = ['distance_km' => round($ride, 2), 'pickup_order' => $order + 1];
        }

        return ['route_km' => round(end($cum), 2), 'members' => $members, 'stops' => $stops];
    }
}
