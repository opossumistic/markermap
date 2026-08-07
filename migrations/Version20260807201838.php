<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Multi-map tenant core: maps table + map_id on locations/submissions.
 * Seeds Tenant #1 (tauschboxen) and backfills existing rows.
 */
final class Version20260807201838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add maps tenant table; scope locations and submissions with map_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE maps (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, slug VARCHAR(80) NOT NULL, name VARCHAR(120) NOT NULL, description CLOB DEFAULT NULL, center_lat DOUBLE PRECISION NOT NULL, center_lng DOUBLE PRECISION NOT NULL, default_zoom DOUBLE PRECISION NOT NULL, min_lat DOUBLE PRECISION DEFAULT NULL, max_lat DOUBLE PRECISION DEFAULT NULL, min_lng DOUBLE PRECISION DEFAULT NULL, max_lng DOUBLE PRECISION DEFAULT NULL, notify_email VARCHAR(180) DEFAULT NULL, categories_config CLOB DEFAULT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_maps_slug ON maps (slug)');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->addSql(
            'INSERT INTO maps (id, slug, name, description, center_lat, center_lng, default_zoom, min_lat, max_lat, min_lng, max_lng, notify_email, categories_config, status, created_at, updated_at) VALUES (1, \'tauschboxen\', \'Tauschboxen Hamburg\', \'Öffentliche Tauschboxen in Hamburg — vorschlagen, ergänzen, melden.\', 53.5511, 9.9937, 11, 53.38, 53.75, 9.7, 10.35, NULL, NULL, \'active\', ?, ?)',
            [$now, $now],
        );

        $this->addSql('CREATE TEMPORARY TABLE __temp__locations AS SELECT id, title, street, postal_code, district, lat, lng, description, categories, image_path, status, deleted_at, confirmed_at, created_at, updated_at FROM locations');
        $this->addSql('DROP TABLE locations');
        $this->addSql('CREATE TABLE locations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, map_id INTEGER NOT NULL, title VARCHAR(180) DEFAULT NULL, street VARCHAR(180) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, district VARCHAR(100) DEFAULT NULL, lat DOUBLE PRECISION NOT NULL, lng DOUBLE PRECISION NOT NULL, description CLOB DEFAULT NULL, categories CLOB NOT NULL, image_path VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, deleted_at DATETIME DEFAULT NULL, confirmed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_17E64ABA53C55F64 FOREIGN KEY (map_id) REFERENCES maps (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO locations (id, map_id, title, street, postal_code, district, lat, lng, description, categories, image_path, status, deleted_at, confirmed_at, created_at, updated_at) SELECT id, 1, title, street, postal_code, district, lat, lng, description, categories, image_path, status, deleted_at, confirmed_at, created_at, updated_at FROM __temp__locations');
        $this->addSql('DROP TABLE __temp__locations');
        $this->addSql('CREATE INDEX IDX_17E64ABA53C55F64 ON locations (map_id)');

        $this->addSql('CREATE TEMPORARY TABLE __temp__submissions AS SELECT id, type, payload, email, created_at, reviewed_at, review_status, location_id FROM submissions');
        $this->addSql('DROP TABLE submissions');
        $this->addSql('CREATE TABLE submissions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, map_id INTEGER NOT NULL, type VARCHAR(255) NOT NULL, payload CLOB NOT NULL, email VARCHAR(180) DEFAULT NULL, created_at DATETIME NOT NULL, reviewed_at DATETIME DEFAULT NULL, review_status VARCHAR(255) NOT NULL, location_id INTEGER DEFAULT NULL, CONSTRAINT FK_3F6169F764D218E FOREIGN KEY (location_id) REFERENCES locations (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3F6169F753C55F64 FOREIGN KEY (map_id) REFERENCES maps (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO submissions (id, map_id, type, payload, email, created_at, reviewed_at, review_status, location_id) SELECT id, 1, type, payload, email, created_at, reviewed_at, review_status, location_id FROM __temp__submissions');
        $this->addSql('DROP TABLE __temp__submissions');
        $this->addSql('CREATE INDEX IDX_3F6169F764D218E ON submissions (location_id)');
        $this->addSql('CREATE INDEX IDX_3F6169F753C55F64 ON submissions (map_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__locations AS SELECT id, title, street, postal_code, district, lat, lng, description, categories, image_path, status, deleted_at, confirmed_at, created_at, updated_at FROM locations');
        $this->addSql('DROP TABLE locations');
        $this->addSql('CREATE TABLE locations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(180) DEFAULT NULL, street VARCHAR(180) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, district VARCHAR(100) DEFAULT NULL, lat DOUBLE PRECISION NOT NULL, lng DOUBLE PRECISION NOT NULL, description CLOB DEFAULT NULL, categories CLOB NOT NULL, image_path VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, deleted_at DATETIME DEFAULT NULL, confirmed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO locations (id, title, street, postal_code, district, lat, lng, description, categories, image_path, status, deleted_at, confirmed_at, created_at, updated_at) SELECT id, title, street, postal_code, district, lat, lng, description, categories, image_path, status, deleted_at, confirmed_at, created_at, updated_at FROM __temp__locations');
        $this->addSql('DROP TABLE __temp__locations');

        $this->addSql('CREATE TEMPORARY TABLE __temp__submissions AS SELECT id, type, payload, email, created_at, reviewed_at, review_status, location_id FROM submissions');
        $this->addSql('DROP TABLE submissions');
        $this->addSql('CREATE TABLE submissions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(255) NOT NULL, payload CLOB NOT NULL, email VARCHAR(180) DEFAULT NULL, created_at DATETIME NOT NULL, reviewed_at DATETIME DEFAULT NULL, review_status VARCHAR(255) NOT NULL, location_id INTEGER DEFAULT NULL, CONSTRAINT FK_3F6169F764D218E FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO submissions (id, type, payload, email, created_at, reviewed_at, review_status, location_id) SELECT id, type, payload, email, created_at, reviewed_at, review_status, location_id FROM __temp__submissions');
        $this->addSql('DROP TABLE __temp__submissions');
        $this->addSql('CREATE INDEX IDX_3F6169F764D218E ON submissions (location_id)');

        $this->addSql('DROP TABLE maps');
    }
}
