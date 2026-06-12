<?php

declare(strict_types=1);

namespace ComposerCheckUpdates\Command;

use Composer\Command\BaseCommand;
use Composer\Factory;
use Composer\Json\JsonManipulator;
use ComposerCheckUpdates\Grouper;
use ComposerCheckUpdates\Model\Group;
use ComposerCheckUpdates\Model\UpdateEntry;
use ComposerCheckUpdates\Tui\InteractiveSelector;
use ComposerCheckUpdates\UpdateFinder;
use ComposerCheckUpdates\Version\ConstraintUpdater;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

final class CheckUpdatesCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('check-updates')
            ->setAliases(['ccu'])
            ->setDescription('Check for dependency updates within and beyond your version ranges, then apply them interactively')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Apply all updates without the interactive picker (see --target)')
            ->addOption('target', 't', InputOption::VALUE_REQUIRED, 'With --all, which update to pick when a package has both: "latest" or "in-range"', 'latest')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only list available updates, never modify anything')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format for non-interactive listing: "group" or "json"', 'group')
            ->addOption('ignore-platform-reqs', null, InputOption::VALUE_NONE, 'Ignore php and extension requirements when resolving candidates');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->requireComposer();
        $io = $this->getIO();

        $target = $input->getOption('target');
        if (!in_array($target, ['latest', 'in-range'], true)) {
            $io->writeError('<error>--target must be "latest" or "in-range"</error>');

            return 1;
        }

        $format = $input->getOption('format');
        if (!in_array($format, ['group', 'json'], true)) {
            $io->writeError('<error>--format must be "group" or "json"</error>');

            return 1;
        }

        $finder = new UpdateFinder($composer, $io);
        $updates = $finder->find((bool) $input->getOption('ignore-platform-reqs'));

        if ($updates === []) {
            $io->write('<info>All dependencies match the latest available versions.</info>');

            return 0;
        }

        $groups = Grouper::group($updates);

        if ($input->getOption('dry-run') || (!$input->getOption('all') && !$this->isInteractive($input))) {
            $this->printListing($groups, $format);

            return 0;
        }

        if ($input->getOption('all')) {
            $selected = Grouper::selectAll($groups, $target);
        } else {
            $terminal = new Terminal();
            $selector = new InteractiveSelector(
                STDIN,
                STDOUT,
                $terminal->getWidth(),
                $terminal->getHeight(),
                !getenv('CCU_NO_TTY_MANAGE'),
            );
            $selected = $selector->run($groups);

            if ($selected === null) {
                $io->writeError('Aborted, nothing changed.');

                return 130;
            }
        }

        if ($selected === []) {
            $io->write('No updates selected, nothing changed.');

            return 0;
        }

        return $this->apply($selected, (bool) $input->getOption('dry-run'));
    }

    /**
     * @param UpdateEntry[] $selected
     */
    private function apply(array $selected, bool $dryRun): int
    {
        $io = $this->getIO();
        $composerFile = Factory::getComposerFile();

        $constraintChanges = [];
        foreach ($selected as $entry) {
            if ($entry->requiresConstraintChange) {
                $constraintChanges[] = $entry;
            }
        }

        if ($constraintChanges !== []) {
            $manipulator = new JsonManipulator((string) file_get_contents($composerFile));
            $io->write('');
            $io->write(sprintf('Updating <info>%s</info>:', $composerFile));
            foreach ($constraintChanges as $entry) {
                $newConstraint = ConstraintUpdater::update($entry->constraint, $entry->to);
                if (!$manipulator->addLink($entry->isDev ? 'require-dev' : 'require', $entry->package, $newConstraint, false)) {
                    $io->writeError(sprintf('<error>Could not update constraint for %s in %s</error>', $entry->package, $composerFile));

                    return 1;
                }
                $io->write(sprintf('  - %s: <comment>%s</comment> → <info>%s</info>', $entry->package, $entry->constraint, $newConstraint));
            }
            if (!$dryRun) {
                file_put_contents($composerFile, $manipulator->getContents());
            }
        }

        $packages = array_map(static fn (UpdateEntry $e) => $e->package, $selected);
        $args = array_merge(['update'], $packages);
        if ($constraintChanges !== []) {
            $args[] = '--with-all-dependencies';
        }

        if ($dryRun) {
            $io->write(sprintf('Would run: <info>composer %s</info>', implode(' ', $args)));

            return 0;
        }

        return $this->runComposer($args);
    }

    /**
     * Re-invoke composer in a subprocess with inherited stdio so the
     * regular update output (progress, prompts) passes straight through.
     *
     * @param string[] $args
     */
    private function runComposer(array $args): int
    {
        $io = $this->getIO();

        $composerBin = getenv('COMPOSER_BINARY');
        if ($composerBin === false || $composerBin === '') {
            $argv = $_SERVER['argv'] ?? null;
            $composerBin = is_array($argv) && isset($argv[0]) && is_string($argv[0]) ? $argv[0] : null;
        }
        if ($composerBin === null || !is_file($composerBin)) {
            $io->writeError(sprintf(
                '<warning>Could not locate the composer binary. Run this yourself:</warning> composer %s',
                implode(' ', $args),
            ));

            return 1;
        }

        $command = array_merge([PHP_BINARY, $composerBin], array_values($args));
        $io->write('');
        $io->write(sprintf('Running <info>composer %s</info>', implode(' ', $args)));

        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
        if (!is_resource($process)) {
            $io->writeError('<error>Failed to start composer subprocess.</error>');

            return 1;
        }

        return proc_close($process);
    }

    private function isInteractive(InputInterface $input): bool
    {
        if (getenv('CCU_FORCE_INTERACTIVE')) {
            return true;
        }

        return $input->isInteractive()
            && function_exists('stream_isatty')
            && @stream_isatty(STDIN)
            && @stream_isatty(STDOUT);
    }

    /**
     * @param Group[] $groups
     */
    private function printListing(array $groups, string $format): void
    {
        $io = $this->getIO();

        if ($format === 'json') {
            $data = [];
            foreach ($groups as $group) {
                foreach ($group->entries as $entry) {
                    $data[$entry->package] ??= [
                        'constraint' => $entry->constraint,
                        'installed' => $entry->from,
                        'dev' => $entry->isDev,
                        'inRange' => null,
                        'latest' => null,
                    ];
                    if ($entry->requiresConstraintChange) {
                        $data[$entry->package]['latest'] = $entry->to;
                        $data[$entry->package]['suggestedConstraint'] = ConstraintUpdater::update($entry->constraint, $entry->to);
                    } else {
                        $data[$entry->package]['inRange'] = $entry->to;
                    }
                }
            }
            $io->write((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        $nameWidth = 0;
        $fromWidth = 0;
        foreach ($groups as $group) {
            foreach ($group->entries as $entry) {
                $nameWidth = max($nameWidth, strlen($entry->package) + ($entry->isDev ? 6 : 0));
                $fromWidth = max($fromWidth, strlen($entry->from));
            }
        }

        $colorTag = [Group::IN_RANGE => 'green', Group::PATCH => 'green', Group::MINOR => 'cyan', Group::MAJOR => 'red', Group::OTHER => 'magenta'];
        foreach ($groups as $group) {
            $io->write('');
            $io->write(sprintf('<fg=%s;options=bold>%s</>  %s', $colorTag[$group->key], $group->title, $group->subtitle));
            foreach ($group->entries as $entry) {
                $line = sprintf(
                    '  %s  %s  →  <fg=%s>%s</>',
                    str_pad($entry->package . ($entry->isDev ? '  (dev)' : ''), $nameWidth + 2),
                    str_pad($entry->from, $fromWidth, ' ', STR_PAD_LEFT),
                    $colorTag[$group->key],
                    $entry->to,
                );
                if ($entry->note !== null) {
                    $line .= sprintf('   (%s)', $entry->note);
                }
                $io->write($line);
            }
        }

        $io->write('');
        $io->write('Run <info>composer check-updates</info> in a terminal to pick updates interactively, or use <info>--all [--target in-range|latest]</info>.');
    }
}
