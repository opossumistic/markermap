<?php

namespace App\Command;

use App\Repository\MapRepository;
use App\Service\LocationWorkflow;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-demo', description: 'Seed one open location submission for inbox testing')]
final class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly LocationWorkflow $workflow,
        private readonly MapRepository $mapRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $map = $this->mapRepository->getDefaultMap();

        $submission = $this->workflow->submitNew($map, [
            'street' => 'Schanzenstraße 1',
            'postal_code' => '20357',
            'district' => 'Sternschanze',
            'lat' => 53.5615,
            'lng' => 9.9655,
            'description' => 'Demo-Eintrag ohne Namen',
            'categories' => ['books', 'toys'],
        ], 'test@example.com');

        $io->success(sprintf('Open submission #%d created. Review at /admin', $submission->getId()));

        return Command::SUCCESS;
    }
}
