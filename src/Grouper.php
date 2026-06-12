<?php

declare(strict_types=1);

namespace ComposerCheckUpdates;

use ComposerCheckUpdates\Model\FoundUpdate;
use ComposerCheckUpdates\Model\Group;
use ComposerCheckUpdates\Model\UpdateEntry;
use ComposerCheckUpdates\Version\VersionDiff;

final class Grouper
{
    private const GROUP_DEFINITIONS = [
        Group::IN_RANGE => [
            'title' => 'In range',
            'subtitle' => 'satisfies composer.json — plain `composer update` would pick these',
            'color' => 'green',
        ],
        Group::PATCH => [
            'title' => 'Patch',
            'subtitle' => 'outside range · composer.json will be updated',
            'color' => 'green',
        ],
        Group::MINOR => [
            'title' => 'Minor',
            'subtitle' => 'backwards-compatible features · composer.json will be updated',
            'color' => 'cyan',
        ],
        Group::MAJOR => [
            'title' => 'Major',
            'subtitle' => 'potentially breaking changes · composer.json will be updated',
            'color' => 'red',
        ],
        Group::OTHER => [
            'title' => 'Other',
            'subtitle' => 'unclassifiable versions · composer.json will be updated',
            'color' => 'magenta',
        ],
    ];

    /**
     * Build ordered, selectable groups from the raw findings.
     *
     * A package with both an in-range update and a newer release outside
     * the range yields one entry in "In range" and one in the matching
     * out-of-range group; both carry a note pointing at the other.
     *
     * @param FoundUpdate[] $updates
     * @return Group[]
     */
    public static function group(array $updates): array
    {
        /** @var array<string, UpdateEntry[]> $buckets */
        $buckets = [];

        foreach ($updates as $update) {
            if ($update->inRange !== null) {
                $buckets[Group::IN_RANGE][] = new UpdateEntry(
                    $update->name,
                    $update->isDev,
                    $update->constraint,
                    $update->installed,
                    $update->inRange,
                    false,
                    VersionDiff::between($update->installed, $update->inRange),
                    Group::IN_RANGE,
                    $update->latest !== null
                        ? sprintf('or %s, see %s', $update->latest, self::titleFor($update->installed, $update->latest))
                        : null,
                );
            }

            if ($update->latest !== null) {
                $diff = VersionDiff::between($update->installed, $update->latest);
                $groupKey = in_array($diff, [Group::PATCH, Group::MINOR, Group::MAJOR], true) ? $diff : Group::OTHER;
                $buckets[$groupKey][] = new UpdateEntry(
                    $update->name,
                    $update->isDev,
                    $update->constraint,
                    $update->installed,
                    $update->latest,
                    true,
                    $diff,
                    $groupKey,
                    $update->inRange !== null
                        ? sprintf('or %s, see In range', $update->inRange)
                        : null,
                );
            }
        }

        $groups = [];
        foreach (self::GROUP_DEFINITIONS as $key => $def) {
            if (empty($buckets[$key])) {
                continue;
            }
            usort($buckets[$key], static fn (UpdateEntry $a, UpdateEntry $b) => strcmp($a->package, $b->package));
            $groups[] = new Group($key, $def['title'], $def['subtitle'], $def['color'], $buckets[$key]);
        }

        return $groups;
    }

    /**
     * Resolve a non-interactive selection (--all): pick one entry per
     * package, preferring the requested target when a package appears in
     * more than one group.
     *
     * @param Group[] $groups
     * @param 'latest'|'in-range' $target
     * @return UpdateEntry[]
     */
    public static function selectAll(array $groups, string $target = 'latest'): array
    {
        /** @var array<string, UpdateEntry> $byPackage */
        $byPackage = [];
        foreach ($groups as $group) {
            foreach ($group->entries as $entry) {
                $existing = $byPackage[$entry->package] ?? null;
                if ($existing === null) {
                    $byPackage[$entry->package] = $entry;
                    continue;
                }
                $preferOutOfRange = $target === 'latest';
                if ($entry->requiresConstraintChange === $preferOutOfRange) {
                    $byPackage[$entry->package] = $entry;
                }
            }
        }

        $selected = array_values($byPackage);
        foreach ($selected as $entry) {
            $entry->selected = true;
        }

        return $selected;
    }

    private static function titleFor(string $from, string $to): string
    {
        $diff = VersionDiff::between($from, $to);

        return self::GROUP_DEFINITIONS[in_array($diff, [Group::PATCH, Group::MINOR, Group::MAJOR], true) ? $diff : Group::OTHER]['title'];
    }
}
