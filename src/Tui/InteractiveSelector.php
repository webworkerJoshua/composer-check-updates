<?php

declare(strict_types=1);

namespace ComposerCheckUpdates\Tui;

use ComposerCheckUpdates\Model\Group;
use ComposerCheckUpdates\Model\UpdateEntry;
use ComposerCheckUpdates\Version\VersionDiff;

/**
 * Minimal full-line checklist UI: ↑/↓ to move, Space to toggle, a/n for
 * all/none, Enter to confirm, q/Esc/Ctrl-C to abort.
 *
 * Entries sharing a package name are mutually exclusive — selecting the
 * major jump deselects the in-range update of the same package and vice
 * versa.
 *
 * Input/output are injectable streams so the whole interaction is unit
 * testable without a real terminal.
 */
final class InteractiveSelector
{
    private const COLORS = [
        'green' => '32',
        'cyan' => '36',
        'red' => '31',
        'magenta' => '35',
        'yellow' => '33',
    ];

    /** @var resource */
    private $input;
    /** @var resource */
    private $output;

    private int $width;
    private int $height;
    private bool $manageTty;

    private int $renderedLines = 0;

    /**
     * @param resource $input
     * @param resource $output
     */
    public function __construct($input, $output, int $width = 110, int $height = 35, bool $manageTty = true)
    {
        $this->input = $input;
        $this->output = $output;
        $this->width = max(60, $width);
        $this->height = max(10, $height);
        $this->manageTty = $manageTty;
    }

    /**
     * @param Group[] $groups
     * @return UpdateEntry[]|null selected entries in display order, or null when aborted
     */
    public function run(array $groups): ?array
    {
        $entries = [];
        foreach ($groups as $group) {
            foreach ($group->entries as $entry) {
                $entries[] = $entry;
            }
        }
        if ($entries === []) {
            return [];
        }

        $sttyState = null;
        if ($this->manageTty) {
            $sttyState = trim((string) shell_exec('stty -g 2>/dev/null'));
            shell_exec('stty -icanon -echo min 1 time 0 2>/dev/null');
            $this->write("\x1b[?25l"); // hide cursor
        }

        $cursor = 0;
        try {
            while (true) {
                $this->render($groups, $entries, $cursor);

                switch ($this->readKey()) {
                    case 'up':
                        $cursor = ($cursor - 1 + count($entries)) % count($entries);
                        break;
                    case 'down':
                        $cursor = ($cursor + 1) % count($entries);
                        break;
                    case 'space':
                        $this->toggle($entries, $cursor);
                        break;
                    case 'all':
                        $this->selectAll($entries);
                        break;
                    case 'none':
                        foreach ($entries as $entry) {
                            $entry->selected = false;
                        }
                        break;
                    case 'enter':
                        $this->render($groups, $entries, $cursor);

                        return array_values(array_filter($entries, static fn (UpdateEntry $e) => $e->selected));
                    case 'abort':
                        return null;
                }
            }
        } finally {
            if ($this->manageTty) {
                $this->write("\x1b[?25h"); // show cursor
                if ($sttyState !== null && $sttyState !== '') {
                    shell_exec('stty ' . escapeshellarg($sttyState) . ' 2>/dev/null');
                }
            }
            $this->write("\n");
        }
    }

    /**
     * @param UpdateEntry[] $entries
     */
    private function toggle(array $entries, int $cursor): void
    {
        $current = $entries[$cursor];
        $current->selected = !$current->selected;

        if ($current->selected) {
            foreach ($entries as $i => $entry) {
                if ($i !== $cursor && $entry->package === $current->package) {
                    $entry->selected = false;
                }
            }
        }
    }

