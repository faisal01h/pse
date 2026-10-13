<?php

namespace App\Http\Controllers;

use App\Models\PublicRecord;
use Illuminate\Http\Response;

class FeedController extends Controller
{
    private const CATEGORY_LABELS = [
        PublicRecord::CATEGORY_STATEMENT => 'Pernyataan publik',
        PublicRecord::CATEGORY_ALLEGATION => 'Dugaan pelanggaran',
        PublicRecord::CATEGORY_CONVICTION => 'Vonis pengadilan',
    ];

    public function __invoke(): Response
    {
        $records = PublicRecord::query()
            ->where('status', PublicRecord::STATUS_PUBLISHED)
            ->whereHas('politician', function ($query) {
                $query->where('status', 1);
            })
            ->with(['politician.party'])
            ->orderByRaw('COALESCE(reviewed_at, created_at) DESC')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $content = view('feed.rss', [
            'records' => $records,
            'baseUrl' => rtrim((string) config('app.url'), '/'),
            'labels' => self::CATEGORY_LABELS,
        ])->render();

        return response($content, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }
}
