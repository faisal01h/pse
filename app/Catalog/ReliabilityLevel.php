<?php

namespace App\Catalog;

class ReliabilityLevel
{
    public const LOW = 'low';

    public const MEDIUM = 'medium';

    public const HIGH = 'high';

    public const OFFICIALLY_VERIFIED = 'officially_verified';

    public const LABELS = [
        self::LOW => 'Reliabilitas Rendah',
        self::MEDIUM => 'Reliabilitas Medium',
        self::HIGH => 'Terpercaya',
        self::OFFICIALLY_VERIFIED => 'Terverifikasi',
    ];

    /** Levels AutoVet may propose (never officially verified). */
    public const AUTO_LEVELS = [
        self::LOW,
        self::MEDIUM,
        self::HIGH,
    ];

    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(?string $level): ?string
    {
        if ($level === null || $level === '') {
            return null;
        }

        return self::LABELS[$level] ?? $level;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function formOptions(): array
    {
        $options = [];
        foreach (self::LABELS as $key => $label) {
            $options[] = ['key' => $key, 'label' => $label];
        }

        return $options;
    }

    public static function clampForAuto(?string $level): ?string
    {
        if ($level === null || $level === '') {
            return null;
        }

        if ($level === self::OFFICIALLY_VERIFIED) {
            return self::HIGH;
        }

        return in_array($level, self::AUTO_LEVELS, true) ? $level : null;
    }
}
