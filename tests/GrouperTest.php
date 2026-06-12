<?php

declare(strict_types=1);

use ComposerCheckUpdates\Grouper;
use ComposerCheckUpdates\Model\FoundUpdate;
use ComposerCheckUpdates\Model\Group;
use ComposerCheckUpdates\Model\UpdateEntry;

it('groups by range semantics first, then severity', function (): void {
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

    expect(array_keys($byKey))->toBe([Group::IN_RANGE, Group::MINOR, Group::MAJOR]);

    $packages = static fn (Group $group): array => array_map(
        static fn (UpdateEntry $e): string => $e->package,
        $group->entries,
    );

    expect($packages($byKey[Group::IN_RANGE]))->toBe(['acme/both', 'acme/in-range-only'])
        ->and($packages($byKey[Group::MAJOR]))->toBe(['acme/both', 'acme/major-only'])
        ->and($packages($byKey[Group::MINOR]))->toBe(['acme/minor-pin']);
});

it('cross-references dual entries', function (): void {
    [$inRangeGroup, $majorGroup] = Grouper::group([
        new FoundUpdate('acme/both', false, '^1.0', '1.25.0', '1.27.1', '3.9.0'),
    ]);

    expect($inRangeGroup->entries[0]->requiresConstraintChange)->toBeFalse()
        ->and($inRangeGroup->entries[0]->note)->toBe('or 3.9.0, see Major')
        ->and($majorGroup->entries[0]->requiresConstraintChange)->toBeTrue()
        ->and($majorGroup->entries[0]->note)->toBe('or 1.27.1, see In range');
});

it('resolves select-all according to the requested target', function (string $target, string $expectedBothVersion): void {
    $groups = Grouper::group([
        new FoundUpdate('acme/both', false, '^1.0', '1.25.0', '1.27.1', '3.9.0'),
        new FoundUpdate('acme/in-range-only', false, '^1.0', '1.2.0', '1.4.0', null),
    ]);

    $picked = [];
    foreach (Grouper::selectAll($groups, $target) as $entry) {
        $picked[$entry->package] = $entry->to;
    }

    expect($picked)->toBe(['acme/both' => $expectedBothVersion, 'acme/in-range-only' => '1.4.0']);
})->with([
    'latest' => ['latest', '3.9.0'],
    'in-range' => ['in-range', '1.27.1'],
]);
