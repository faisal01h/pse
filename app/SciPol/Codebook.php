<?php

namespace App\SciPol;

use App\Models\BusinessAffiliation;
use App\Models\IdeologyPosition;
use App\Models\IssueStance;
use App\Models\PublicRecord;

class Codebook
{
    /**
     * @return list<array{field: string, type: string, values: string, description: string}>
     */
    public static function rows(): array
    {
        $axes = collect(IdeologyPosition::AXES)->map(function (array $axis, string $key) {
            return "{$key}: {$axis['label']} (0={$axis['left']} … 100={$axis['right']})";
        })->implode('; ');

        $stances = collect(IssueStance::LABELS)->map(fn (string $label, string $key) => "{$key}={$label}")->implode('; ');
        $categories = collect([
            PublicRecord::CATEGORY_STATEMENT => 'Pernyataan publik',
            PublicRecord::CATEGORY_ALLEGATION => 'Dugaan',
            PublicRecord::CATEGORY_CONVICTION => 'Vonis',
        ])->map(fn (string $label, string $key) => "{$key}={$label}")->implode('; ');
        $affiliationTypes = collect(BusinessAffiliation::LABELS)
            ->map(fn (string $label, string $key) => "{$key}={$label}")
            ->implode('; ');

        return [
            [
                'field' => 'subject_kind',
                'type' => 'enum',
                'values' => 'politician; party',
                'description' => 'Jenis subjek dalam ekstrak.',
            ],
            [
                'field' => 'subject_id',
                'type' => 'integer',
                'values' => '',
                'description' => 'ID internal PolitiSEE untuk subjek.',
            ],
            [
                'field' => 'axis',
                'type' => 'enum',
                'values' => $axes,
                'description' => 'Sumbu ideologi. Skor 0–100 adalah tendensi terkode dari sumber, bukan vonis.',
            ],
            [
                'field' => 'score',
                'type' => 'integer',
                'values' => '0–100',
                'description' => 'Posisi pada sumbu; samples menunjukkan jumlah sumber yang dipakai menghitung.',
            ],
            [
                'field' => 'position',
                'type' => 'enum',
                'values' => $stances,
                'description' => 'Sikap pada isu bernama.',
            ],
            [
                'field' => 'record_category',
                'type' => 'enum',
                'values' => $categories,
                'description' => 'Kategori rekaman publik. Allegation ≠ conviction.',
            ],
            [
                'field' => 'affiliation_type',
                'type' => 'enum',
                'values' => $affiliationTypes,
                'description' => 'Jenis afiliasi bisnis yang diterbitkan.',
            ],
            [
                'field' => 'source_url',
                'type' => 'url',
                'values' => '',
                'description' => 'URL sumber yang mendasari baris data.',
            ],
            [
                'field' => 'retrieved_at',
                'type' => 'datetime',
                'values' => 'ISO-8601',
                'description' => 'Waktu ekstrak dibuat dari arsip terbit.',
            ],
        ];
    }
}
