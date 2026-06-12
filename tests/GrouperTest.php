<?php

declare(strict_types=1);

namespace ComposerCheckUpdates\Tests;

use ComposerCheckUpdates\Grouper;
use ComposerCheckUpdates\Model\FoundUpdate;
use ComposerCheckUpdates\Model\Group;
use PHPUnit\Framework\TestCase;

final class GrouperTest extends TestCase
{
    public function testGroupsByRangeSemanticsThenSeverity(): void
    {
        $groups = Grouper::group([
            new FoundUpdate('acme/in-range-only', false, '^1.0', '1.2.0', '1.4.0', null),
            new FoundUpdate('acme/major-only', false, '^2.0', '2.9.0', null, '3.0.0'),
            new FoundUpdate('acme/minor-pin', false, '1.1.0', '1.1.0', null, '1.2.0'),
            new FoundUpdate('acme/both', true, '^1.0', '1.25.0', '1.27.1', '3.9.0'),
        ]);

        $byKey = [];
        foreach ($groups as $group) {
            $byKey[$group->key] = $group;
        }

        self::assertSame([Group::IN_RANGE, Group::MINOR, Group::MAJOR], array_keys($byKey));

        $inRange = array_map(static fn ($e) => $e->package, $byKey[Group::IN_RANGE]->entries);
        self::assertSame(['acme/both', 'acme/in-range-only'], $inRange);

        $major = array_map(static fn ($e) => $e->package, $byKey[Group::MAJOR]->entries);
        self::assertSame(['acme/both', 'acme/major-only'], $major);

        $minor = array_map(static fn ($e) => $e->package, $byKey[Group::MINOR]->entries);
        self::assertSame(['acme/minor-pin'], $minor);
    }

    public function testDualEntriesCrossReferenceEachOther(): void
    {
        $groups = Grouper::group([
            new FoundUpdate('acme/both', false, '^1.0', '1.25.0', '1.27.1', '3.9.0'),
        ]);

        [$inRangeGroup, $majorGroup] = $groups;

        self::assertFalse($inRangeGroup->entries[0]->requiresConstraintChange);
        self::assertSame('or 3.9.0, see Major', $inRangeGroup->entries[0]->note);

        self::assertTrue($majorGroup->entries[0]->requiresConstraintChange);
        self::assertSame('or 1.27.1, see In range', $majorGroup->entries[0]->note);
    }

    public function testSelectAllPrefersTarget(): void
    {
        $groups = Grouper::group([
            new FoundUpdate('acme/both', false, '^1.0', '1.25.0', '1.27.1', '3.9.0'),
            new FoundUpdate('acme/in-range-only', false, '^1.0', '1.2.0', '1.4.0', null),
        ]);

        $latest = Grouper::selectAll($groups, 'latest');
        $picked = [];
        foreach ($latest as $entry) {
            $picked[$entry->package] = $entry->to;
        }
        self::assertSame(['acme/both' => '3.9.0', 'acme/in-range-only' => '1.4.0'], $picked);

        foreach ($groups as $group) {
            foreach ($group->entries as $entry) {
                $entry->selected = false;
            }
        }

        $inRange = Grouper::selectAll($groups, 'in-range');
        $picked = [];
        foreach ($inRange as $entry) {
            $picked[$entry->package] = $entry->to;
        }
        self::assertSame(['acme/both' => '1.27.1', 'acme/in-range-only' => '1.4.0'], $picked);
    }
}
