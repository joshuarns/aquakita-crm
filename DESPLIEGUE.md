# Guía de despliegue a producción — Aquakita

Sistema de administración y seguimiento de leads (Laravel 13 + Livewire).

## 1. Requisitos del servidor

- PHP 8.3+ (probado en 8.5) con extensiones: `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `ctype`, `fileinfo`, `json`, `tokenizer`, `xml`, `curl`, `zip`, `gd`.
- MySQL 8 o MariaDB 10.6+.
- Composer 2, Node 18+ (solo para compilar assets).
- Un servidor web (Nginx/Apache) apuntando a la carpeta `public/`.

## 2. Primer despliegue

```bash
# 1. Código y dependencias
git clone <repo> aquakita && cd aquakita
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 2. Entorno
cp .env.production.example .env
# Editar .env: APP_URL, base de datos, correo SMTP, zona horaria
php artisan key:generate

# 3. Base de datos
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force   # roles y permisos
php artisan db:seed --class=StatusSeeder --force            # los 13 estatus (§7)
php artisan db:seed --class=CatalogSeeder --force           # catálogos base (opcional)
#  NO correr DemoUsersSeeder en producción (usuarios de prueba).
#  Crear el primer administrador con Tinker (ver abajo).

# 4. Optimización de producción
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Permisos de escritura
chmod -R ug+rw storage bootstrap/cache
```

### Crear el primer administrador

```bash
php artisan tinker
>>> $u = App\Models\User::create(['name'=>'Admin','email'=>'admin@aquakita.com','password'=>bcrypt('CAMBIA_ESTA'),'active'=>true]);
>>> $u->assignRole('administrador');
```

Luego, el resto de usuarios se dan de alta desde **Configuración → Usuarios** (§3.1).

## 3. Tareas en segundo plano

**Programador (§4.3 avisos de seguimiento):** una sola entrada de cron:

```cron
* * * * * cd /ruta/aquakita && php artisan schedule:run >> /dev/null 2>&1
```

Esto dispara a diario `leads:notify-followups` (seguimientos próximos y vencidos).

**Worker de colas** (correo y trabajos en segundo plano). Con `QUEUE_CONNECTION=database`, mantener un worker vivo con supervisor/systemd:

```bash
php artisan queue:work --tries=3 --max-time=3600
```

## 4. Actualizaciones posteriores

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
```

## 5. Respaldos (§10)

- **Base de datos** diaria: `mysqldump aquakita > backup.sql` vía cron, con retención.
- **Adjuntos**: respaldar `storage/app/private/attachments`.
- Considerar el paquete `spatie/laravel-backup` para automatizar BD + archivos.

## 6. Seguridad

- `APP_DEBUG=false` y `APP_ENV=production` (obligatorio).
- HTTPS con `APP_URL=https://...` y `SESSION_ENCRYPT=true`.
- El registro público está deshabilitado por diseño (sistema privado, §1).
- Contraseña fuerte de BD y usuario de BD con privilegios mínimos.
- Los adjuntos se sirven por ruta con verificación de permisos, no son públicos (§2, §5).

## 7. Pendientes de confirmar con el cliente

Antes de operar, cerrar los puntos abiertos del documento (pág. 13):
volumen esperado, proveedor SMTP, reglas exactas de detección de duplicados,
tipos/tamaños de adjuntos permitidos, moneda(s) de venta, zona horaria y
política de respaldos/retención.
