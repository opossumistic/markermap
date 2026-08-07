<?php

namespace App\Command;

use App\Entity\Location;
use App\Enum\LocationCategory;
use App\Repository\LocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:seed-community',
    description: 'Import community-list locations from data/community-locations.json',
)]
final class SeedCommunityLocationsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LocationRepository $locationRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('purge', null, InputOption::VALUE_NONE, 'Delete all existing locations before import')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'JSON path relative to project root', 'data/community-locations.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $relative = (string) $input->getOption('file');
        $path = $this->projectDir.'/'.$relative;

        if (!is_readable($path)) {
            $io->error(sprintf('Seed file not readable: %s', $path));

            return Command::FAILURE;
        }

        /** @var array{meta?: array<string, mixed>, locations?: list<array<string, mixed>>} $payload */
        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $rows = $payload['locations'] ?? [];
        if ($rows === []) {
            $io->warning('No locations in seed file.');

            return Command::SUCCESS;
        }

        if ($input->getOption('purge')) {
            foreach ($this->locationRepository->findAll() as $existing) {
                $this->entityManager->remove($existing);
            }
            $this->entityManager->flush();
            $io->note('Purged existing locations.');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $category = LocationCategory::tryFrom((string) ($row['category'] ?? 'other')) ?? LocationCategory::Other;
            // Markermap = Tauschboxen; reine Bücherzellen sind Rauschen und werden nicht importiert.
            if ($category === LocationCategory::Books) {
                ++$skipped;
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $lat = isset($row['lat']) ? (float) $row['lat'] : 0.0;
            $lng = isset($row['lng']) ? (float) $row['lng'] : 0.0;
            if ($title === '' || $lat === 0.0 || $lng === 0.0) {
                ++$skipped;
                continue;
            }

            $street = isset($row['street']) ? trim((string) $row['street']) : null;
            $location = $this->findExisting($title, $street, $lat, $lng);
            $isNew = $location === null;
            if ($isNew) {
                $location = new Location();
            }

            $description = isset($row['description']) && $row['description'] !== null && $row['description'] !== ''
                ? trim((string) $row['description'])
                : null;

            $location
                ->setTitle($title)
                ->setStreet($street !== '' ? $street : null)
                ->setPostalCode(isset($row['postal_code']) && $row['postal_code'] !== null && $row['postal_code'] !== ''
                    ? (string) $row['postal_code']
                    : null)
                ->setDistrict(isset($row['district']) && $row['district'] !== null && $row['district'] !== ''
                    ? (string) $row['district']
                    : null)
                ->setLat($lat)
                ->setLng($lng)
                ->setDescription($description)
                ->setCategory($category);

            $status = (string) ($row['status'] ?? 'active');
            if ($status === 'removed') {
                $location->softRemove();
            } else {
                $location->activate();
            }

            if ($isNew) {
                $this->entityManager->persist($location);
                ++$created;
            } else {
                ++$updated;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Community seed done: %d created, %d updated, %d skipped. File meta count=%s',
            $created,
            $updated,
            $skipped,
            (string) ($payload['meta']['count'] ?? '?'),
        ));

        return Command::SUCCESS;
    }

    private function findExisting(string $title, ?string $street, float $lat, float $lng): ?Location
    {
        foreach ($this->locationRepository->findAll() as $existing) {
            if ($existing->getTitle() === $title
                && ($street === null || $existing->getStreet() === $street)
            ) {
                return $existing;
            }

            // ~80 m: same pin from a previous seed run
            if (abs($existing->getLat() - $lat) < 0.0007
                && abs($existing->getLng() - $lng) < 0.0007
                && mb_strtolower($existing->getTitle()) === mb_strtolower($title)
            ) {
                return $existing;
            }
        }

        return null;
    }
}
