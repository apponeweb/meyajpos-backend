<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414043500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add photo_url to user table and fix avg_rating precision in tbd_barber_profile';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tbd_barber_profile CHANGE avg_rating avg_rating NUMERIC(4, 2) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE user ADD photo_url VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tbd_barber_profile CHANGE avg_rating avg_rating NUMERIC(4, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE user DROP photo_url');
    }
}
