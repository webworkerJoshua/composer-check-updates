<?php

declare(strict_types=1);

namespace ComposerCheckUpdates\Tests\Version;

use ComposerCheckUpdates\Version\ConstraintUpdater;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConstraintUpdaterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideConstraints(): iterable
    {
        yield 'caret full' => ['^1.2.3', '2.1.0', '^2.1.0'];
        yield 'caret two segments' => ['^1.2', '2.1.4', '^2.1'];
        yield 'caret one segment' => ['^1', '2.1.4', '^2'];
        yield 'tilde' => ['~1.2', '2.1.0', '~2.1'];
        yield 'tilde full' => ['~1.2.0', '2.1.3', '~2.1.3'];
        yield 'wildcard' => ['1.2.*', '2.1.3', '2.1.*'];
        yield 'wildcard single' => ['1.*', '2.1.3', '2.*'];
        yield 'gte' => ['>=1.2', '2.1.0', '>=2.1'];
        yield 'exact' => ['1.2.3', '2.1.0', '2.1.0'];
        yield 'exact keeps v prefix' => ['v1.2.3', '2.1.0', 'v2.1.0'];
        yield 'caret keeps v prefix' => ['^v1.2.3', '2.1.0', '^v2.1.0'];
        yield 'new version with v prefix' => ['^1.2.3', 'v2.1.0', '^2.1.0'];
        yield 'pads missing segments' => ['^1.2.3', '2.1', '^2.1.0'];
        yield 'pre-release keeps suffix' => ['^1.2.3', '2.0.0-RC1', '^2.0.0-RC1'];
        yield 'pre-release on exact pin' => ['1.2.3', '2.0.0-beta2', '2.0.0-beta2'];
        yield 'complex range falls back to caret' => ['>=1.2 <2.0', '2.1.0', '^2.1.0'];
        yield 'or range falls back to caret' => ['^1.0 || ^2.0', '3.1.0', '^3.1.0'];
        yield 'as alias falls back to caret' => ['dev-main as 1.0.0', '2.0.0', '^2.0.0'];
    }

    #[DataProvider('provideConstraints')]
    public function testUpdate(string $constraint, string $newVersion, string $expected): void
    {
        self::assertSame($expected, ConstraintUpdater::update($constraint, $newVersion));
    }
}
