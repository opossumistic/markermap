<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260725151251 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create locations and submissions tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE locations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(180) NOT NULL, street VARCHAR(180) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, district VARCHAR(100) DEFAULT NULL, lat DOUBLE PRECISION NOT NULL, lng DOUBLE PRECISION NOT NULL, description CLOB DEFAULT NULL, category VARCHAR(255) NOT NULL, image_path VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, deleted_at DATETIME DEFAULT NULL, confirmed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE TABLE submissions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(255) NOT NULL, payload CLOB NOT NULL, email VARCHAR(180) DEFAULT NULL, created_at DATETIME NOT NULL, reviewed_at DATETIME DEFAULT NULL, review_status VARCHAR(255) NOT NULL, location_id INTEGER DEFAULT NULL, CONSTRAINT FK_3F6169F764D218E FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3F6169F764D218E ON submissions (location_id)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE locations');
        $this->addSql('DROP TABLE submissions');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
