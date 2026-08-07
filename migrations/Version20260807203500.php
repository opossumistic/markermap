<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tauschboxen: enable categories explicitly; other maps hide category UI.
 */
final class Version20260807203500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set categories_config for tauschboxen; empty array for other maps';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE maps SET categories_config = '[\"books\",\"toys\",\"clothes\",\"household\",\"other\"]' WHERE slug = 'tauschboxen'");
        $this->addSql("UPDATE maps SET categories_config = '[]' WHERE slug != 'tauschboxen' OR categories_config IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE maps SET categories_config = NULL');
    }
}
