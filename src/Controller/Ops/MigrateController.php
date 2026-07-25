<?php

namespace App\Controller\Ops;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * One-shot ops for shared hosting without SSH.
 * Call manually after deploy — never linked from the UI.
 */
final class MigrateController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(MIGRATE_TOKEN)%')]
        private readonly string $migrateToken,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('/_ops/migrate', name: 'ops_migrate', methods: ['POST'])]
    public function __invoke(Request $request, KernelInterface $kernel, Connection $connection): JsonResponse
    {
        $provided = $request->headers->get('X-Migrate-Token')
            ?? (string) $request->request->get('token', '');

        if ($this->migrateToken === '' || $this->migrateToken === 'change-me') {
            return $this->json(['ok' => false, 'error' => 'MIGRATE_TOKEN not configured'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if ($provided === '' || !hash_equals($this->migrateToken, $provided)) {
            return $this->json(['ok' => false, 'error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $backup = $this->backupSqliteIfPossible($connection);

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--no-interaction' => true,
            '--allow-no-migration' => true,
        ]);
        $output = new BufferedOutput();
        $exitCode = $application->run($input, $output);

        return $this->json([
            'ok' => $exitCode === 0,
            'exitCode' => $exitCode,
            'backup' => $backup,
            'output' => $output->fetch(),
        ], $exitCode === 0 ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    private function backupSqliteIfPossible(Connection $connection): ?string
    {
        $params = $connection->getParams();
        $path = $params['path'] ?? null;
        if (!\is_string($path) || $path === '' || !is_file($path)) {
            return null;
        }

        $backupDir = $this->projectDir.'/var/data/backups';
        if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
            return null;
        }

        $target = $backupDir.'/app-'.date('Ymd-His').'.db';
        if (!@copy($path, $target)) {
            return null;
        }

        return 'var/data/backups/'.basename($target);
    }
}
