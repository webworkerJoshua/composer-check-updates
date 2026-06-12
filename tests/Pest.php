<?php

declare(strict_types=1);

use ComposerCheckUpdates\Grouper;
use ComposerCheckUpdates\Model\FoundUpdate;
use ComposerCheckUpdates\Model\Group;
use ComposerCheckUpdates\Model\UpdateEntry;
use ComposerCheckUpdates\Tui\InteractiveSelector;

/**
 * Fixture shared by the selector tests. Display order:
 *   In range: acme/both (1.27.1), acme/in-range-only (1.4.0)
 *   Major:    acme/both (3.9.0)
 *
 * @return Group[]
 */
function ccuGroups(): array
{
    return Grouper::group([
        new FoundUpdate('acme/both', false, '^1.0', '1.25.0', '1.27.1', '3.9.0'),
        new FoundUpdate('acme/in-range-only', false, '^1.0', '1.2.0', '1.4.0', null),
    ]);
}

/**
 * @return resource
 */
function ccuStream(string $content = '')
{
    $stream = fopen('php://memory', 'w+');
    assert(is_resource($stream));
    fwrite($stream, $content);
    rewind($stream);

    return $stream;
}

/**
 * Drive the selector with a sequence of key bytes against the shared
 * fixture (or the given groups) and return its selection result.
 *
 * @param Group[]|null $groups
 * @return UpdateEntry[]|null
 */
function ccuSelect(string $keys, ?array $groups = null): ?array
{
    $selector = new InteractiveSelector(ccuStream($keys), ccuStream(), 100, 40, false);

    return $selector->run($groups ?? ccuGroups());
}
