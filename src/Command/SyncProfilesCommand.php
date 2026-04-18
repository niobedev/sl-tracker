<?php

namespace App\Command;

use App\Repository\TrackedAvatarRepository;
use App\Service\SecondLifeProfileService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:sync-profiles', description: 'Re-sync all AvatarProfiles from Second Life')]
class SyncProfilesCommand extends Command
{
    public function __construct(
        private readonly TrackedAvatarRepository $trackedAvatarRepository,
        private readonly SecondLifeProfileService $profileService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force refresh even if not stale');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $avatars = $this->trackedAvatarRepository->findEnabled();
        $total = count($avatars);

        if ($total === 0) {
            $io->warning('No tracked avatars found.');
            return Command::SUCCESS;
        }

        $io->info("Found {$total} tracked avatars.");
        $io->progressStart($total);

        $success = 0;
        $failed = 0;

        foreach ($avatars as $avatar) {
            $key = $avatar->getAvatarKey();
            $io->progressAdvance(1);

            $profile = $this->profileService->fetchProfile($key, $force);
            if ($profile !== null) {
                $success++;
            } else {
                $failed++;
            }
        }

        $io->progressFinish();

        $io->success("Synced {$success}/{$total} avatars." . ($failed > 0 ? " ({$failed} failed)" : ''));
        return $failed > 0 && $success === 0 ? Command::FAILURE : Command::SUCCESS;
    }
}