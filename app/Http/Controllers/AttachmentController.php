<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Uploads are on a private disk; this is the only way to read them, and it checks the policy each time. */
class AttachmentController extends Controller
{
    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment);

        // Staff opening a client's file leaves a trace.
        if ($attachment->user_id !== $request->user()->id) {
            Audit::log('attachment.viewed', $attachment);
        }

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
