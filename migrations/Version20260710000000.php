<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260710000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add visit_frequency_days to customer for next-visit calculation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tbd_customer ADD visit_frequency_days INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tbd_customer DROP COLUMN visit_frequency_days');
    }
}