    /**
     * Select everything; when a package appears in several groups prefer
     * the in-range entry (the conservative choice — majors stay opt-in).
     *
     * Pressing "a" with everything already selected deselects all.
     *
     * @param UpdateEntry[] $entries
     */
    private function selectAll(array $entries): void
    {
        $seen = [];
        $target = [];
        foreach ($entries as $i => $entry) {
            $prev = $seen[$entry->package] ?? null;
            if ($prev === null || (!$entry->requiresConstraintChange && $entries[$prev]->requiresConstraintChange)) {
                $seen[$entry->package] = $i;
            }
        }
        foreach ($seen as $i) {
            $target[$i] = true;
        }

        $alreadyAll = true;
        foreach ($entries as $i => $entry) {
            if ($entry->selected !== isset($target[$i])) {
                $alreadyAll = false;
                break;
            }
        }

        foreach ($entries as $i => $entry) {
            $entry->selected = !$alreadyAll && isset($target[$i]);
        }
    }

    private function readKey(): string
    {
        $char = fgetc($this->input);
        if ($char === false) {
            return 'abort'; // EOF — never spin
        }

        switch ($char) {
            case "\x03":
            case 'q':
                return 'abort';
            case "\n":
            case "\r":
                return 'enter';
            case ' ':
                return 'space';
            case 'a':
                return 'all';
            case 'n':
                return 'none';
            case 'k':
                return 'up';
            case 'j':
                return 'down';
            case "\x1b":
                $seq = $this->readEscapeSequence();
                if ($seq === '[A') {
                    return 'up';
                }
                if ($seq === '[B') {
                    return 'down';
                }
                if ($seq === '') {
                    return 'abort'; // bare Esc
                }

                return 'noop';
        }

        return 'noop';
    }

    private function readEscapeSequence(): string
    {
        $meta = stream_get_meta_data($this->input);
        $isTty = function_exists('stream_isatty') && @stream_isatty($this->input);

        if ($isTty) {
            // Distinguish a bare Esc press from an escape sequence
            stream_set_blocking($this->input, false);
            usleep(5000);
        }

        $seq = '';
        for ($i = 0; $i < 2; $i++) {
            $char = fgetc($this->input);
            if ($char === false) {
                break;
            }
            $seq .= $char;
            if ($i === 0 && $char !== '[') {
                break;
            }
        }

        if ($isTty) {
            stream_set_blocking($this->input, $meta['blocked']);
        }

        return $seq;
    }

    /**
     * @param Group[] $groups
     * @param UpdateEntry[] $entries
     */
    private function render(array $groups, array $entries, int $cursor): void
    {
        $nameWidth = 0;
        $fromWidth = 0;
        foreach ($entries as $entry) {
            $nameWidth = max($nameWidth, strlen($entry->package) + ($entry->isDev ? 6 : 0));
            $fromWidth = max($fromWidth, strlen($entry->from) + 1);
        }

        $selectedCount = 0;
        $jsonChanges = 0;
        foreach ($entries as $entry) {
            if ($entry->selected) {
                $selectedCount++;
                if ($entry->requiresConstraintChange) {
                    $jsonChanges++;
                }
            }
        }

        $lines = [];
        $lines[] = $this->bold('? Choose which packages to update') . $this->dim('  ·  ↑/↓ move · Space select · a all · n none · Enter apply · q quit');
        $cursorLine = 0;

        $index = 0;
        foreach ($groups as $group) {
            $lines[] = '';
            $lines[] = $this->color($group->color, $this->bold($group->title)) . '  ' . $this->dim($group->subtitle);

            foreach ($group->entries as $entry) {
                $isCursor = $index === $cursor;
                if ($isCursor) {
                    $cursorLine = count($lines);
                }

                $pointer = $isCursor ? $this->color('cyan', '❯') : ' ';
                $checkbox = $entry->selected ? $this->color('green', '◉') : $this->dim('◯');
                $name = str_pad($entry->package . ($entry->isDev ? '  (dev)' : ''), $nameWidth + 2);
                $from = str_pad($entry->from, $fromWidth, ' ', STR_PAD_LEFT);
                $to = $this->colorizeTarget($entry->from, $entry->to, $group->color);

                $line = sprintf(' %s %s %s%s  %s  %s', $pointer, $checkbox, $name, $from, $this->dim('→'), $to);
                if ($entry->note !== null) {
                    $line .= '   ' . $this->dim('(' . $entry->note . ')');
                }
                $lines[] = $line;
                $index++;
            }
        }

        $lines[] = '';
        $summary = sprintf('%d of %d selected', $selectedCount, count($entries));
        if ($jsonChanges > 0) {
            $summary .= sprintf(' · %d composer.json change%s', $jsonChanges, $jsonChanges === 1 ? '' : 's');
        }
        $lines[] = $this->dim($summary);

        $lines = $this->applyViewport($lines, $cursorLine);

        $buffer = '';
        if ($this->renderedLines > 0) {
            $buffer .= "\x1b[" . $this->renderedLines . 'A';
        }
        foreach ($lines as $line) {
            $buffer .= "\x1b[2K" . $this->truncate($line, $this->width) . "\n";
        }
        $this->renderedLines = count($lines);
        $this->write($buffer);
    }

