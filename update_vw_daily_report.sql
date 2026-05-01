-- Script para actualizar la vista vw_daily_report con branch_id y branch_name
-- Basado en la definición real de la vista existente

CREATE OR REPLACE VIEW `vw_daily_report` AS
SELECT
  `sd`.`id`                           AS `detail_id`,
  `s`.`folio`                          AS `ticket_folio`,
  `p`.`name`                           AS `product_service_name`,
  `p`.`id`                             AS `product_service_id`,
  `st`.`name`                          AS `service_type_name`,
  `st`.`id`                            AS `service_type_id`,
  `u`.`name`                           AS `barber_name`,
  `u`.`id`                             AS `barber_id`,
  `sd`.`quantity`                      AS `quantity`,
  `sd`.`unit_price`                    AS `unit_price`,
  (SELECT COALESCE(SUM(`t`.`amount`),0) FROM `tbd_tip` `t`
     WHERE `t`.`sale_payment_id` IN
       (SELECT `sp2`.`id` FROM `tbr_sale_payment` `sp2` WHERE `sp2`.`sale_id` = `s`.`id`)) AS `tip_amount`,
  `sd`.`total`                         AS `total`,
  (SELECT COALESCE(SUM(`sp3`.`amount_received`),0) FROM `tbr_sale_payment` `sp3`
     WHERE `sp3`.`sale_id` = `s`.`id`)  AS `payment_amount`,
  COALESCE(`s`.`cash_change`,0)        AS `cash_change`,
  (SELECT `pt2`.`name` FROM `tbr_sale_payment` `sp4`
     JOIN `tbn_payment_type` `pt2` ON `sp4`.`payment_type_id` = `pt2`.`id`
     WHERE `sp4`.`sale_id` = `s`.`id` LIMIT 1) AS `payment_method`,
  (SELECT `sp5`.`payment_type_id` FROM `tbr_sale_payment` `sp5`
     WHERE `sp5`.`sale_id` = `s`.`id` LIMIT 1) AS `payment_method_id`,
  DATE_FORMAT(`s`.`sale_date`,'%d/%m/%Y %H:%i:%s') AS `formatted_sale_date`,
  `s`.`sale_date`                      AS `sale_date`,
  COALESCE(`cb`.`name`,'N/A')          AS `cash_box_name`,
  `b`.`id`                             AS `branch_id`,
  COALESCE(`b`.`name`,'')             AS `branch_name`
FROM `tbd_sale_detail` `sd`
JOIN  `tbd_sale`         `s`  ON `sd`.`sale_id`      = `s`.`id`
LEFT JOIN `tbd_cash_box` `cb` ON `s`.`cash_box_id`   = `cb`.`id`
LEFT JOIN `tbn_branch`   `b`  ON `cb`.`branch_id`    = `b`.`id`
JOIN  `tbd_master_product` `p` ON `sd`.`product_id`  = `p`.`id`
JOIN  `tbn_service_type` `st` ON `p`.`service_type_id` = `st`.`id`
LEFT JOIN `user`         `u`  ON `sd`.`service_provider_id` = `u`.`id`;
