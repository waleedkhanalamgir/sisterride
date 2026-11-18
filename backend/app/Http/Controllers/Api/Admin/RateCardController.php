<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RateCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RateCardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(RateCard::orderBy('mode')->get());
    }

    public function update(Request $request, RateCard $rateCard): JsonResponse
    {
        $data = $request->validate([
            'base_fare' => ['required', 'numeric', 'min:0', 'max:5000'],
            'per_km' => ['required', 'numeric', 'min:0', 'max:1000'],
            'commission_pct' => ['required', 'numeric', 'min:0', 'max:30'],
            'active' => ['required', 'boolean'],
        ]);

        $rateCard->update($data);

        return response()->json($rateCard->fresh());
    }
}
