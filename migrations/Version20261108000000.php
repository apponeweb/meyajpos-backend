<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add visit_frequency_days to appointment to keep frequency captured at booking time';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tbd_appointment ADD visit_frequency_days INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tbd_appointment DROP COLUMN visit_frequency_days');
    }
}
