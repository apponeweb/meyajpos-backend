# MeyajPOS Backend

## Requisitos

- PHP compatible con Symfony del proyecto
- Composer
- MySQL
- Configuración válida en `.env` o `.env.local`

## Preparar ambiente local

### 1. Instalar dependencias

```powershell
composer install
```

### 2. Revisar configuración de base de datos

Verifica que `DATABASE_URL` apunte a la base correcta en tu `.env` o `.env.local`.

### 3. Revisar cambios pendientes de esquema

```powershell
php bin/console doctrine:schema:update --dump-sql
```

### 4. Aplicar cambios de base de datos

```powershell
php bin/console doctrine:schema:update --force
```

### 5. Limpiar caché

```powershell
php bin/console cache:clear
```

### 6. Calentar caché

```powershell
php bin/console cache:warmup
```

## Actualizar proyecto desde git en local

```powershell
git pull origin main
composer install
php bin/console doctrine:schema:update --dump-sql
php bin/console doctrine:schema:update --force
php bin/console cache:clear
php bin/console cache:warmup
```

## Despliegue / actualización en servidor

### 1. Actualizar código

```bash
git pull origin main
```

### 2. Instalar dependencias

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Revisar cambios pendientes de esquema

```bash
php bin/console doctrine:schema:update --dump-sql --env=prod
```

### 4. Aplicar cambios de base de datos

```bash
php bin/console doctrine:schema:update --force --env=prod
```

### 5. Limpiar caché de producción

```bash
php bin/console cache:clear --env=prod
```

### 6. Calentar caché de producción

```bash
php bin/console cache:warmup --env=prod
```

### 7. Ajustar permisos

```bash
sudo chown -R nginx:nginx /var/www/backend/var/
```

### 8. Reiniciar PHP-FPM

```bash
sudo systemctl restart php-fpm
```

## Validaciones recomendadas

### Probar conexión a base de datos

```powershell
php bin/console doctrine:query:sql "SELECT 1"
```

### Revisar rutas disponibles

```powershell
php bin/console debug:router
```

### Revisar servicios y errores de contenedor

```powershell
php bin/console debug:container
```

# Notas para AJUSTES NO ESTANDAR
update_vw_daily_report.sql
create_user_branch.sql
php bin/console doctrine:schema:update --force
assign_admin_branches.sql

## Notas

- No sobrescribas el `.env` del servidor con el de desarrollo.
- Si el ambiente local usa otra base de datos, deja esos valores en `.env.local`.
- Antes de ejecutar `doctrine:schema:update --force`, revisa el resultado de `--dump-sql`.