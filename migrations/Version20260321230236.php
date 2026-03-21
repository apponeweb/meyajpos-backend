<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260321230236 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE tbr_user_branch (created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, created_by BIGINT DEFAULT NULL, updated_by BIGINT DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, delete_by BIGINT DEFAULT NULL, id BIGINT AUTO_INCREMENT NOT NULL, is_default TINYINT(1) DEFAULT 0 NOT NULL, user_id INT NOT NULL, branch_id BIGINT NOT NULL, INDEX IDX_17544AD0A76ED395 (user_id), INDEX IDX_17544AD0DCD6CC49 (branch_id), UNIQUE INDEX uniq_user_branch (user_id, branch_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('ALTER TABLE tbr_user_branch ADD CONSTRAINT FK_17544AD0A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tbr_user_branch ADD CONSTRAINT FK_17544AD0DCD6CC49 FOREIGN KEY (branch_id) REFERENCES tbn_branch (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tbd_barber_profile CHANGE avg_rating avg_rating NUMERIC(3, 2) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tbr_user_branch DROP FOREIGN KEY FK_17544AD0A76ED395');
        $this->addSql('ALTER TABLE tbr_user_branch DROP FOREIGN KEY FK_17544AD0DCD6CC49');
        $this->addSql('DROP TABLE tbr_user_branch');
        $this->addSql('ALTER TABLE tbd_barber_profile CHANGE avg_rating avg_rating NUMERIC(3, 2) DEFAULT \'0.00\' NOT NULL');
    }
}
