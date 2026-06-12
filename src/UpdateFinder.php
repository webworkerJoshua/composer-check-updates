<?php

declare(strict_types=1);

namespace ComposerCheckUpdates;

use Composer\Composer;
use Composer\Filter\PlatformRequirementFilter\PlatformRequirementFilterFactory;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Package\Version\VersionSelector;
use Composer\Repository\CompositeRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositorySet;
use Composer\Semver\Comparator;
use ComposerCheckUpdates\Model\FoundUpdate;

final class UpdateFinder
{
    public function __construct(
        private readonly Composer $composer,
        private readonly IOInterface $io,
    ) {
    }

    /**
     * Check every root requirement (require + require-dev) for the best
     * version inside its constraint and the best version overall.
     *
     * @return FoundUpdate[] only packages that actually have an update
     */
    public function find(bool $ignorePlatformReqs = false): array
    {
        $root = $this->composer->getPackage();
        $installedRepo = $this->getInstalledRepository();

        $repositorySet = new RepositorySet($root->getMinimumStability(), $root->getStabilityFlags());
        $repositorySet->addRepository(new CompositeRepository($this->composer->getRepositoryManager()->getRepositories()));

        $platformOverrides = $this->composer->getConfig()->get('platform') ?: [];
        $platformRepo = new PlatformRepository([], $platformOverrides);
        $selector = new VersionSelector($repositorySet, $platformRepo);

        $platformFilter = $ignorePlatformReqs
            ? PlatformRequirementFilterFactory::ignoreAll()
            : PlatformRequirementFilterFactory::ignoreNothing();

        $requireSets = [
            ['links' => $root->getRequires(), 'dev' => false],
            ['links' => $root->getDevRequires(), 'dev' => true],
        ];

        $total = 0;
        foreach ($requireSets as $set) {
            $total += count($set['links']);
        }

        $results = [];
        $done = 0;
        foreach ($requireSets as $set) {
            foreach ($set['links'] as $link) {
                $done++;
                $name = $link->getTarget();
                if (PlatformRepository::isPlatformPackage($name)) {
                    continue;
                }

                $installed = $installedRepo?->findPackage($name, '*');
                if ($installed === null) {
                    continue;
                }

                // dev-* installs (branches) have no meaningful version ordering
                if ($installed->getStability() === 'dev' && !preg_match('/^\d/', ltrim($installed->getPrettyVersion(), 'vV'))) {
                    continue;
                }

                if ($this->io->isDecorated()) {
                    $this->io->overwriteError(sprintf('Checking packages... %d/%d <info>%s</info>', $done, $total, $name), false);
                }

                $preferredStability = $installed->getStability();

                $bestInRange = $selector->findBestCandidate($name, $link->getPrettyConstraint(), $preferredStability, $platformFilter);
                $bestOverall = $selector->findBestCandidate($name, null, $preferredStability, $platformFilter);

                $inRange = null;
                $ceiling = $installed->getVersion();
                if ($bestInRange instanceof PackageInterface && Comparator::greaterThan($bestInRange->getVersion(), $installed->getVersion())) {
                    $inRange = $bestInRange->getPrettyVersion();
                    $ceiling = $bestInRange->getVersion();
                }

                $latest = null;
                if ($bestOverall instanceof PackageInterface && Comparator::greaterThan($bestOverall->getVersion(), $ceiling)) {
                    $latest = $bestOverall->getPrettyVersion();
                }

                if ($inRange !== null || $latest !== null) {
                    $results[] = new FoundUpdate(
                        $name,
                        $set['dev'],
                        $link->getPrettyConstraint(),
                        $installed->getPrettyVersion(),
                        $inRange,
                        $latest,
                    );
                }
            }
        }

        if ($this->io->isDecorated()) {
            $this->io->overwriteError('', false);
        }
        $this->io->writeError(sprintf('Checked %d root requirements.', $total), true, IOInterface::VERBOSE);

        return $results;
    }

    private function getInstalledRepository(): ?RepositoryInterface
    {
        $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();
        if (count($localRepo->getPackages()) > 0) {
            return $localRepo;
        }

        $locker = $this->composer->getLocker();
        if ($locker->isLocked()) {
            return $locker->getLockedRepository(true);
        }

        return null;
    }
}
