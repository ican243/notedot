<?php

namespace App\Support;

// 계절 테마 결정: 매장 설정값 → auto 면 오늘 날짜 기준
class Season
{
    public const ALL = ['spring', 'summer', 'autumn', 'winter'];

    public static function resolve(?string $theme = 'auto'): string
    {
        if (in_array($theme, self::ALL, true)) {
            return $theme;
        }

        return match (true) {
            in_array(now()->month, [3, 4, 5]) => 'spring',
            in_array(now()->month, [6, 7, 8]) => 'summer',
            in_array(now()->month, [9, 10, 11]) => 'autumn',
            default => 'winter',
        };
    }
}
