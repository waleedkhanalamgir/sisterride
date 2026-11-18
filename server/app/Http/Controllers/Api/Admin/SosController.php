<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SosEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SosController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        return response()->json(
            SosEvent::with('user:id,name,phone')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()
                ->paginate(20)
        );
    }

    public function update(Request $request, SosEvent $sos): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['acknowledged', 'resolved'])],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $sos->update($data + ['handled_by' => $request->user()->id]);

        return response()->json($sos->fresh());
    }
}
