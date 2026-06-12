<?php

declare(strict_types=1);

namespace ComposerCheckUpdates\Version;

final class VersionDiff
{
    public const MAJOR = 'major';
    public const MINOR = 'minor';
    public const PATCH = 'patch';
    public const NONE = 'none';
    public const UNKNOWN = 'unknown';

    /**
     * Classify the jump between two pretty versions by their literal
     * major.minor.patch segments (0.x minors count as minor, like ncu's
     * group view, because the constraint impact is what matters here).
     */
    public static function between(string $from, string $to): string
    {
        $a = self::parse($from);
        $b = self::parse($to);

        if ($a === null || $b === null) {
            return self::UNKNOWN;
        }

        if ($a[0] !== $b[0]) {
            return self::MAJOR;
        }
        if ($a[1] !== $b[1]) {
            return self::MINOR;
        }
        if ($a[2] !== $b[2] || trim($from, 'vV') !== trim($to, 'vV')) {
            return self::PATCH;
        }

        return self::NONE;
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private static function parse(string $version): ?array
    {
        $version = ltrim($version, 'vV');
        if (!preg_match('/^(\d+)\.(\d+)(?:\.(\d+))?/', $version, $m)) {
            return null;
        }

        return [(int) $m[1], (int) $m[2], (int) ($m[3] ?? 0)];
    }
}
