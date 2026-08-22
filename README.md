# CUMPLE

**Control Unificado de Metas, Pendientes, Logros y Evidencias.**

Aplicación institucional para administrar actas, compromisos, responsables, fechas límite y evidencias de cumplimiento de las áreas asistenciales.

## Base técnica

- Laravel 12
- PHP 8.3 o superior
- PostgreSQL 16 o superior
- Livewire 4, Blade, Alpine.js y Tailwind CSS

## Instalación local

```bash
composer install
copy .env.example .env
php artisan key:generate
npm install
npm run build
php artisan migrate --seed
php artisan serve
```

Configure primero las variables `DB_*` de PostgreSQL en `.env`. El registro público solo está disponible en el entorno `local`; en producción el administrador inicial se crea mediante `ADMIN_NAME`, `ADMIN_EMAIL` y `ADMIN_PASSWORD` al ejecutar el seeder.

## Despliegue

El document root de Nginx debe apuntar a `/var/www/cumple/public`. Los directorios `storage` y `bootstrap/cache` deben pertenecer al usuario de PHP-FPM. Nunca se versionan `.env`, evidencias ni secretos.

## Módulos preparados

- Áreas asistenciales
- Usuarios y roles
- Actas y asistentes
- Tareas y responsables
- Comentarios
- Evidencias privadas
