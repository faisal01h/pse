<?php

namespace App\Models;

use App\Models\Concerns\RecordsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdeologyPosition extends Model
{
    use RecordsChanges;

    public const AXIS_ECONOMIC = 'ekonomi';

    public const AXIS_SOCIAL = 'sosial';

    public const AXIS_NATIONALISM = 'nasionalisme';

    public const AXES = [
        self::AXIS_ECONOMIC => [
            'label' => 'Ekonomi',
            'left' => 'Peran negara besar',
            'right' => 'Orientasi pasar',
        ],
        self::AXIS_SOCIAL => [
            'label' => 'Sosial',
            'left' => 'Progresif',
            'right' => 'Konservatif',
        ],
        self::AXIS_NATIONALISM => [
            'label' => 'Nasionalisme',
            'left' => 'Pluralis',
            'right' => 'Nasionalis',
        ],
    ];

    public const LEGACY_SCORES = [
        'negara' => 0,
        'progresif' => 0,
        'pluralis' => 0,
        'campuran' => 50,
        'pasar' => 100,
        'konservatif' => 100,
        'nasionalis' => 100,
    ];

    protected $fillable = [
        'politician_id',
        'party_id',
        'axis',
        'score',
        'samples',
        'note',
        'source_url',
        'source_type',
        'occurred_on',
        'user_id',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'score' => 'integer',
        'samples' => 'integer',
        'politician_id' => 'integer',
        'party_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function politician(): BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public static function axisKeys(): array
    {
        return array_keys(self::AXES);
    }

    public static function scoreFromLegacy(string $position): ?int
    {
        return self::LEGACY_SCORES[$position] ?? null;
    }

    public static function scoreFromHits(int $left, int $right): ?int
    {
        if ($left < 1 && $right < 1) {
            return null;
        }

        if ($left > 0 && $right > 0) {
            return (int) round(100 * $right / ($left + $right));
        }

        if ($left > 0) {
            return max(0, 35 - (15 * ($left - 1)));
        }

        return min(100, 65 + (15 * ($right - 1)));
    }

    /**
     * @return array{score: int, samples: int}
     */
    public static function blendScore(int $current, int $samples, int $incoming): array
    {
        $samples = max(1, $samples);
        $current = max(0, min(100, $current));
        $incoming = max(0, min(100, $incoming));
        $next = $samples + 1;

        return [
            'score' => (int) round((($current * $samples) + $incoming) / $next),
            'samples' => $next,
        ];
    }

    public static function formAxes(): array
    {
        $axes = [];

        foreach (self::AXES as $key => $axis) {
            $axes[] = [
                'key' => $key,
                'label' => $axis['label'],
                'left' => $axis['left'],
                'right' => $axis['right'],
            ];
        }

        return $axes;
    }

    public function axisLabel(): string
    {
        return self::AXES[$this->axis]['label'] ?? $this->axis;
    }
}
