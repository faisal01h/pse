<?php

namespace App\Http\Controllers;

use App\Catalog\ReliabilityLevel;
use App\Models\PublicRecord;
use App\Models\RecordAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicRecordController extends Controller
{
    public function apiIndex(Request $request, PoliticianController $politicians): JsonResponse
    {
        $partyId = $request->filled('party') ? $request->integer('party') : null;

        return response()->json(
            $politicians->publishedReports($partyId)
        );
    }

    public function show(PublicRecord $record): Response
    {
        $record->loadMissing(['politician.party', 'attachments', 'topics']);

        abort_unless($this->isPubliclyVisible($record), 404);

        return Inertia::render('PublicRecord', [
            'record' => $this->present($record),
        ]);
    }

    public function apiShow(PublicRecord $record): JsonResponse
    {
        $record->loadMissing(['politician.party', 'attachments', 'topics']);

        abort_unless($this->isPubliclyVisible($record), 404);

        return response()->json($this->present($record));
    }

    /**
     * @return array<string, mixed>
     */
    public function present(PublicRecord $record): array
    {
        $date = $record->occurred_on ?? $record->reviewed_at ?? $record->created_at;

        return [
            'id' => $record->id,
            'category' => $record->category,
            'quote' => $record->quote,
            'summary' => $record->summary,
            'source_url' => $record->source_url,
            'source_type' => $record->source_type,
            'reliability_level' => $record->reliability_level,
            'reliability_label' => ReliabilityLevel::label($record->reliability_level),
            'occurred_on' => optional($record->occurred_on)->toDateString(),
            'date' => optional($date)->toDateString(),
            'href' => route('records.show', $record),
            'politician' => [
                'id' => $record->politician->id,
                'name' => $record->politician->name,
                'photo_url' => $record->politician->photo_url,
                'position' => $record->politician->position,
                'institution' => $record->politician->institution,
                'href' => route('politicians.show', $record->politician_id),
                'party' => $record->politician->party?->only(['id', 'name', 'logo_url']),
            ],
            'topics' => $record->topics->map->only(['id', 'name'])->values(),
            'attachments' => $record->attachments->map(fn (RecordAttachment $file) => [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'url' => $file->url,
                'kind' => $file->kind,
            ])->values(),
        ];
    }

    private function isPubliclyVisible(PublicRecord $record): bool
    {
        return (int) $record->status === PublicRecord::STATUS_PUBLISHED
            && $record->politician
            && (int) $record->politician->status === 1;
    }
}
