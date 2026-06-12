<?php

declare(strict_types=1);

namespace ComposerCheckUpdates\Model;

/**
 * Result of checking one root requirement against the repositories.
 */
final class FoundUpdate
{
    public function __construct(
        public readonly string $name,
        public readonly bool $isDev,
        public readonly string $constraint,
        public readonly string $installed,
        /** Best version satisfying the constraint, if newer than installed. */
        public readonly ?string $inRange,
        /** Best version overall, if newer than both installed and in-range. */
        public readonly ?string $latest,
    ) {
    }

    public function hasUpdate(): bool
    {
        return $this->inRange !== null || $this->latest !== null;
    }
}
