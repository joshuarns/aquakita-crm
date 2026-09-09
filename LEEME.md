# Aquakita — Sistema de Administración y Seguimiento de Leads

Aplicación web privada para capturar, clasificar, asignar y dar seguimiento a leads,
con trazabilidad completa (usuario, fecha y hora en cada acción).

Basado en la *Especificación funcional del sistema de gestión de leads* (Etapa 1).

## Stack

- **Laravel 13** (PHP 8.5)
- **Livewire 4** + **Breeze** (autenticación) + Tailwind
- **SQLite** en desarrollo (cambiar a MySQL/MariaDB en producción vía `.env`)
- `spatie/laravel-permission` — roles y permisos (§2)
- `owen-it/laravel-auditing` — auditoría de cambios (§10)

## Despliegue a producción

Ver **[DESPLIEGUE.md](DESPLIEGUE.md)** (MySQL, SMTP, cron, colas, respaldos) y la
plantilla **[.env.production.example](.env.production.example)**.

## Cómo correr (desarrollo)

```bash
php artisan serve --port=8000
```

Luego abre http://127.0.0.1:8000/login

### Usuarios demo (contraseña: `password`)

| Rol | Correo |
|-----|--------|
| Administrador | admin@aquakita.test |
| Capturista | capturista@aquakita.test |
| Vendedor | vendedor@aquakita.test |
| Supervisor | supervisor@aquakita.test |

Recrear la base con datos de ejemplo:

```bash
php artisan migrate:fresh --seed
```

## Qué ya está construido (backbone Etapa 1)

- **Esquema completo de datos** (migraciones): leads, actividades, historial de
  estatus, línea de tiempo, notificaciones, adjuntos y 9 catálogos de configuración.
- **Modelos Eloquent** con relaciones y auditoría en `Lead` y `Activity`.
- **4 roles con permisos** (§2): administrador, capturista, vendedor, supervisor.
- **Los 13 estatus** del flujo comercial (§7) sembrados.
- **Catálogos base** de ejemplo (países/ciudades, idiomas, fuentes, campañas,
  tipos de proyecto, motivos de descarte, plantillas de correo).
- **Autenticación** (login, recuperación de contraseña). Registro público
  deshabilitado por ser sistema privado; los usuarios los crea el administrador.
- Alias de middleware `role` / `permission` para proteger rutas.

## Mapa especificación → código

| Sección del documento | Dónde vive |
|----|----|
| §2 Roles y permisos | `database/seeders/RolePermissionSeeder.php` |
| §3.2 Catálogos | `..._create_catalog_tables.php`, modelos de catálogo |
| §3.3 Captura de leads | `..._create_leads_table.php`, `app/Models/Lead.php` |
| §5 Ficha e historial | `TimelineEvent`, `LeadStatusHistory` |
| §6 Actividades y agenda | `..._create_activities_table.php`, `app/Models/Activity.php` |
| §4.3 Notificaciones | `..._create_lead_notifications_table.php` |
| §7 Estatus (13) | `database/seeders/StatusSeeder.php` |
| §10 Auditoría | trait `Auditable` + tabla `audits` |

## Siguientes pasos sugeridos (Etapa 1, pendientes)

1. **Captura de leads** (Livewire): formulario + detección de duplicados (§3.3).
2. **Bandeja general** con filtros, búsqueda y asignación individual/múltiple (§4.1).
3. **Panel del vendedor** con separación estricta de datos (§4.2) + Policies.
4. **Ficha del prospecto** con línea de tiempo y registro de actividades (§5, §6).
5. **Notificaciones** internas + correo, con registro de apertura (§4.3).
6. **Recordatorios/agenda** y seguimientos vencidos (§6, §8).
7. **Panel administrativo** con los KPIs del §8.
8. **Exportación CSV/Excel** para Mailchimp con reglas de exclusión (§9).

## Pendiente de definir con el cliente (antes de cerrar cotización)

Volumen esperado, proveedor de correo saliente, reglas exactas de duplicados,
tipos/tamaños de adjuntos, moneda(s) de venta, zona horaria (global o por usuario),
seguridad/respaldos/garantía. (Ver "Nota de alcance", pág. 13 del documento.)
