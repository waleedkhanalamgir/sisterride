<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GroupMember;
use App\Models\MatchGroup;
use App\Models\RateCard;
use App\Models\RideRequest;
use App\Services\FareSplitter;
use App\Services\Geo;
use App\Services\MatchingService;
use App\Services\RidePresenter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RideRequestController extends Controller
{
    private const MAX_DAYS_AHEAD = 7;

    /** Price preview before she requests: distance, solo fare and what sharing would cost per woman. */
    public function preview(Request $request, FareSplitter $splitter): JsonResponse
    {
        $this->guardPassenger($request);
        $data = $request->validate($this->placeRules() + ['mode' => ['required', Rule::in(['bike', 'car_rickshaw'])]]);

        $km = round(Geo::roadKm([$data['pickup_lat'], $data['pickup_lng']], [$data['dropoff_lat'], $data['dropoff_lng']]), 2);
        $card = RateCard::where('mode', $data['mode'])->where('active', true)->firstOrFail();
        $total = $splitter->totalFare((float) $card->base_fare, (float) $card->per_km, $km);

        $perPerson = [1 => $total];
        if ($data['mode'] === 'car_rickshaw') {
            foreach ([2, 3, 4] as $n) {
                $perPerson[$n] = (int) round($total / $n);
            }
        }

        return response()->json(['distance_km' => $km, 'total_fare' => $total, 'per_person_by_group_size' => $perPerson]);
    }

    public function store(Request $request, MatchingService $matching): JsonResponse
    {
        $user = $this->guardPassenger($request);
        $data = $request->validate($this->placeRules() + [
            'mode' => ['required', Rule::in(['bike', 'car_rickshaw'])],
            'pickup_address' => ['required', 'string', 'max:200'],
            'dropoff_address' => ['required', 'string', 'max:200'],
            'depart_at' => ['required', 'date', 'after:now', 'before:+'.self::MAX_DAYS_AHEAD.' days'],
            'recurrence_days' => ['nullable', 'array', 'max:7'],
            'recurrence_days.*' => ['integer', 'between:1,7'],
        ]);

        $km = round(Geo::roadKm([$data['pickup_lat'], $data['pickup_lng']], [$data['dropoff_lat'], $data['dropoff_lng']]), 2);
        if ($km < 0.5) {
            return response()->json(['message' => 'Pickup and drop-off are too close together.'], 422);
        }

        $departAt = Carbon::parse($data['depart_at']);
        $clash = RideRequest::where('passenger_id', $user->id)
            ->whereIn('status', ['requested', 'matched'])
            ->whereBetween('depart_at', [$departAt->copy()->subHours(2), $departAt->copy()->addHours(2)])
            ->exists();
        if ($clash) {
            return response()->json(['message' => 'You already have a ride booked around that time.'], 422);
        }

        $ride = RideRequest::create([
            'passenger_id' => $user->id,
            'mode' => $data['mode'],
            'pickup_address' => $data['pickup_address'],
            'pickup_lat' => $data['pickup_lat'],
            'pickup_lng' => $data['pickup_lng'],
            'dropoff_address' => $data['dropoff_address'],
            'dropoff_lat' => $data['dropoff_lat'],
            'dropoff_lng' => $data['dropoff_lng'],
            'distance_km' => $km,
            'depart_at' => $departAt,
            'recurrence_days' => $data['recurrence_days'] ?? null,
            'seats' => 1,
            'status' => 'requested',
        ]);

        $matching->run($data['mode']); // try to match right away; unmatched requests keep waiting

        return response()->json(RidePresenter::forPassenger($ride->fresh()), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $rides = RideRequest::where('passenger_id', $request->user()->id)
            ->latest('depart_at')->limit(30)->get()
            ->map(fn ($r) => RidePresenter::forPassenger($r));

        return response()->json($rides);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ride = RideRequest::where('passenger_id', $request->user()->id)->findOrFail($id);

        return response()->json(RidePresenter::forPassenger($ride));
    }

    public function cancel(Request $request, int $id, MatchingService $matching): JsonResponse
    {
        $ride = RideRequest::where('passenger_id', $request->user()->id)->findOrFail($id);

        if (! in_array($ride->status, ['requested', 'matched'], true)) {
            return response()->json(['message' => 'This ride can no longer be cancelled.'], 422);
        }

        $started = GroupMember::where('ride_request_id', $ride->id)
            ->whereHas('group', fn ($q) => $q->whereIn('status', ['in_progress', 'completed']))
            ->exists();
        if ($started) {
            return response()->json(['message' => 'The trip has already started.'], 422);
        }

        $matching->cancelRequest($ride);

        return response()->json(RidePresenter::forPassenger($ride->fresh()));
    }

    private function placeRules(): array
    {
        return [
            'pickup_lat' => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng' => ['required', 'numeric', 'between:-180,180'],
            'dropoff_lat' => ['required', 'numeric', 'between:-90,90'],
            'dropoff_lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    private function guardPassenger(Request $request)
    {
        $user = $request->user();
        abort_unless($user->active_role === 'passenger' && $user->isApproved() && ! $user->is_suspended, 403,
            'Only verified passengers can request rides.');

        return $user;
    }
}
