<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MatchGroup;
use App\Models\RideRequest;
use App\Services\MatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $groups = MatchGroup::with(['driver:id,name,phone', 'members.passenger:id,name'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('depart_at')
            ->paginate(20);

        return response()->json([
            'waiting_requests' => RideRequest::where('status', 'requested')->count(),
            'groups' => $groups,
        ]);
    }

    /** Run matching now (the same job the scheduler runs). */
    public function runMatching(MatchingService $matching): JsonResponse
    {
        return response()->json($matching->run());
    }
}
