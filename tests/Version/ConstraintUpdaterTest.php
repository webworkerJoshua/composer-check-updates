<?php

declare(strict_types=1);

use ComposerCheckUpdates\Version\ConstraintUpdater;

it('rewrites constraints preserving their style', function (string $constraint, string $newVersion, string $expected): void {
    expect(ConstraintUpdater::update($constraint, $newVersion))->toBe($expected);
})->with([
    'caret full' => ['^1.2.3', '2.1.0', '^2.1.0'],
    'caret two segments' => ['^1.2', '2.1.4', '^2.1'],
    'caret one segment' => ['^1', '2.1.4', '^2'],
    'tilde' => ['~1.2', '2.1.0', '~2.1'],
    'tilde full' => ['~1.2.0', '2.1.3', '~2.1.3'],
    'wildcard' => ['1.2.*', '2.1.3', '2.1.*'],
    'wildcard single' => ['1.*', '2.1.3', '2.*'],
    'gte' => ['>=1.2', '2.1.0', '>=2.1'],
    'exact' => ['1.2.3', '2.1.0', '2.1.0'],
    'exact keeps v prefix' => ['v1.2.3', '2.1.0', 'v2.1.0'],
    'caret keeps v prefix' => ['^v1.2.3', '2.1.0', '^v2.1.0'],
    'new version with v prefix' => ['^1.2.3', 'v2.1.0', '^2.1.0'],
    'pads missing segments' => ['^1.2.3', '2.1', '^2.1.0'],
    'pre-release keeps suffix' => ['^1.2.3', '2.0.0-RC1', '^2.0.0-RC1'],
    'pre-release on exact pin' => ['1.2.3', '2.0.0-beta2', '2.0.0-beta2'],
    'complex range falls back to caret' => ['>=1.2 <2.0', '2.1.0', '^2.1.0'],
    'or range falls back to caret' => ['^1.0 || ^2.0', '3.1.0', '^3.1.0'],
    'as alias falls back to caret' => ['dev-main as 1.0.0', '2.0.0', '^2.0.0'],
]);
