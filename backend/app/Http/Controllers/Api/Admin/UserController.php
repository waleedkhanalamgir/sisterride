<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['nullable', Rule::in(['passenger', 'provider', 'admin'])],
            'q' => ['nullable', 'string', 'max:50'],
        ]);

        $users = User::query()
            ->when($data['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($data['q'] ?? null, function ($q, $term) {
                $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"));
            })
            ->latest()
            ->paginate(20);

        return response()->json($users);
    }

    public function setSuspended(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['suspended' => ['required', 'boolean']]);

        if ($user->id === $request->user()->id || $user->role === User::ROLE_ADMIN) {
            return response()->json(['message' => 'Admin accounts cannot be suspended here.'], 422);
        }

        $user->update(['is_suspended' => $data['suspended']]);
        if ($data['suspended']) {
            $user->tokens()->delete(); // log the user out everywhere
        }

        return response()->json($user->fresh());
    }
}
