<?php

namespace App\Http\Controllers;

use App\Models\PublicRecord;
use App\Tracking\SubmissionCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionTrackingController extends Controller
{
    private const CATEGORY_LABELS = [
        PublicRecord::CATEGORY_STATEMENT => 'Pernyataan publik',
        PublicRecord::CATEGORY_ALLEGATION => 'Dugaan pelanggaran',
        PublicRecord::CATEGORY_CONVICTION => 'Vonis pengadilan',
    ];

    public function receipt(Request $request): Response
    {
        return Inertia::render('LaporanDiterima', [
            'code' => $request->session()->get('submission_code'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Lacak', [
            'progress' => null,
        ]);
    }

    public function show(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'tracking_code' => 'required|string|max:32',
        ], [
            'tracking_code.required' => 'Kode pelacakan wajib diisi.',
        ]);

        $normalized = SubmissionCode::normalize($validated['tracking_code']);

        if (! preg_match('/^[A-Z2-9]{16}$/', $normalized)) {
            return back()->withErrors([
                'tracking_code' => 'Kode tidak dikenali.',
            ]);
        }

        $record = PublicRecord::query()
            ->where('tracking_code_hash', SubmissionCode::hash($normalized))
            ->first();

        if (! $record || ! SubmissionCode::matches($normalized, $record->tracking_code_hash)) {
            return back()->withErrors([
                'tracking_code' => 'Kode tidak dikenali.',
            ]);
        }

        return Inertia::render('Lacak', [
            'progress' => $this->progress($record),
        ]);
    }

    /**
     * @return array{status: string, label: string, detail: string, politician: ?string, category: string, submitted_at: ?string, reviewed_at: ?string, href: ?string}
     */
    private function progress(PublicRecord $record): array
    {
        $record->loadMissing('politician:id,name,status');
        $status = (int) $record->status;
        $published = $status === PublicRecord::STATUS_PUBLISHED
            && $record->politician
            && (int) $record->politician->status === 1;

        if ($published) {
            $key = 'terbit';
            $label = 'Sudah terbit';
            $detail = 'Catatan ini sudah diperiksa dan tampil di profil.';
        } elseif ($status === PublicRecord::STATUS_REJECTED) {
            $key = 'ditolak';
            $label = 'Tidak diterbitkan';
            $detail = 'Catatan ini sudah diperiksa dan tidak diterbitkan.';
        } else {
            $key = 'menunggu';
            $label = 'Menunggu pemeriksaan';
            $detail = 'Catatan sudah diterima dan belum selesai diperiksa.';
        }

        return [
            'status' => $key,
            'label' => $label,
            'detail' => $detail,
            'politician' => $record->politician?->name,
            'category' => self::CATEGORY_LABELS[$record->category] ?? $record->category,
            'submitted_at' => $record->created_at?->format('d-m-Y'),
            'reviewed_at' => $record->reviewed_at?->format('d-m-Y'),
            'href' => $published ? '/politicians/'.$record->politician_id.'#record-'.$record->id : null,
        ];
    }
}
