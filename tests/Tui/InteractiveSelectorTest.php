<?php

declare(strict_types=1);

use ComposerCheckUpdates\Tui\InteractiveSelector;

it('returns an empty selection on plain enter', function (): void {
    expect(ccuSelect("\n"))->toBe([]);
});

it('selects the current entry with space', function (): void {
    $selected = ccuSelect(" \n");

    expect($selected)->toHaveCount(1)
        ->and($selected[0]->package)->toBe('acme/both')
        ->and($selected[0]->to)->toBe('1.27.1');
});

it('navigates with arrow keys', function (): void {
    $selected = ccuSelect("\x1b[B \n");

    expect($selected)->toHaveCount(1)
        ->and($selected[0]->package)->toBe('acme/in-range-only');
});

it('treats entries of the same package as mutually exclusive', function (): void {
    // Select the in-range entry of acme/both, then move two down to its
    // major entry and select that — the first selection must vanish.
    $selected = ccuSelect(" \x1b[B\x1b[B \n");

    expect($selected)->toHaveCount(1)
        ->and($selected[0]->package)->toBe('acme/both')
        ->and($selected[0]->to)->toBe('3.9.0')
        ->and($selected[0]->requiresConstraintChange)->toBeTrue();
});

it('prefers in-range entries on select-all and clears on the second press', function (): void {
    $selected = ccuSelect("a\n");
    $picked = [];
    foreach ($selected ?? [] as $entry) {
        $picked[$entry->package] = $entry->to;
    }

    expect($picked)->toBe(['acme/both' => '1.27.1', 'acme/in-range-only' => '1.4.0'])
        ->and(ccuSelect("aa\n"))->toBe([]);
});

it('wraps around when navigating past the ends', function (): void {
    // Up from the first entry wraps to the last (major acme/both)
    $selected = ccuSelect("\x1b[A \n");

    expect($selected)->toHaveCount(1)
        ->and($selected[0]->to)->toBe('3.9.0');
});

it('aborts on q, Ctrl-C and EOF', function (string $keys): void {
    expect(ccuSelect($keys))->toBeNull();
})->with([
    'q' => ['q'],
    'ctrl-c' => ["\x03"],
    'eof after space' => [' '], // EOF must abort, never spin
]);

it('renders groups, versions and cross-references', function (): void {
    $output = ccuStream();
    $selector = new InteractiveSelector(ccuStream("\n"), $output, 120, 40, false);
    $selector->run(ccuGroups());

    rewind($output);
    $rendered = (string) stream_get_contents($output);
    $plain = (string) preg_replace('/\x1b\[[0-9;]*[A-Za-z]/', '', $rendered);

    expect($plain)->toContain('In range')
        ->toContain('Major')
        ->toContain('acme/both')
        ->toContain('1.25.0')
        ->toContain('1.27.1')
        ->toContain('3.9.0')
        ->toContain('or 3.9.0, see Major')
        ->toContain('or 1.27.1, see In range');
});
