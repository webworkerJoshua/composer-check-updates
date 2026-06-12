<?php

declare(strict_types=1);

namespace ComposerCheckUpdates\Tests\Tui;

use ComposerCheckUpdates\Grouper;
use ComposerCheckUpdates\Model\FoundUpdate;
use ComposerCheckUpdates\Model\UpdateEntry;
use ComposerCheckUpdates\Tui\InteractiveSelector;
use PHPUnit\Framework\TestCase;

final class InteractiveSelectorTest extends TestCase
{
    /**
     * @return array{0: \ComposerCheckUpdates\Model\Group[], 1: resource}
     */
    private function makeGroups(): array
    {
        // Display order: In range: [both(1.27.1), in-range-only(1.4.0)], Major: [both(3.9.0)]
        $groups = Grouper::group([
            new FoundUpdate('acme/both', false, '^1.0', '1.25.0', '1.27.1', '3.9.0'),
            new FoundUpdate('acme/in-range-only', false, '^1.0', '1.2.0', '1.4.0', null),
        ]);
        $output = fopen('php://memory', 'w+');
        self::assertIsResource($output);

        return [$groups, $output];
    }

    /**
     * @return resource
     */
    private function input(string $keys)
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        fwrite($stream, $keys);
        rewind($stream);

        return $stream;
    }

    /**
     * @param \ComposerCheckUpdates\Model\Group[] $groups
     * @return UpdateEntry[]|null
     */
    private function runSelector(string $keys, ?array $groups = null)
    {
        [$defaultGroups, $output] = $this->makeGroups();
        $selector = new InteractiveSelector($this->input($keys), $output, 100, 40, false);

        return $selector->run($groups ?? $defaultGroups);
    }

    public function testEnterWithoutSelectionReturnsEmpty(): void
    {
        self::assertSame([], $this->runSelector("\n"));
    }

    public function testSpaceSelectsCurrentEntry(): void
    {
        $selected = $this->runSelector(" \n");

        self::assertNotNull($selected);
        self::assertCount(1, $selected);
        self::assertSame('acme/both', $selected[0]->package);
        self::assertSame('1.27.1', $selected[0]->to);
    }

    public function testArrowNavigationSelectsOtherEntry(): void
    {
        $selected = $this->runSelector("\x1b[B \n");

        self::assertNotNull($selected);
        self::assertCount(1, $selected);
        self::assertSame('acme/in-range-only', $selected[0]->package);
    }

    public function testSamePackageEntriesAreMutuallyExclusive(): void
    {
        // Select in-range entry of acme/both, then move two down to its
        // major entry and select that — the first selection must vanish.
        $selected = $this->runSelector(" \x1b[B\x1b[B \n");

        self::assertNotNull($selected);
        self::assertCount(1, $selected);
        self::assertSame('acme/both', $selected[0]->package);
        self::assertSame('3.9.0', $selected[0]->to);
        self::assertTrue($selected[0]->requiresConstraintChange);
    }

    public function testSelectAllPrefersInRangeAndSecondPressClears(): void
    {
        $selected = $this->runSelector("a\n");

        self::assertNotNull($selected);
        $picked = [];
        foreach ($selected as $entry) {
            $picked[$entry->package] = $entry->to;
        }
        self::assertSame(['acme/both' => '1.27.1', 'acme/in-range-only' => '1.4.0'], $picked);

        self::assertSame([], $this->runSelector("aa\n"));
    }

    public function testWrapAroundNavigation(): void
    {
        // Up from the first entry wraps to the last (major acme/both)
        $selected = $this->runSelector("\x1b[A \n");

        self::assertNotNull($selected);
        self::assertCount(1, $selected);
        self::assertSame('3.9.0', $selected[0]->to);
    }

    public function testAbortReturnsNull(): void
    {
        self::assertNull($this->runSelector('q'));
        self::assertNull($this->runSelector("\x03"));
        self::assertNull($this->runSelector(' ')); // EOF after space must abort, not spin
    }

    public function testRendersGroupsAndVersions(): void
    {
        [$groups, $output] = $this->makeGroups();
        $selector = new InteractiveSelector($this->input("\n"), $output, 120, 40, false);
        $selector->run($groups);

        rewind($output);
        $rendered = (string) stream_get_contents($output);
        $plain = preg_replace('/\x1b\[[0-9;]*[A-Za-z]/', '', $rendered);

        self::assertStringContainsString('In range', $plain);
        self::assertStringContainsString('Major', $plain);
        self::assertStringContainsString('acme/both', $plain);
        self::assertStringContainsString('1.25.0', $plain);
        self::assertStringContainsString('1.27.1', $plain);
        self::assertStringContainsString('3.9.0', $plain);
        self::assertStringContainsString('or 3.9.0, see Major', $plain);
        self::assertStringContainsString('or 1.27.1, see In range', $plain);
    }
}
