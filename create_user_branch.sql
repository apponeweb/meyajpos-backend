-- Script para habilitar soporte multisucursal a nivel de usuario
-- Ejecutar en la base de datos antes de desplegar el código

CREATE TABLE IF NOT EXISTS `tbd_user_branch` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `user_id`    INT          NOT NULL,
    `branch_id`  BIGINT       NOT NULL,
    `is_default` TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_branch` (`user_id`, `branch_id`),
    CONSTRAINT `fk_ub_user`   FOREIGN KEY (`user_id`)   REFERENCES `user`(`id`)        ON DELETE CASCADE,
    CONSTRAINT `fk_ub_branch` FOREIGN KEY (`branch_id`) REFERENCES `tbn_branch`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
