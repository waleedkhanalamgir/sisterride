<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrustedContactController extends Controller
{
    private const MAX_CONTACTS = 3;

    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->trustedContacts);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->trustedContacts()->count() >= self::MAX_CONTACTS) {
            return response()->json(['message' => 'You can add up to '.self::MAX_CONTACTS.' trusted contacts.'], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^(\+92|0)3\d{9}$/'],
            'relation' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json($user->trustedContacts()->create($data), 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->trustedContacts()->findOrFail($id)->delete();

        return response()->json(['message' => 'Contact removed.']);
    }
}
