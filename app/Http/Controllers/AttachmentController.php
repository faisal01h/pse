<?php

namespace App\Http\Controllers;

use App\Models\PublicRecord;
use App\Models\RecordAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
    public function show(RecordAttachment $attachment): BinaryFileResponse
    {
        $attachment->loadMissing('record');
        $published = (int) $attachment->record?->status === PublicRecord::STATUS_PUBLISHED;

        abort_unless($published || auth()->check(), 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        $name = str_replace(['"', "\r", "\n"], '', $attachment->original_name);

        return response()->file(Storage::disk('local')->path($attachment->path), [
            'Content-Type' => $attachment->mime,
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
