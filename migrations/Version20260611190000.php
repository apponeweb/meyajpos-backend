<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create WhatsApp conversation state table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE tbd_whatsapp_conversation_state (
                id BIGSERIAL NOT NULL,
                wa_id VARCHAR(30) NOT NULL,
                step VARCHAR(80) NOT NULL,
                company_id BIGINT DEFAULT NULL,
                branch_id BIGINT DEFAULT NULL,
                branch_name VARCHAR(255) DEFAULT NULL,
                branch_address VARCHAR(500) DEFAULT NULL,
                service_id BIGINT DEFAULT NULL,
                service_name VARCHAR(255) DEFAULT NULL,
                service_price NUMERIC(12, 2) DEFAULT NULL,
                service_duration INTEGER DEFAULT NULL,
                date VARCHAR(20) DEFAULT NULL,
                barber_id BIGINT DEFAULT NULL,
                barber_name VARCHAR(255) DEFAULT NULL,
                time_label VARCHAR(100) DEFAULT NULL,
                scheduled_date_time VARCHAR(100) DEFAULT NULL,
                customer_name VARCHAR(255) DEFAULT NULL,
                customer_email VARCHAR(255) DEFAULT NULL,
                customer_phone VARCHAR(30) DEFAULT NULL,
                customer_country_code VARCHAR(10) DEFAULT NULL,
                customer_notes TEXT DEFAULT NULL,
                payload_json JSON DEFAULT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX uniq_whatsapp_conversation_state_wa_id ON tbd_whatsapp_conversation_state (wa_id)');
        $this->addSql('CREATE INDEX idx_whatsapp_conversation_state_step ON tbd_whatsapp_conversation_state (step)');
        $this->addSql('CREATE INDEX idx_whatsapp_conversation_state_expires_at ON tbd_whatsapp_conversation_state (expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tbd_whatsapp_conversation_state');
    }
}