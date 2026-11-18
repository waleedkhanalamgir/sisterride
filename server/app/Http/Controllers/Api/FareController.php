<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RateCard;
use App\Services\FareSplitter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FareController extends Controller
{
    /**
     * Preview a fare before a group exists.
     * Body: { mode, route_km, passengers: [{ id, distance_km }, ...] }
     */
    public function quote(Request $request, FareSplitter $splitter): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['bike', 'car_rickshaw'])],
            'route_km' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'passengers' => ['required', 'array', 'min:1', 'max:4'],
            'passengers.*.id' => ['required'],
            'passengers.*.distance_km' => ['required', 'numeric', 'min:0.1', 'lte:route_km'],
        ]);

        $card = RateCard::where('mode', $data['mode'])->where('active', true)->firstOrFail();

        $total = $splitter->totalFare((float) $card->base_fare, (float) $card->per_km, (float) $data['route_km']);
        $distances = collect($data['passengers'])->mapWithKeys(fn ($p) => [$p['id'] => (float) $p['distance_km']])->all();

        return response()->json(
            ['mode' => $data['mode'], 'total_fare' => $total, 'commission_pct' => (float) $card->commission_pct]
            + $splitter->split($total, $distances, (float) $card->commission_pct)
        );
    }
}
