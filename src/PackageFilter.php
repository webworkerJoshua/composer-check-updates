<?php

declare(strict_types=1);

namespace ComposerCheckUpdates;

/**
 * Decides which root requirements to check, based on the --filter,
 * --reject, --dev-only and --prod-only options. Patterns support "*"
 * wildcards (e.g. "symfony/*") and match case-insensitively; rejects
 * win over filters.
 */
final class PackageFilter
{
    /**
     * @param string[] $filters
     * @param string[] $rejects
     */
    public function __construct(
        private readonly array $filters = [],
        private readonly array $rejects = [],
        private readonly bool $devOnly = false,
        private readonly bool $prodOnly = false,
    ) {
    }

    public function accepts(string $package, bool $isDev): bool
    {
        if ($this->devOnly && !$isDev) {
            return false;
        }
        if ($this->prodOnly && $isDev) {
            return false;
        }

        foreach ($this->rejects as $pattern) {
            if (self::matches($package, $pattern)) {
                return false;
            }
        }

        if ($this->filters === []) {
            return true;
        }

        foreach ($this->filters as $pattern) {
            if (self::matches($package, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function matches(string $package, string $pattern): bool
    {
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/i';

        return preg_match($regex, $package) === 1;
    }
}
