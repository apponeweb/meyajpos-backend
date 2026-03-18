<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260311184836 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tbd_barber_profile CHANGE avg_rating avg_rating NUMERIC(3, 2) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE tbn_branch ADD image VARCHAR(255) DEFAULT NULL, ADD rating DOUBLE PRECISION DEFAULT NULL, ADD review_count INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tbd_barber_profile CHANGE avg_rating avg_rating NUMERIC(3, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE tbn_branch DROP image, DROP rating, DROP review_count');
    }
}
