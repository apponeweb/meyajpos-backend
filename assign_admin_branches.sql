-- =============================================================================
-- Asignar todas las sucursales a todos los usuarios con ROLE_ADMIN
-- =============================================================================
-- Vista previa: ejecuta este SELECT primero para revisar qué se insertará
-- -----------------------------------------------------------------------------
-- SELECT
--     u.id AS user_id,
--     u.name AS user_name,
--     b.id AS branch_id,
--     b.name AS branch_name
-- FROM user u
-- CROSS JOIN tbn_branch b
-- WHERE JSON_CONTAINS(u.roles, '"ROLE_ADMIN"')
--   AND u.enabled = 1
--   AND b.deleted_at IS NULL
-- ORDER BY u.id, b.id;
-- =============================================================================

INSERT INTO tbd_user_branch (user_id, branch_id, is_default, created_at)
SELECT
    u.id AS user_id,
    b.id AS branch_id,
    CASE
        WHEN b.id = (
            SELECT MIN(b2.id)
            FROM tbn_branch b2
            WHERE b2.deleted_at IS NULL
        ) THEN 1
        ELSE 0
    END AS is_default,
    NOW() AS created_at
FROM user u
CROSS JOIN tbn_branch b
WHERE JSON_CONTAINS(u.roles, '"ROLE_ADMIN"')
  AND u.enabled = 1
  AND b.deleted_at IS NULL
ON DUPLICATE KEY UPDATE
    is_default = VALUES(is_default);
