<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tbd_whatsapp_message table to log bot conversations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS tbd_whatsapp_message (
                id BIGSERIAL PRIMARY KEY,
                wa_id VARCHAR(64) NOT NULL,
                direction VARCHAR(10) NOT NULL,
                body TEXT NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
            SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_wa_msg_wa_id ON tbd_whatsapp_message (wa_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_wa_msg_created ON tbd_whatsapp_message (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tbd_whatsapp_message');
    }
}
