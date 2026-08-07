<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260807202551 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Users, auth_tokens, Map.owner for self-service + magic link';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');

        $this->addSql('CREATE TABLE auth_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, selector VARCHAR(32) NOT NULL, verifier_hash VARCHAR(64) NOT NULL, purpose VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, consumed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, user_id INTEGER NOT NULL, map_id INTEGER DEFAULT NULL, CONSTRAINT FK_8AF9B66CA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_8AF9B66C53C55F64 FOREIGN KEY (map_id) REFERENCES maps (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_8AF9B66C53C55F64 ON auth_tokens (map_id)');
        $this->addSql('CREATE INDEX idx_auth_tokens_user ON auth_tokens (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_auth_tokens_selector ON auth_tokens (selector)');

        $this->addSql('CREATE TEMPORARY TABLE __temp__maps AS SELECT id, slug, name, description, center_lat, center_lng, default_zoom, min_lat, max_lat, min_lng, max_lng, notify_email, categories_config, status, created_at, updated_at FROM maps');
        $this->addSql('DROP TABLE maps');
        $this->addSql('CREATE TABLE maps (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, slug VARCHAR(80) NOT NULL, name VARCHAR(120) NOT NULL, description CLOB DEFAULT NULL, center_lat DOUBLE PRECISION NOT NULL, center_lng DOUBLE PRECISION NOT NULL, default_zoom DOUBLE PRECISION NOT NULL, min_lat DOUBLE PRECISION DEFAULT NULL, max_lat DOUBLE PRECISION DEFAULT NULL, min_lng DOUBLE PRECISION DEFAULT NULL, max_lng DOUBLE PRECISION DEFAULT NULL, notify_email VARCHAR(180) DEFAULT NULL, categories_config CLOB DEFAULT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, owner_id INTEGER DEFAULT NULL, CONSTRAINT FK_472E08A57E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO maps (id, slug, name, description, center_lat, center_lng, default_zoom, min_lat, max_lat, min_lng, max_lng, notify_email, categories_config, status, created_at, updated_at) SELECT id, slug, name, description, center_lat, center_lng, default_zoom, min_lat, max_lat, min_lng, max_lng, notify_email, categories_config, status, created_at, updated_at FROM __temp__maps');
        $this->addSql('DROP TABLE __temp__maps');
        $this->addSql('CREATE UNIQUE INDEX uniq_maps_slug ON maps (slug)');
        $this->addSql('CREATE INDEX IDX_472E08A57E3C61F9 ON maps (owner_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE auth_tokens');

        $this->addSql('CREATE TEMPORARY TABLE __temp__maps AS SELECT id, slug, name, description, center_lat, center_lng, default_zoom, min_lat, max_lat, min_lng, max_lng, notify_email, categories_config, status, created_at, updated_at FROM maps');
        $this->addSql('DROP TABLE maps');
        $this->addSql('CREATE TABLE maps (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, slug VARCHAR(80) NOT NULL, name VARCHAR(120) NOT NULL, description CLOB DEFAULT NULL, center_lat DOUBLE PRECISION NOT NULL, center_lng DOUBLE PRECISION NOT NULL, default_zoom DOUBLE PRECISION NOT NULL, min_lat DOUBLE PRECISION DEFAULT NULL, max_lat DOUBLE PRECISION DEFAULT NULL, min_lng DOUBLE PRECISION DEFAULT NULL, max_lng DOUBLE PRECISION DEFAULT NULL, notify_email VARCHAR(180) DEFAULT NULL, categories_config CLOB DEFAULT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO maps (id, slug, name, description, center_lat, center_lng, default_zoom, min_lat, max_lat, min_lng, max_lng, notify_email, categories_config, status, created_at, updated_at) SELECT id, slug, name, description, center_lat, center_lng, default_zoom, min_lat, max_lat, min_lng, max_lng, notify_email, categories_config, status, created_at, updated_at FROM __temp__maps');
        $this->addSql('DROP TABLE __temp__maps');
        $this->addSql('CREATE UNIQUE INDEX uniq_maps_slug ON maps (slug)');

        $this->addSql('DROP TABLE users');
    }
}