    /**
     * Keep the rendered block within the terminal height by windowing
     * around the cursor while pinning the header and footer lines.
     *
     * @param string[] $lines
     * @return string[]
     */
    private function applyViewport(array $lines, int $cursorLine): array
    {
        $max = $this->height - 2;
        if (count($lines) <= $max) {
            return $lines;
        }

        $header = [$lines[0]];
        $footer = array_slice($lines, -2);
        $body = array_slice($lines, 1, count($lines) - 3);
        $bodyCursor = max(0, $cursorLine - 1);

        $visible = $max - 3; // header + footer + scroll marker
        $start = max(0, min($bodyCursor - intdiv($visible, 2), count($body) - $visible));
        $window = array_slice($body, $start, $visible);

        $marker = [$this->dim(sprintf('  … %d more (scroll with ↑/↓)', count($body) - $visible))];

        return array_merge($header, $window, $marker, $footer);
    }

    /**
     * Color the target version from the first segment that differs from
     * the installed version (like ncu does): 13.6.0 -> 13.6.<color>1</color>
     */
    private function colorizeTarget(string $from, string $to, string $color): string
    {
        $fromParts = explode('.', ltrim($from, 'vV'));
        $toParts = explode('.', ltrim($to, 'vV'));

        $diffIndex = 0;
        foreach ($toParts as $i => $part) {
            if (!isset($fromParts[$i]) || $fromParts[$i] !== $part) {
                $diffIndex = $i;
                break;
            }
            $diffIndex = $i + 1;
        }

        if ($diffIndex >= count($toParts)) {
            return $to;
        }

        $unchanged = implode('.', array_slice($toParts, 0, $diffIndex));
        $changed = implode('.', array_slice($toParts, $diffIndex));
        $prefix = (str_starts_with($to, 'v') ? 'v' : '') . ($unchanged !== '' ? $unchanged . '.' : '');

        return $prefix . $this->color($color, $changed);
    }

    private function truncate(string $line, int $width): string
    {
        // Measure without ANSI sequences; only truncate plainly long lines
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $line) ?? $line;
        if (strlen($plain) <= $width) {
            return $line;
        }

        // Truncating styled text safely: strip styles, cut, re-dim
        return substr($plain, 0, $width - 1) . '…';
    }

    private function color(string $name, string $text): string
    {
        return "\x1b[" . (self::COLORS[$name] ?? '39') . 'm' . $text . "\x1b[39m";
    }

    private function bold(string $text): string
    {
        return "\x1b[1m" . $text . "\x1b[22m";
    }

    private function dim(string $text): string
    {
        return "\x1b[2m" . $text . "\x1b[22m";
    }

    private function write(string $text): void
    {
        fwrite($this->output, $text);
    }
}
