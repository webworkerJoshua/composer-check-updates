<?php

declare(strict_types=1);

namespace ComposerCheckUpdates\Model;

/**
 * One selectable row in the update list. A package that has both an
 * in-range update and a newer version outside the range produces two
 * entries (in different groups) sharing the same package name; selecting
 * one deselects the other.
 */
final class UpdateEntry
{
    public bool $selected = false;

    public function __construct(
        public readonly string $package,
        public readonly bool $isDev,
        public readonly string $constraint,
        public readonly string $from,
        public readonly string $to,
        public readonly bool $requiresConstraintChange,
        /** VersionDiff::* classification of from => to */
        public readonly string $diff,
        public readonly string $groupKey,
        public readonly ?string $note = null,
    ) {
    }
}
