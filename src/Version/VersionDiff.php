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
     * Classify the jump between two pretty versions by their
     * major.minor.patch segments. Below 1.0.0 a minor bump counts as
     * major: semver makes no compatibility promise for 0.x, and Composer's
     * caret operator agrees (^0.1.0 allows 0.1.x but not 0.2.0). Patch
     * bumps within the same 0.x minor stay patches.
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
            return $a[0] === 0 ? self::MAJOR : self::MINOR;
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
