<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Location.publicId for printed QR / deep links + backfill.
 */
final class Version20260808085000 extends AbstractMigration
{
    private const ALPHABET = '23456789abcdefghijkmnpqrstuvwxyz';
    private const LENGTH = 12;

    public function getDescription(): string
    {
        return 'Add locations.public_id (immutable), backfill, unique index';
    }

    public function up(Schema $schema): void
    {
        $this->connection->executeStatement('ALTER TABLE locations ADD COLUMN public_id VARCHAR(12) DEFAULT NULL');

        $ids = $this->connection->fetchFirstColumn('SELECT id FROM locations');
        $used = [];
        foreach ($ids as $id) {
            do {
                $publicId = $this->newPublicId();
            } while (isset($used[$publicId]));
            $used[$publicId] = true;
            $this->connection->update('locations', ['public_id' => $publicId], ['id' => (int) $id]);
        }

        $this->connection->executeStatement('CREATE TEMPORARY TABLE __temp__locations AS SELECT id, map_id, title, street, postal_code, district, lat, lng, description, categories, image_path, status, deleted_at, confirmed_at, created_at, updated_at, public_id FROM locations');
        $this->connection->executeStatement('DROP TABLE locations');
        $this->connection->executeStatement('CREATE TABLE locations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, map_id INTEGER NOT NULL, title VARCHAR(180) DEFAULT NULL, street VARCHAR(180) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, district VARCHAR(100) DEFAULT NULL, lat DOUBLE PRECISION NOT NULL, lng DOUBLE PRECISION NOT NULL, description CLOB DEFAULT NULL, categories CLOB NOT NULL, image_path VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, deleted_at DATETIME DEFAULT NULL, confirmed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, public_id VARCHAR(12) NOT NULL, CONSTRAINT FK_17E64ABA53C55F64 FOREIGN KEY (map_id) REFERENCES maps (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->connection->executeStatement('INSERT INTO locations (id, map_id, title, street, postal_code, district, lat, lng, description, categories, image_path, status, deleted_at, confirmed_at, created_at, updated_at, public_id) SELECT id, map_id, title, street, postal_code, district, lat, lng, description, categories, image_path, status, deleted_at, confirmed_at, created_at, updated_at, public_id FROM __temp__locations');
        $this->connection->executeStatement('DROP TABLE __temp__locations');
        $this->connection->executeStatement('CREATE INDEX IDX_17E64ABA53C55F64 ON locations (map_id)');
        $this->connection->executeStatement('CREATE UNIQUE INDEX uniq_locations_public_id ON locations (public_id)');
    }

    public function down(Schema $schema): void
    {
        $this->connection->executeStatement('CREATE TEMPORARY TABLE __temp__locations AS SELECT id, map_id, title, street, postal_code, district, lat, lng, description, categories, image_path, status, deleted_at, confirmed_at, created_at, updated_at FROM locations');
        $this->connection->executeStatement('DROP TABLE locations');
        $this->connection->executeStatement('CREATE TABLE locations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, map_id INTEGER NOT NULL, title VARCHAR(180) DEFAULT NULL, street VARCHAR(180) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, district VARCHAR(100) DEFAULT NULL, lat DOUBLE PRECISION NOT NULL, lng DOUBLE PRECISION NOT NULL, description CLOB DEFAULT NULL, categories CLOB NOT NULL, image_path VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, deleted_at DATETIME DEFAULT NULL, confirmed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_17E64ABA53C55F64 FOREIGN KEY (map_id) REFERENCES maps (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->connection->executeStatement('INSERT INTO locations (id, map_id, title, street, postal_code, district, lat, lng, description, categories, image_path, status, deleted_at, confirmed_at, created_at, updated_at) SELECT id, map_id, title, street, postal_code, district, lat, lng, description, categories, image_path, status, deleted_at, confirmed_at, created_at, updated_at FROM __temp__locations');
        $this->connection->executeStatement('DROP TABLE __temp__locations');
        $this->connection->executeStatement('CREATE INDEX IDX_17E64ABA53C55F64 ON locations (map_id)');
    }

    private function newPublicId(): string
    {
        $alphabet = self::ALPHABET;
        $max = \strlen($alphabet) - 1;
        $id = '';
        for ($i = 0; $i < self::LENGTH; ++$i) {
            $id .= $alphabet[random_int(0, $max)];
        }

        return $id;
    }
}
