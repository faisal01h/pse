<?php

namespace App\SciPol;

class Codebook
{
    /**
     * @return list<array{field: string, type: string, values: string, description: string}>
     */
    public static function rows(): array
    {
        $axes = collect(Labels::AXES)->map(function (array $axis, string $key) {
            return "{$key}: {$axis['label']} (0={$axis['left']} … 100={$axis['right']})";
        })->implode('; ');

        $stances = collect(Labels::STANCES)->map(fn (string $label, string $key) => "{$key}={$label}")->implode('; ');
        $categories = collect(Labels::RECORD_CATEGORIES)->map(fn (string $label, string $key) => "{$key}={$label}")->implode('; ');
        $affiliationTypes = collect(Labels::AFFILIATIONS)->map(fn (string $label, string $key) => "{$key}={$label}")->implode('; ');

        return [
            [
                'field' => 'subject_kind',
                'type' => 'enum',
                'values' => 'politician; party',
                'description' => 'Subject kind in the extract.',
            ],
            [
                'field' => 'subject_id',
                'type' => 'string',
                'values' => 'opaque SciPol reference',
                'description' => 'Masked subject reference used by SciPol (not a database primary key).',
            ],
            [
                'field' => 'axis',
                'type' => 'enum',
                'values' => $axes,
                'description' => 'Ideology axis. Score 0–100 coded from sources.',
            ],
            [
                'field' => 'score',
                'type' => 'integer',
                'values' => '0–100',
                'description' => 'Position on the axis; samples is the number of sources used.',
            ],
            [
                'field' => 'position',
                'type' => 'enum',
                'values' => $stances,
                'description' => 'Stance on a named issue.',
            ],
            [
                'field' => 'record_category',
                'type' => 'enum',
                'values' => $categories,
                'description' => 'Public record category.',
            ],
            [
                'field' => 'affiliation_type',
                'type' => 'enum',
                'values' => $affiliationTypes,
                'description' => 'Published business affiliation type.',
            ],
            [
                'field' => 'source_url',
                'type' => 'url',
                'values' => '',
                'description' => 'Source URL underpinning the row.',
            ],
            [
                'field' => 'retrieved_at',
                'type' => 'datetime',
                'values' => 'ISO-8601',
                'description' => 'When the extract was built from the published archive.',
            ],
        ];
    }
}
