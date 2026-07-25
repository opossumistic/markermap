<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260725160340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Optional title; categories JSON (1-n) instead of single category';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__locations AS SELECT id, title, street, postal_code, district, lat, lng, description, category, image_path, status, deleted_at, confirmed_at, created_at, updated_at FROM locations');
        $this->addSql('DROP TABLE locations');
        $this->addSql('CREATE TABLE locations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(180) DEFAULT NULL, street VARCHAR(180) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, district VARCHAR(100) DEFAULT NULL, lat DOUBLE PRECISION NOT NULL, lng DOUBLE PRECISION NOT NULL, description CLOB DEFAULT NULL, categories CLOB NOT NULL, image_path VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, deleted_at DATETIME DEFAULT NULL, confirmed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO locations (id, title, street, postal_code, district, lat, lng, description, categories, image_path, status, deleted_at, confirmed_at, created_at, updated_at) SELECT id, NULLIF(title, \'\'), street, postal_code, district, lat, lng, description, \'["\' || category || \'"]\', image_path, status, deleted_at, confirmed_at, created_at, updated_at FROM __temp__locations');
        $this->addSql('DROP TABLE __temp__locations');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__locations AS SELECT id, title, street, postal_code, district, lat, lng, description, categories, image_path, status, deleted_at, confirmed_at, created_at, updated_at FROM locations');
        $this->addSql('DROP TABLE locations');
        $this->addSql('CREATE TABLE locations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(180) NOT NULL, street VARCHAR(180) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, district VARCHAR(100) DEFAULT NULL, lat DOUBLE PRECISION NOT NULL, lng DOUBLE PRECISION NOT NULL, description CLOB DEFAULT NULL, category VARCHAR(255) NOT NULL, image_path VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, deleted_at DATETIME DEFAULT NULL, confirmed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO locations (id, title, street, postal_code, district, lat, lng, description, category, image_path, status, deleted_at, confirmed_at, created_at, updated_at) SELECT id, COALESCE(title, \'Tauschbox\'), street, postal_code, district, lat, lng, description, COALESCE(json_extract(categories, \'$[0]\'), \'other\'), image_path, status, deleted_at, confirmed_at, created_at, updated_at FROM __temp__locations');
        $this->addSql('DROP TABLE __temp__locations');
    }
}
