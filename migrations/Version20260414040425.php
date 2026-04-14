<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260414040425 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE lic_license (id BIGINT AUTO_INCREMENT NOT NULL, max_branches INT NOT NULL, max_barbers INT NOT NULL, start_date DATE NOT NULL, duration_days INT NOT NULL, expires_at DATE NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, notes LONGTEXT DEFAULT NULL, created_by BIGINT DEFAULT NULL, updated_by BIGINT DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, user_id INT NOT NULL, company_id BIGINT NOT NULL, UNIQUE INDEX UNIQ_3F57EDC5A76ED395 (user_id), INDEX IDX_3F57EDC5979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE lic_license_system (id BIGINT AUTO_INCREMENT NOT NULL, license_id BIGINT NOT NULL, system_id BIGINT NOT NULL, INDEX IDX_26FAB554460F904B (license_id), INDEX IDX_26FAB554D0952FA5 (system_id), UNIQUE INDEX UNIQ_LICENSE_SYSTEM (license_id, system_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE lic_system (id BIGINT AUTO_INCREMENT NOT NULL, code VARCHAR(20) NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_2BE7355977153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE tbd_review (created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, created_by BIGINT DEFAULT NULL, updated_by BIGINT DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, delete_by BIGINT DEFAULT NULL, id BIGINT AUTO_INCREMENT NOT NULL, customer_name VARCHAR(100) NOT NULL, rating SMALLINT NOT NULL, comment LONGTEXT NOT NULL, branch_id BIGINT DEFAULT NULL, barber_id INT DEFAULT NULL, INDEX IDX_2700F3CCDCD6CC49 (branch_id), INDEX IDX_2700F3CCBFF2FEF2 (barber_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('ALTER TABLE lic_license ADD CONSTRAINT FK_3F57EDC5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE lic_license ADD CONSTRAINT FK_3F57EDC5979B1AD6 FOREIGN KEY (company_id) REFERENCES tbn_company (id)');
        $this->addSql('ALTER TABLE lic_license_system ADD CONSTRAINT FK_26FAB554460F904B FOREIGN KEY (license_id) REFERENCES lic_license (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lic_license_system ADD CONSTRAINT FK_26FAB554D0952FA5 FOREIGN KEY (system_id) REFERENCES lic_system (id)');
        $this->addSql('ALTER TABLE tbd_review ADD CONSTRAINT FK_2700F3CCDCD6CC49 FOREIGN KEY (branch_id) REFERENCES tbn_branch (id)');
        $this->addSql('ALTER TABLE tbd_review ADD CONSTRAINT FK_2700F3CCBFF2FEF2 FOREIGN KEY (barber_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE tbd_barber_profile CHANGE avg_rating avg_rating NUMERIC(4, 2) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE tbd_sale_detail ADD created_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD deleted_at DATETIME DEFAULT NULL, ADD created_by BIGINT DEFAULT NULL, ADD updated_by BIGINT DEFAULT NULL, ADD is_active TINYINT(1) DEFAULT 1 NOT NULL, ADD delete_by BIGINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lic_license DROP FOREIGN KEY FK_3F57EDC5A76ED395');
        $this->addSql('ALTER TABLE lic_license DROP FOREIGN KEY FK_3F57EDC5979B1AD6');
        $this->addSql('ALTER TABLE lic_license_system DROP FOREIGN KEY FK_26FAB554460F904B');
        $this->addSql('ALTER TABLE lic_license_system DROP FOREIGN KEY FK_26FAB554D0952FA5');
        $this->addSql('ALTER TABLE tbd_review DROP FOREIGN KEY FK_2700F3CCDCD6CC49');
        $this->addSql('ALTER TABLE tbd_review DROP FOREIGN KEY FK_2700F3CCBFF2FEF2');
        $this->addSql('DROP TABLE lic_license');
        $this->addSql('DROP TABLE lic_license_system');
        $this->addSql('DROP TABLE lic_system');
        $this->addSql('DROP TABLE tbd_review');
        $this->addSql('ALTER TABLE tbd_barber_profile CHANGE avg_rating avg_rating NUMERIC(3, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE tbd_sale_detail DROP created_at, DROP updated_at, DROP deleted_at, DROP created_by, DROP updated_by, DROP is_active, DROP delete_by');
    }
}
