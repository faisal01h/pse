<?php

namespace App\SciPol;

use App\Models\BusinessAffiliation;
use App\Models\IdeologyPosition;
use App\Models\IssueStance;
use App\Models\PartyLink;
use App\Models\PoliticianLink;
use App\Models\PublicRecord;

class Labels
{
    public const RECORD_CATEGORIES = [
        PublicRecord::CATEGORY_STATEMENT => 'Statement',
        PublicRecord::CATEGORY_ALLEGATION => 'Allegation',
        PublicRecord::CATEGORY_CONVICTION => 'Conviction',
    ];

    public const STANCES = [
        IssueStance::SUPPORT => 'Support',
        IssueStance::OPPOSE => 'Oppose',
        IssueStance::MIXED => 'Mixed',
    ];

    public const AFFILIATIONS = [
        BusinessAffiliation::TYPE_OWNERSHIP => 'Ownership',
        BusinessAffiliation::TYPE_ENDORSEMENT => 'Endorsement',
    ];

    public const AXES = [
        IdeologyPosition::AXIS_ECONOMIC => [
            'label' => 'Economic',
            'left' => 'Strong state role',
            'right' => 'Market orientation',
        ],
        IdeologyPosition::AXIS_SOCIAL => [
            'label' => 'Social',
            'left' => 'Progressive',
            'right' => 'Conservative',
        ],
        IdeologyPosition::AXIS_NATIONALISM => [
            'label' => 'Nationalism',
            'left' => 'Pluralist',
            'right' => 'Nationalist',
        ],
    ];

    public static function recordCategory(string $category): string
    {
        return self::RECORD_CATEGORIES[$category] ?? $category;
    }

    public static function stance(string $position): string
    {
        return self::STANCES[$position] ?? $position;
    }

    public static function affiliation(string $type): string
    {
        return self::AFFILIATIONS[$type] ?? $type;
    }

    public static function politicianLink(string $type, bool $viewerIsSource): string
    {
        if ($type === PoliticianLink::TYPE_SUPPORT && ! $viewerIsSource) {
            return 'Supported by';
        }

        return match ($type) {
            PoliticianLink::TYPE_ALLY => 'Ally',
            PoliticianLink::TYPE_RIVAL => 'Rival',
            PoliticianLink::TYPE_SUPPORT => 'Supports',
            default => $type,
        };
    }

    public static function partyLink(string $type, bool $viewerIsSource): string
    {
        if ($viewerIsSource) {
            return match ($type) {
                PartyLink::TYPE_COALITION => 'Coalition',
                PartyLink::TYPE_OPPOSITION => 'Opposition',
                PartyLink::TYPE_MERGED_INTO => 'Merged into',
                PartyLink::TYPE_SPLIT_FROM => 'Split from',
                default => $type,
            };
        }

        return match ($type) {
            PartyLink::TYPE_MERGED_INTO => 'Merged from',
            PartyLink::TYPE_SPLIT_FROM => 'Split into',
            PartyLink::TYPE_COALITION => 'Coalition',
            PartyLink::TYPE_OPPOSITION => 'Opposition',
            default => $type,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $tendencies
     * @return list<array<string, mixed>>
     */
    public static function englishTendencies(array $tendencies): array
    {
        return array_map(function (array $item) {
            $axis = $item['axis'] ?? '';
            $definition = self::AXES[$axis] ?? null;
            if ($definition) {
                $item['axis_label'] = $definition['label'];
                $item['left'] = $definition['left'];
                $item['right'] = $definition['right'];
            }

            return $item;
        }, $tendencies);
    }

    /**
     * @param  list<array<string, mixed>>  $stances
     * @return list<array<string, mixed>>
     */
    public static function englishStances(array $stances): array
    {
        return array_map(function (array $item) {
            foreach (self::STANCES as $key => $label) {
                if (($item['position_label'] ?? null) === IssueStance::LABELS[$key]) {
                    $item['position_label'] = $label;
                    break;
                }
            }

            return $item;
        }, $stances);
    }

    /**
     * @param  list<array<string, mixed>>  $affiliations
     * @return list<array<string, mixed>>
     */
    public static function englishAffiliations(array $affiliations): array
    {
        return array_map(function (array $item) {
            if (isset($item['type'])) {
                $item['label'] = self::affiliation($item['type']);
            }

            return $item;
        }, $affiliations);
    }
}
