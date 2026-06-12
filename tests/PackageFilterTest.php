<?php

declare(strict_types=1);

use ComposerCheckUpdates\PackageFilter;

it('matches names against wildcard patterns', function (string $package, string $pattern, bool $expected): void {
    expect(PackageFilter::matches($package, $pattern))->toBe($expected);
})->with([
    'exact match' => ['monolog/monolog', 'monolog/monolog', true],
    'vendor wildcard' => ['symfony/console', 'symfony/*', true],
    'vendor wildcard miss' => ['monolog/monolog', 'symfony/*', false],
    'suffix wildcard' => ['phpstan/phpstan-strict-rules', '*-strict-rules', true],
    'inner wildcard' => ['symfony/http-kernel', 'symfony/*kernel', true],
    'case insensitive' => ['Symfony/Console', 'symfony/console', true],
    'no partial match without wildcard' => ['symfony/console', 'symfony', false],
    'regex chars are literal' => ['acme/pkg.name', 'acme/pkg.name', true],
    'dot does not act as regex any' => ['acme/pkgxname', 'acme/pkg.name', false],
]);

it('accepts everything by default', function (): void {
    $filter = new PackageFilter();

    expect($filter->accepts('monolog/monolog', false))->toBeTrue()
        ->and($filter->accepts('phpunit/phpunit', true))->toBeTrue();
});

it('only accepts packages matching a filter when filters are set', function (): void {
    $filter = new PackageFilter(filters: ['symfony/*', 'monolog/*']);

    expect($filter->accepts('symfony/console', false))->toBeTrue()
        ->and($filter->accepts('monolog/monolog', false))->toBeTrue()
        ->and($filter->accepts('doctrine/orm', false))->toBeFalse();
});

it('lets rejects win over filters', function (): void {
    $filter = new PackageFilter(filters: ['symfony/*'], rejects: ['symfony/console']);

    expect($filter->accepts('symfony/console', false))->toBeFalse()
        ->and($filter->accepts('symfony/process', false))->toBeTrue();
});

it('restricts to dev or prod dependencies', function (): void {
    $devOnly = new PackageFilter(devOnly: true);
    $prodOnly = new PackageFilter(prodOnly: true);

    expect($devOnly->accepts('phpunit/phpunit', true))->toBeTrue()
        ->and($devOnly->accepts('monolog/monolog', false))->toBeFalse()
        ->and($prodOnly->accepts('monolog/monolog', false))->toBeTrue()
        ->and($prodOnly->accepts('phpunit/phpunit', true))->toBeFalse();
});
