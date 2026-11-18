<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'passengers' => User::where('role', 'passenger')->count(),
            'providers' => User::where('role', 'provider')->count(),
            'pending_verifications' => DB::table('verifications')->where('status', 'pending')->count(),
            'open_sos' => DB::table('sos_events')->whereIn('status', ['new', 'acknowledged'])->count(),
            'groups_total' => DB::table('match_groups')->count(),
            'trips_completed' => DB::table('trips')->where('status', 'completed')->count(),
            'suspended_users' => User::where('is_suspended', true)->count(),
        ]);
    }
}
