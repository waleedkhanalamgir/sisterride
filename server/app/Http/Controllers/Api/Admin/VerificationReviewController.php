<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\Verification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationReviewController extends Controller
{
    public function pending(Request $request): JsonResponse
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true)
            ? $request->query('status') : 'pending';

        return response()->json(
            Verification::with('user:id,name,phone,role,provider_type,gender')
                ->where('status', $status)->oldest()->paginate(20)
        );
    }

    public function approve(Request $request, Verification $verification): JsonResponse
    {
        $verification->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);
        $verification->user->update(['verification_status' => 'approved']);

        // Providers are approved together with their vehicle in this MVP.
        Vehicle::where('user_id', $verification->user_id)->update(['status' => 'approved']);

        return response()->json($verification->fresh());
    }

    public function reject(Request $request, Verification $verification): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);

        $verification->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $data['reason'],
        ]);
        $verification->user->update(['verification_status' => 'rejected']);

        return response()->json($verification->fresh());
    }
}
