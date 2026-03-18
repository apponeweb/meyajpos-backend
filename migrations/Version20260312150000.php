<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Fixing vw_daily_report definer issue.
 */
final class Version20260312150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recreates vw_daily_report to fix definer issues.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE OR REPLACE VIEW `vw_daily_report` AS
            SELECT
                `tsd`.`id` AS `detail_id`,
                `ts`.`folio` AS `ticket_folio`,
                `tmp`.`name` AS `product_service_name`,
                `tmp`.`id` AS `product_service_id`,
                `tst`.`name` AS `service_type_name`,
                `tst`.`id` AS `service_type_id`,
                CONCAT(`u`.`name`, ' ', `u`.`last_name`) AS `barber_name`,
                `u`.`id` AS `barber_id`,
                `tsd`.`quantity` AS `quantity`,
                `tsd`.`unit_price` AS `unit_price`,
                (`tsd`.`total` - `tsd`.`unit_price`) AS `tip_amount`,
                `tsd`.`total` AS `total`,
                `tsp`.`amount_received` AS `payment_amount`,
                `ts`.`cash_change` AS `cash_change`,
                `tpt`.`name` AS `payment_method`,
                `tpt`.`id` AS `payment_method_id`,
                DATE_FORMAT(`ts`.`sale_date`, '%d/%m/%Y %H:%i:%s') AS `formatted_sale_date`,
                `ts`.`sale_date` AS `sale_date`
            FROM
                `tbd_sale` `ts`
            JOIN `tbd_sale_detail` `tsd` ON (`ts`.`id` = `tsd`.`sale_id`)
            JOIN `tbd_master_product` `tmp` ON (`tmp`.`id` = `tsd`.`product_id`)
            JOIN `tbn_service_type` `tst` ON (`tst`.`id` = `tmp`.`service_type_id`)
            JOIN `user` `u` ON (`u`.`id` = `tsd`.`service_provider_id`)
            JOIN `tbr_sale_payment` `tsp` ON (`tsp`.`sale_id` = `ts`.`id`)
            JOIN `tbn_payment_type` `tpt` ON (`tpt`.`id` = `tsp`.`payment_type_id`)
            ORDER BY
                `tsd`.`id` DESC
        ");
    }

    public function down(Schema $schema): void
    {
    }
}
