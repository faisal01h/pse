<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessAffiliation;
use App\Models\Dispute;
use App\Models\PublicRecord;
use App\Tracking\SubmissionCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionTrackingController extends Controller
{
    public const KIND_SUBMISSION = 'submission';

    public const KIND_DISPUTE = 'dispute';

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

    public function create(Request $request): Response
    {
        $kind = $request->query('kind');
        $kind = in_array($kind, [self::KIND_SUBMISSION, self::KIND_DISPUTE], true) ? $kind : null;

        return Inertia::render('Lacak', [
            'kind' => $kind,
            'progress' => null,
        ]);
    }

    public function show(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'kind' => ['required', Rule::in([self::KIND_SUBMISSION, self::KIND_DISPUTE])],
            'tracking_code' => 'required|string|max:32',
        ], [
            'kind.required' => 'Pilih jenis pelacakan.',
            'kind.in' => 'Pilih jenis pelacakan.',
            'tracking_code.required' => 'Kode pelacakan wajib diisi.',
        ]);

        $normalized = SubmissionCode::normalize($validated['tracking_code']);

        if (! preg_match('/^[A-Z2-9]{16}$/', $normalized)) {
            return back()->withErrors([
                'tracking_code' => 'Kode tidak dikenali.',
            ])->withInput();
        }

        if ($validated['kind'] === self::KIND_DISPUTE) {
            $dispute = $this->findDispute($normalized);

            if (! $dispute) {
                return back()->withErrors([
                    'tracking_code' => 'Kode tidak dikenali.',
                ])->withInput();
            }

            return Inertia::render('Lacak', [
                'kind' => self::KIND_DISPUTE,
                'progress' => $this->disputeProgress($dispute),
            ]);
        }

        $hash = SubmissionCode::hash($normalized);

        $record = PublicRecord::query()
            ->where('tracking_code_hash', $hash)
            ->first();

        if ($record && SubmissionCode::matches($normalized, $record->tracking_code_hash)) {
            return Inertia::render('Lacak', [
                'kind' => self::KIND_SUBMISSION,
                'progress' => $this->recordProgress($record),
            ]);
        }

        $affiliation = BusinessAffiliation::query()
            ->where('tracking_code_hash', $hash)
            ->first();

        if ($affiliation && SubmissionCode::matches($normalized, $affiliation->tracking_code_hash)) {
            return Inertia::render('Lacak', [
                'kind' => self::KIND_SUBMISSION,
                'progress' => $this->affiliationProgress($affiliation),
            ]);
        }

        return back()->withErrors([
            'tracking_code' => 'Kode tidak dikenali.',
        ])->withInput();
    }

    private function findDispute(string $normalized): ?Dispute
    {
        $formatted = implode('-', str_split($normalized, 4));

        return Dispute::query()
            ->where('tracking_code', $formatted)
            ->first();
    }

    /**
     * @return array{status: string, label: string, detail: string, politician: ?string, category: string, submitted_at: ?string, reviewed_at: ?string, href: ?string}
     */
    private function recordProgress(PublicRecord $record): array
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
            'href' => $published ? $record->publicPath() : null,
        ];
    }

    /**
     * @return array{status: string, label: string, detail: string, politician: ?string, category: string, submitted_at: ?string, reviewed_at: ?string, href: ?string}
     */
    private function affiliationProgress(BusinessAffiliation $affiliation): array
    {
        $affiliation->loadMissing(['politician:id,name,status', 'business:id,name,status']);
        $status = (int) $affiliation->status;
        $published = $status === BusinessAffiliation::STATUS_PUBLISHED
            && $affiliation->politician
            && (int) $affiliation->politician->status === 1
            && $affiliation->business
            && (int) $affiliation->business->status === Business::STATUS_PUBLISHED;

        if ($published) {
            $key = 'terbit';
            $label = 'Sudah terbit';
            $detail = 'Afiliasi bisnis ini sudah diperiksa dan tampil di katalog.';
        } elseif ($status === BusinessAffiliation::STATUS_REJECTED) {
            $key = 'ditolak';
            $label = 'Tidak diterbitkan';
            $detail = 'Afiliasi bisnis ini sudah diperiksa dan tidak diterbitkan.';
        } else {
            $key = 'menunggu';
            $label = 'Menunggu pemeriksaan';
            $detail = 'Afiliasi bisnis sudah diterima dan belum selesai diperiksa.';
        }

        return [
            'status' => $key,
            'label' => $label,
            'detail' => $detail,
            'politician' => $affiliation->politician?->name,
            'category' => $affiliation->typeLabel(),
            'submitted_at' => $affiliation->created_at?->format('d-m-Y'),
            'reviewed_at' => $affiliation->reviewed_at?->format('d-m-Y'),
            'href' => $published ? route('businesses.show', $affiliation->business_id) : null,
        ];
    }

    /**
     * @return array{status: string, label: string, detail: string, politician: ?string, category: string, submitted_at: ?string, reviewed_at: ?string, href: ?string}
     */
    private function disputeProgress(Dispute $dispute): array
    {
        $dispute->loadMissing([
            'record.politician:id,name,status',
            'affiliation.politician:id,name,status',
            'affiliation.business:id,name,status',
        ]);
        $status = (string) $dispute->status;
        $isAffiliation = $dispute->isAffiliationTarget();

        if ($status === Dispute::STATUS_UPHELD) {
            $key = 'diterima';
            $label = 'Sanggahan diterima';
            $detail = $isAffiliation
                ? 'Sanggahan ini diterima. Afiliasi bisnis terkait ditarik untuk diperiksa ulang.'
                : 'Sanggahan ini diterima. Arsip terkait ditarik untuk diperiksa ulang.';
        } elseif ($status === Dispute::STATUS_DISMISSED) {
            $key = 'ditolak';
            $label = 'Sanggahan ditolak';
            $detail = $isAffiliation
                ? 'Sanggahan ini ditolak. Afiliasi bisnis terkait dapat tetap terbit.'
                : 'Sanggahan ini ditolak. Arsip terkait tetap terbit.';
        } else {
            $key = 'menunggu';
            $label = 'Menunggu pemeriksaan';
            $detail = $isAffiliation
                ? 'Sanggahan afiliasi sudah diterima dan belum selesai diperiksa.'
                : 'Sanggahan sudah diterima dan belum selesai diperiksa.';
        }

        if ($isAffiliation) {
            $affiliation = $dispute->affiliation;
            $politician = $affiliation?->politician;
            $published = $affiliation
                && (int) $affiliation->status === BusinessAffiliation::STATUS_PUBLISHED
                && $politician
                && (int) $politician->status === 1
                && $affiliation->business
                && (int) $affiliation->business->status === Business::STATUS_PUBLISHED;

            return [
                'status' => $key,
                'label' => $label,
                'detail' => $detail,
                'politician' => $politician?->name,
                'category' => 'Sanggahan afiliasi'.($affiliation?->business?->name ? ' · '.$affiliation->business->name : ''),
                'submitted_at' => $dispute->created_at?->format('d-m-Y'),
                'reviewed_at' => $dispute->reviewed_at?->format('d-m-Y'),
                'href' => $published ? route('businesses.show', $affiliation->business_id) : null,
            ];
        }

        $record = $dispute->record;
        $politician = $record?->politician;
        $recordPublished = $record
            && (int) $record->status === PublicRecord::STATUS_PUBLISHED
            && $politician
            && (int) $politician->status === 1;

        return [
            'status' => $key,
            'label' => $label,
            'detail' => $detail,
            'politician' => $politician?->name,
            'category' => 'Sanggahan',
            'submitted_at' => $dispute->created_at?->format('d-m-Y'),
            'reviewed_at' => $dispute->reviewed_at?->format('d-m-Y'),
            'href' => $recordPublished
                ? $record->publicPath()
                : null,
        ];
    }
}
