<?php

declare(strict_types=1);

namespace ComposerCheckUpdates\Version;

final class ConstraintUpdater
{
    /**
     * Rewrite a composer.json constraint so it allows $newVersion while
     * preserving the original constraint style as far as possible:
     *
     *   ^1.2.3  + 2.1.0   => ^2.1.0
     *   ~1.2    + 2.1.0   => ~2.1
     *   1.2.*   + 2.1.3   => 2.1.*
     *   >=1.2   + 2.1.0   => >=2.1
     *   v1.2.3  + 2.1.0   => v2.1.0
     *   1.2.3   + 2.1.0   => 2.1.0
     *
     * Anything more complex (||, ranges with spaces, dev constraints)
     * falls back to a caret constraint on the new version.
     */
    public static function update(string $constraint, string $newVersion): string
    {
        $constraint = trim($constraint);
        $new = ltrim(trim($newVersion), 'vV');

        if (preg_match('/^(\^|~|>=)?(v?)(\d+(?:\.\d+)*)(\.\*)?$/', $constraint, $m)) {
            $operator = $m[1];
            $vPrefix = $m[2];
            $segments = substr_count($m[3], '.') + 1;
            $wildcard = $m[4] ?? '';

            // Pre-release versions (2.0.0-RC1) need the full version string;
            // truncating or wildcarding them would change their meaning.
            if (preg_match('/^\d+(?:\.\d+)*-/', $new)) {
                return $operator . $vPrefix . $new;
            }

            if (!preg_match('/^(\d+(?:\.\d+)*)/', $new, $nm)) {
                return '^' . $new;
            }

            $parts = explode('.', $nm[1]);
            $parts = array_slice(array_pad($parts, $segments, '0'), 0, $segments);

            return $operator . $vPrefix . implode('.', $parts) . $wildcard;
        }

        return '^' . $new;
    }
}
