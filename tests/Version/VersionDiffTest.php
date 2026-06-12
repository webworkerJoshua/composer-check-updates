<?php

declare(strict_types=1);

namespace ComposerCheckUpdates\Tests\Version;

use ComposerCheckUpdates\Version\VersionDiff;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VersionDiffTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideVersions(): iterable
    {
        yield 'patch' => ['1.2.3', '1.2.4', VersionDiff::PATCH];
        yield 'minor' => ['1.2.3', '1.3.0', VersionDiff::MINOR];
        yield 'major' => ['1.2.3', '2.0.0', VersionDiff::MAJOR];
        yield 'major across many' => ['1.2.3', '4.0.1', VersionDiff::MAJOR];
        yield 'zero-major minor' => ['0.1.0', '0.2.0', VersionDiff::MINOR];
        yield 'v prefix' => ['v1.2.3', 'v2.0.0', VersionDiff::MAJOR];
        yield 'mixed prefix' => ['1.2.3', 'v1.3.0', VersionDiff::MINOR];
        yield 'two segment versions' => ['1.2', '1.3', VersionDiff::MINOR];
        yield 'same' => ['1.2.3', '1.2.3', VersionDiff::NONE];
        yield 'stability suffix counts as patch' => ['1.2.3', '1.2.3-p1', VersionDiff::PATCH];
        yield 'dev version unknown' => ['dev-main', '1.2.3', VersionDiff::UNKNOWN];
    }

    #[DataProvider('provideVersions')]
    public function testBetween(string $from, string $to, string $expected): void
    {
        self::assertSame($expected, VersionDiff::between($from, $to));
    }
}
