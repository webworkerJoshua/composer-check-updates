# composer-check-updates

[![CI](https://github.com/webworkerJoshua/composer-check-updates/actions/workflows/ci.yml/badge.svg)](https://github.com/webworkerJoshua/composer-check-updates/actions/workflows/ci.yml)

Interactive dependency update checker for Composer, inspired by
[npm-check-updates](https://github.com/raineorshine/npm-check-updates).

`composer outdated` tells you what's newer. `composer update` only moves you
within your version ranges. This plugin shows **both kinds of updates at
once** — those satisfying your `composer.json` constraints and those beyond
them (new minors/majors) — lets you pick updates with the arrow keys and
Space, rewrites `composer.json` where needed, and runs `composer update` for
exactly what you selected.

```
? Choose which packages to update  ·  ↑/↓ move · Space select · a all · n none · Enter apply · q quit

In range  satisfies composer.json — plain `composer update` would pick these
 ❯ ◉ monolog/monolog   1.25.0  →  1.27.1   (or 3.10.0, see Major)

Minor  backwards-compatible features · composer.json will be updated
   ◯ phpunit/phpunit  (dev)   11.5.2  →  11.9.1

Major  potentially breaking changes · composer.json will be updated
   ◯ monolog/monolog   1.25.0  →  3.10.0   (or 1.27.1, see In range)
   ◯ psr/log            1.0.0  →  3.0.2

1 of 4 selected
```

## Installation

```bash
composer require --dev webworkerjoshua/composer-check-updates
```

Composer will ask you to trust the plugin (`allow-plugins`); confirm with yes.

## Usage

```bash
composer check-updates        # interactive picker (alias: composer ccu)
```

- **↑/↓** (or j/k) move, **Space** selects, **a** selects all, **n** selects
  none, **Enter** applies, **q**/Esc/Ctrl-C aborts without changing anything.
- Selecting an entry from *In range* leaves `composer.json` untouched and
  just runs `composer update <package>`.
- Selecting an entry from *Patch/Minor/Major* first rewrites the constraint
  in `composer.json` (preserving your style: `^1.2` becomes `^3.10`, `1.2.*`
  becomes `3.10.*`, exact pins stay exact pins) and then runs
  `composer update <packages> --with-all-dependencies`.

### Packages with updates both inside and outside the range

A package like `monolog/monolog` with constraint `^1.0`, installed at
`1.25.0`, has two distinct upgrade paths: `1.27.1` (in range) and `3.10.0`
(major). It therefore appears **twice** — once in *In range* and once in
*Major* — and each entry cross-references the other (`or 3.10.0, see
Major`). The two entries are mutually exclusive: selecting one automatically
deselects the other, so you always make exactly one decision per package.
**a** (select all) prefers the in-range entry — majors stay opt-in.

### Non-interactive usage

```bash
composer check-updates --dry-run             # list only, change nothing
composer check-updates --format json         # machine-readable listing
composer check-updates --all                 # apply everything, prefer latest
composer check-updates --all --target in-range  # apply everything, stay in range
composer check-updates --ignore-platform-reqs   # ignore php/ext requirements
```

When stdout is not a TTY (CI, pipes) the command prints the grouped listing
instead of starting the picker.

## How it works

The plugin uses Composer's own resolver APIs (`RepositorySet` +
`VersionSelector`), so it sees exactly the packages your project sees:
custom repositories, `minimum-stability`, stability flags, and platform
requirements (your PHP version and extensions) are all respected. For each
root requirement it asks for the best candidate within your constraint and
the best candidate overall; `composer.json` edits go through Composer's
`JsonManipulator`, which preserves your file's formatting.

## Requirements

- PHP >= 8.3
- Composer >= 2.3

## Development

```bash
composer install
composer test     # Pest test suite
composer stan     # PHPStan static analysis (level max)
composer check    # both
```

## License

MIT
