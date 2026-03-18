<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260318135041 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE tbd_appointment (created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, created_by BIGINT DEFAULT NULL, updated_by BIGINT DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, delete_by BIGINT DEFAULT NULL, id BIGINT AUTO_INCREMENT NOT NULL, total_amount NUMERIC(18, 2) NOT NULL, currency VARCHAR(10) NOT NULL, status VARCHAR(20) NOT NULL, customer_id BIGINT NOT NULL, branch_id BIGINT NOT NULL, INDEX IDX_235ACBB19395C3F3 (customer_id), INDEX IDX_235ACBB1DCD6CC49 (branch_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE tbd_appointment_service (created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, created_by BIGINT DEFAULT NULL, updated_by BIGINT DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, delete_by BIGINT DEFAULT NULL, id BIGINT AUTO_INCREMENT NOT NULL, scheduled_date_time DATETIME NOT NULL, duration INT NOT NULL, price NUMERIC(12, 2) NOT NULL, cart_item_id VARCHAR(50) DEFAULT NULL, appointment_id BIGINT NOT NULL, service_id BIGINT NOT NULL, barber_id BIGINT NOT NULL, INDEX IDX_6AE718A2E5B533F9 (appointment_id), INDEX IDX_6AE718A2ED5CA9E6 (service_id), INDEX IDX_6AE718A2BFF2FEF2 (barber_id), UNIQUE INDEX UNIQ_BARBER_SCHEDULE (barber_id, scheduled_date_time), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE tbd_customer (created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, created_by BIGINT DEFAULT NULL, updated_by BIGINT DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, delete_by BIGINT DEFAULT NULL, id BIGINT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(180) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, country_code VARCHAR(5) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('ALTER TABLE tbd_appointment ADD CONSTRAINT FK_235ACBB19395C3F3 FOREIGN KEY (customer_id) REFERENCES tbd_customer (id)');
        $this->addSql('ALTER TABLE tbd_appointment ADD CONSTRAINT FK_235ACBB1DCD6CC49 FOREIGN KEY (branch_id) REFERENCES tbn_branch (id)');
        $this->addSql('ALTER TABLE tbd_appointment_service ADD CONSTRAINT FK_6AE718A2E5B533F9 FOREIGN KEY (appointment_id) REFERENCES tbd_appointment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tbd_appointment_service ADD CONSTRAINT FK_6AE718A2ED5CA9E6 FOREIGN KEY (service_id) REFERENCES tbd_master_product (id)');
        $this->addSql('ALTER TABLE tbd_appointment_service ADD CONSTRAINT FK_6AE718A2BFF2FEF2 FOREIGN KEY (barber_id) REFERENCES tbd_barber_profile (id)');
        $this->addSql('ALTER TABLE tbd_barber_profile CHANGE avg_rating avg_rating NUMERIC(3, 2) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tbd_appointment DROP FOREIGN KEY FK_235ACBB19395C3F3');
        $this->addSql('ALTER TABLE tbd_appointment DROP FOREIGN KEY FK_235ACBB1DCD6CC49');
        $this->addSql('ALTER TABLE tbd_appointment_service DROP FOREIGN KEY FK_6AE718A2E5B533F9');
        $this->addSql('ALTER TABLE tbd_appointment_service DROP FOREIGN KEY FK_6AE718A2ED5CA9E6');
        $this->addSql('ALTER TABLE tbd_appointment_service DROP FOREIGN KEY FK_6AE718A2BFF2FEF2');
        $this->addSql('DROP TABLE tbd_appointment');
        $this->addSql('DROP TABLE tbd_appointment_service');
        $this->addSql('DROP TABLE tbd_customer');
        $this->addSql('ALTER TABLE tbd_barber_profile CHANGE avg_rating avg_rating NUMERIC(3, 2) DEFAULT \'0.00\' NOT NULL');
    }
}
