<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260318141222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Convert existing string statuses to integers before changing the column type
        $this->addSql("UPDATE tbd_appointment SET status = '2' WHERE status = 'CONFIRMED'");
        $this->addSql("UPDATE tbd_appointment SET status = '1' WHERE status = 'PENDING'");
        
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tbd_appointment CHANGE status status SMALLINT NOT NULL');
        $this->addSql('ALTER TABLE tbd_barber_profile CHANGE avg_rating avg_rating NUMERIC(3, 2) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tbd_appointment CHANGE status status VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE tbd_barber_profile CHANGE avg_rating avg_rating NUMERIC(3, 2) DEFAULT \'0.00\' NOT NULL');
    }
}
