<?php

namespace App\Command;

use App\Entity\Location;
use App\Enum\ReviewStatus;
use App\Enum\SubmissionType;
use App\Repository\SubmissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backfill-pending-locations',
    description: 'Create pending Location rows for open New submissions without a location',
)]
final class BackfillPendingLocationsCommand extends Command
{
    public function __construct(
        private readonly SubmissionRepository $submissions,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $open = $this->submissions->createQueryBuilder('s')
            ->andWhere('s.type = :type')
            ->andWhere('s.reviewStatus = :open')
            ->andWhere('s.location IS NULL')
            ->setParameter('type', SubmissionType::New)
            ->setParameter('open', ReviewStatus::Open)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($open as $submission) {
            $location = new Location($submission->getMap());
            $location->applyPayload($submission->getPayload());
            $submission->setLocation($location);
            $this->entityManager->persist($location);
            ++$count;
        }

        $this->entityManager->flush();
        $io->success(sprintf('Backfilled %d pending location(s).', $count));

        return Command::SUCCESS;
    }
}
