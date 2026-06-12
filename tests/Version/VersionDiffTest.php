<?php

declare(strict_types=1);

use ComposerCheckUpdates\Version\VersionDiff;

it('classifies the jump between two versions', function (string $from, string $to, string $expected): void {
    expect(VersionDiff::between($from, $to))->toBe($expected);
})->with([
    'patch' => ['1.2.3', '1.2.4', VersionDiff::PATCH],
    'minor' => ['1.2.3', '1.3.0', VersionDiff::MINOR],
    'major' => ['1.2.3', '2.0.0', VersionDiff::MAJOR],
    'major across many' => ['1.2.3', '4.0.1', VersionDiff::MAJOR],
    'zero-major minor' => ['0.1.0', '0.2.0', VersionDiff::MINOR],
    'v prefix' => ['v1.2.3', 'v2.0.0', VersionDiff::MAJOR],
    'mixed prefix' => ['1.2.3', 'v1.3.0', VersionDiff::MINOR],
    'two segment versions' => ['1.2', '1.3', VersionDiff::MINOR],
    'same' => ['1.2.3', '1.2.3', VersionDiff::NONE],
    'stability suffix counts as patch' => ['1.2.3', '1.2.3-p1', VersionDiff::PATCH],
    'dev version unknown' => ['dev-main', '1.2.3', VersionDiff::UNKNOWN],
]);
