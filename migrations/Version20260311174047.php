<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260311174047 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tbd_barber_profile CHANGE avg_rating avg_rating NUMERIC(3, 2) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE tbn_company ADD tagline VARCHAR(255) DEFAULT NULL, ADD email VARCHAR(100) DEFAULT NULL, ADD cover_image LONGTEXT DEFAULT NULL, ADD logo LONGTEXT DEFAULT NULL, ADD social_networks LONGTEXT DEFAULT NULL, ADD cancellation_policy LONGTEXT DEFAULT NULL, ADD privacy_policy LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tbd_barber_profile CHANGE avg_rating avg_rating NUMERIC(3, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE tbn_company DROP tagline, DROP email, DROP cover_image, DROP logo, DROP social_networks, DROP cancellation_policy, DROP privacy_policy');
    }
}
