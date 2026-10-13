<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\PublicRecord;
use App\Tracking\SubmissionCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class DisputeController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Dispute');
    }

    public function received(Request $request): Response
    {
        return Inertia::render('DisputeDiterima', [
            'code' => $request->session()->get('dispute_code'),
        ]);
    }

    public function records(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2 || mb_strlen($term) > 80) {
            return [];
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return PublicRecord::query()
            ->where('status', PublicRecord::STATUS_PUBLISHED)
            ->whereHas('politician', fn ($query) => $query->where('status', 1))
            ->where(function ($query) use ($like) {
                $query->where('quote', 'like', $like)
                    ->orWhere('summary', 'like', $like)
                    ->orWhereHas('politician', fn ($politician) => $politician->where('name', 'like', $like));
            })
            ->with('politician:id,name')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (PublicRecord $record) => [
                'id' => $record->id,
                'politician' => $record->politician?->name,
                'category' => $record->category,
                'excerpt' => $this->excerpt($record),
                'href' => route('politicians.show', $record->politician_id).'#record-'.$record->id,
            ])
            ->values();
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'public_record_id' => 'required|integer',
            'reason' => 'required|string|min:40|max:5000',
            'source_url' => ['required', 'string', 'max:2048', 'regex:/^https:\/\/\S+$/i'],
            'source_type' => 'required|string|max:100',
            'captcha' => 'required|string|max:12',
        ], [
            'public_record_id.required' => 'Pilih arsip yang terbit.',
            'reason.required' => 'Alasan sanggahan wajib diisi.',
            'reason.min' => 'Alasan sanggahan minimal 40 karakter.',
            'source_url.required' => 'Tautan sumber pembanding wajib diisi.',
            'source_url.regex' => 'Tautan sumber pembanding harus berupa https.',
            'source_type.required' => 'Jenis sumber wajib diisi.',
            'captcha.required' => 'Kode captcha wajib diisi.',
        ]);

        $validator->after(function ($validator) use ($request) {
            $this->assertCaptcha($request, $validator);

            $recordId = (int) $request->input('public_record_id');
            $published = PublicRecord::query()
                ->whereKey($recordId)
                ->where('status', PublicRecord::STATUS_PUBLISHED)
                ->whereHas('politician', fn ($query) => $query->where('status', 1))
                ->exists();

            if (! $published) {
                $validator->errors()->add('public_record_id', 'Pilih arsip yang terbit.');
            }
        });

        $validated = $validator->validate();

        $code = SubmissionCode::generate();

        Dispute::create([
            'public_record_id' => (int) $validated['public_record_id'],
            'tracking_code' => $code,
            'reason' => trim($validated['reason']),
            'source_url' => $validated['source_url'],
            'source_type' => trim($validated['source_type']),
            'status' => Dispute::STATUS_PENDING,
        ]);

        return redirect()->route('dispute.received')->with('dispute_code', $code);
    }

    public function pending()
    {
        return Dispute::query()
            ->where('status', Dispute::STATUS_PENDING)
            ->with('record.politician:id,name')
            ->latest('id')
            ->get()
            ->groupBy('public_record_id')
            ->map(function ($disputes) {
                $record = $disputes->first()->record;

                return [
                    'record' => [
                        'id' => $disputes->first()->public_record_id,
                        'politician' => $record?->politician?->name,
                        'excerpt' => $record ? $this->excerpt($record) : '',
                        'href' => $record
                            ? route('reports.review', $disputes->first()->public_record_id)
                            : null,
                    ],
                    'disputes' => $disputes->map(fn (Dispute $dispute) => [
                        'id' => $dispute->id,
                        'tracking_code' => $dispute->tracking_code,
                        'reason' => $dispute->reason,
                        'source_url' => $dispute->source_url,
                        'source_type' => $dispute->source_type,
                        'created_at' => $dispute->created_at,
                    ])->values(),
                ];
            })
            ->values();
    }

    public function decide(Request $request, Dispute $dispute): RedirectResponse
    {
        abort_unless($dispute->status === Dispute::STATUS_PENDING, 404);

        $validated = $request->validate([
            'decision' => 'required|in:uphold,dismiss',
            'review_note' => 'nullable|string|max:5000',
        ]);

        $dispute->fill([
            'status' => $validated['decision'] === 'uphold' ? Dispute::STATUS_UPHELD : Dispute::STATUS_DISMISSED,
            'review_note' => trim((string) ($validated['review_note'] ?? '')) ?: null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);
        $dispute->save();

        if ($validated['decision'] === 'uphold' && $dispute->record) {
            $dispute->record->fill([
                'status' => PublicRecord::STATUS_PENDING,
                'review_note' => $dispute->review_note ?: 'Ditarik dari arsip karena sanggahan diterima.',
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
            ]);
            $dispute->record->save();
        }

        return back();
    }

    private function excerpt(PublicRecord $record): string
    {
        $text = trim((string) ($record->quote ?: $record->summary));

        return mb_strlen($text) > 140 ? mb_substr($text, 0, 140).'…' : $text;
    }

    private function assertCaptcha(Request $request, \Illuminate\Validation\Validator $validator): void
    {
        $expected = strtoupper((string) $request->session()->pull('report_captcha', ''));
        $given = strtoupper(preg_replace('/\s+/', '', (string) $request->input('captcha')));

        if ($expected === '' || strlen($expected) !== strlen($given) || ! hash_equals($expected, $given)) {
            $validator->errors()->add('captcha', 'Kode captcha tidak cocok. Minta kode baru.');
        }
    }
}
