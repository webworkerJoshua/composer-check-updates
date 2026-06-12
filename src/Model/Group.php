<?php

declare(strict_types=1);

namespace ComposerCheckUpdates\Model;

final class Group
{
    public const IN_RANGE = 'in-range';
    public const PATCH = 'patch';
    public const MINOR = 'minor';
    public const MAJOR = 'major';
    public const OTHER = 'other';

    /**
     * @param UpdateEntry[] $entries
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $subtitle,
        /** ANSI color name used for the title and version highlights */
        public readonly string $color,
        public array $entries,
    ) {
    }
}
