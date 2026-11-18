<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    private const TYPES_BY_PROVIDER = [
        'bike_rider' => ['bike', 'scooty'],
        'car_driver' => ['car'],
        'rickshaw_driver' => ['rickshaw'],
    ];

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isProvider()) {
            return response()->json(['message' => 'Only providers can add a vehicle.'], 403);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(self::TYPES_BY_PROVIDER[$user->provider_type] ?? [])],
            'plate_number' => ['required', 'string', 'max:20', 'unique:vehicles,plate_number'],
            'make_model' => ['nullable', 'string', 'max:80'],
            'seats' => ['nullable', 'integer', 'min:1', 'max:4'],
            'license_image' => ['required', 'image', 'max:4096'],
            'registration_image' => ['nullable', 'image', 'max:4096'],
            'photo' => ['required', 'image', 'max:4096'],
        ]);

        $dir = 'vehicles/'.$user->id;
        $vehicle = $user->vehicle()->create([
            'type' => $data['type'],
            'plate_number' => strtoupper($data['plate_number']),
            'make_model' => $data['make_model'] ?? null,
            // passenger seats: bike/scooty 1, rickshaw 3, car 4 (unless the driver says otherwise)
            'seats' => $data['seats'] ?? match ($data['type']) {
                'bike', 'scooty' => 1,
                'rickshaw' => 3,
                default => 4,
            },
            'license_image_path' => $request->file('license_image')->store($dir, 'local'),
            'registration_image_path' => $request->hasFile('registration_image')
                ? $request->file('registration_image')->store($dir, 'local') : null,
            'photo_path' => $request->file('photo')->store($dir, 'local'),
        ]);

        return response()->json($vehicle, 201);
    }
}
