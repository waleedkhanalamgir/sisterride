<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\MatchGroup;
use App\Services\RidePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    /** Matched groups waiting for a driver, for this provider's vehicle type, soonest first. */
    public function available(Request $request): JsonResponse
    {
        $user = $this->guardProvider($request);
        $mode = RidePresenter::modeForVehicle($user->vehicle?->type);

        $groups = MatchGroup::where('status', 'matched')
            ->whereNull('driver_id')
            ->where('mode', $mode)
            ->where('depart_at', '>', now()->subMinutes(10))
            ->where('depart_at', '<', now()->addDay())
            ->orderBy('depart_at')
            ->limit(30)
            ->get()
            ->map(fn ($g) => RidePresenter::forDriver($g));

        return response()->json($groups);
    }

    /** Groups this provider has accepted. */
    public function mine(Request $request): JsonResponse
    {
        $user = $this->guardProvider($request);

        $groups = MatchGroup::where('driver_id', $user->id)
            ->whereIn('status', ['driver_accepted', 'in_progress'])
            ->orderBy('depart_at')
            ->get()
            ->map(fn ($g) => RidePresenter::forDriver($g, true));

        return response()->json($groups);
    }

    public function accept(Request $request, int $id): JsonResponse
    {
        $user = $this->guardProvider($request);
        $vehicle = $user->vehicle;
        abort_unless($vehicle && $vehicle->status === 'approved', 403, 'Your vehicle is not approved yet.');

        $group = DB::transaction(function () use ($id, $user, $vehicle) {
            $g = MatchGroup::whereKey($id)->lockForUpdate()->firstOrFail();

            abort_if($g->status !== 'matched' || $g->driver_id !== null, 409, 'Another driver already accepted this ride.');
            abort_unless(RidePresenter::modeForVehicle($vehicle->type) === $g->mode, 403, 'This ride is not for your vehicle type.');
            abort_if($g->activeMembers()->count() > $vehicle->seats, 422, 'Your vehicle does not have enough seats for this group.');

            $clash = MatchGroup::where('driver_id', $user->id)
                ->whereIn('status', ['driver_accepted', 'in_progress'])
                ->whereBetween('depart_at', [$g->depart_at->copy()->subHours(2), $g->depart_at->copy()->addHours(2)])
                ->exists();
            abort_if($clash, 409, 'You already have a ride around that time.');

            $g->update(['driver_id' => $user->id, 'status' => 'driver_accepted']);

            return $g;
        });

        return response()->json(RidePresenter::forDriver($group->fresh(), true));
    }

    /** The driver can hand a group back before the trip starts; it becomes available to others. */
    public function release(Request $request, int $id): JsonResponse
    {
        $user = $this->guardProvider($request);

        $group = MatchGroup::where('driver_id', $user->id)->where('status', 'driver_accepted')->findOrFail($id);
        $group->update(['driver_id' => null, 'status' => 'matched']);

        return response()->json(['message' => 'Ride released.']);
    }

    private function guardProvider(Request $request)
    {
        $user = $request->user();
        abort_unless(RidePresenter::isActiveProvider($user), 403, 'Only approved providers can do this.');

        return $user;
    }
}
