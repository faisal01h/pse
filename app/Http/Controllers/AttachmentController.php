<?php

namespace App\Http\Controllers;

use App\Models\BusinessAffiliation;
use App\Models\BusinessAffiliationAttachment;
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

        return $this->fileResponse($attachment->path, $attachment->original_name, $attachment->mime);
    }

    public function showAffiliation(BusinessAffiliationAttachment $attachment): BinaryFileResponse
    {
        $attachment->loadMissing('affiliation');
        $published = (int) $attachment->affiliation?->status === BusinessAffiliation::STATUS_PUBLISHED;

        abort_unless($published || auth()->check(), 404);

        return $this->fileResponse($attachment->path, $attachment->original_name, $attachment->mime);
    }

    private function fileResponse(string $path, string $originalName, string $mime): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($path), 404);

        $name = str_replace(['"', "\r", "\n"], '', $originalName);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
