<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status' => $user->verification_status,
            'verification' => $user->verification,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->verification_status === 'pending' || $user->verification_status === 'approved') {
            return response()->json(['message' => 'Verification already '.$user->verification_status.'.'], 409);
        }

        $data = $request->validate([
            'cnic_number' => ['required', 'regex:/^\d{5}-\d{7}-\d$/'],
            'cnic_image' => ['required', 'image', 'max:4096'],
            'selfie' => ['required', 'image', 'max:4096'],
            'student_id_image' => ['nullable', 'image', 'max:4096'],
        ]);

        // Identity documents go to the private disk (not publicly downloadable).
        $dir = 'verifications/'.$user->id;
        $verification = $user->verification()->create([
            'cnic_number' => $data['cnic_number'],
            'cnic_image_path' => $request->file('cnic_image')->store($dir, 'local'),
            'selfie_path' => $request->file('selfie')->store($dir, 'local'),
            'student_id_image_path' => $request->hasFile('student_id_image')
                ? $request->file('student_id_image')->store($dir, 'local')
                : null,
        ]);

        $user->update(['verification_status' => 'pending']);

        return response()->json($verification, 201);
    }
}
