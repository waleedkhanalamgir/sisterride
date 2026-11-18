<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Verification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams a private verification document to an admin (never a public URL). */
class DocumentController extends Controller
{
    private const FIELDS = [
        'cnic' => 'cnic_image_path',
        'selfie' => 'selfie_path',
        'student' => 'student_id_image_path',
    ];

    public function show(Verification $verification, string $type): StreamedResponse
    {
        abort_unless(isset(self::FIELDS[$type]), 404);

        $path = $verification->{self::FIELDS[$type]};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
