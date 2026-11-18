<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^(\+92|0)3\d{9}$/', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'gender' => ['required', Rule::in(['female', 'male'])],
            'role' => ['required', Rule::in([User::ROLE_PASSENGER, User::ROLE_PROVIDER])],
            'provider_type' => ['required_if:role,provider', 'nullable',
                Rule::in(['bike_rider', 'car_driver', 'rickshaw_driver'])],
            'language' => ['nullable', Rule::in(['en', 'ur'])],
        ]);

        // Safety rules from the pitch deck: passengers and bike riders must be women.
        // Male drivers are allowed only in car / rickshaw mode.
        if ($data['role'] === User::ROLE_PASSENGER && $data['gender'] !== 'female') {
            throw ValidationException::withMessages(['gender' => 'SisterRide passengers must be women.']);
        }
        if (($data['provider_type'] ?? null) === 'bike_rider' && $data['gender'] !== 'female') {
            throw ValidationException::withMessages(['gender' => 'Bike riders must be women.']);
        }

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'gender' => $data['gender'],
                'role' => $data['role'],
                'active_role' => $data['role'],
                'provider_type' => $data['provider_type'] ?? null,
                'language' => $data['language'] ?? 'en',
            ]);
            $user->wallet()->create(['balance' => 0]);

            return $user;
        });

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('mobile')->plainTextToken,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],      // phone or email
            'password' => ['required', 'string'],
        ]);

        $user = User::where('phone', $data['login'])->orWhere('email', $data['login'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['login' => 'Wrong phone/email or password.']);
        }
        if ($user->is_suspended) {
            return response()->json(['message' => 'This account is suspended.'], 403);
        }

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('mobile')->plainTextToken,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load(['verification', 'vehicle', 'trustedContacts', 'wallet']));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /** One app, two sides: an approved provider can switch between rider and passenger views. */
    public function switchRole(Request $request): JsonResponse
    {
        $data = $request->validate(['active_role' => ['required', Rule::in(['passenger', 'provider'])]]);
        $user = $request->user();

        if ($data['active_role'] === 'provider' && (! $user->isProvider() || ! $user->isApproved())) {
            return response()->json(['message' => 'Only approved providers can use the provider side.'], 403);
        }

        $user->update(['active_role' => $data['active_role']]);

        return response()->json($user->fresh());
    }
}
