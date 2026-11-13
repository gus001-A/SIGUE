# SIGUE · Sistema Integral de Gestión Universal Educativa

Plataforma web SaaS multi-institución para gestión escolar. Parte de la base técnica de SAINS_2026
(Laravel 12 + Inertia + Vue 3 + Ant Design Vue). La aplicación móvil queda fuera de esta fase.

## Puesta en marcha (WAMP local)

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
# Crear la BD vacía `sigue_2026` (utf8mb4) en MySQL
php artisan migrate:fresh --seed
npm run build
php -S 127.0.0.1:8050 dev-router.php
```

## Cuentas demo

Las crea `database/seeders/DemoInstitutionSeeder.php` (la contraseña está en ese archivo):
superadmin@sigue.mx, director@demo.sigue.mx, admin@demo.sigue.mx, docente@demo.sigue.mx,
tutor@demo.sigue.mx (con dos hijos) y alumno@demo.sigue.mx.

## Módulos implementados (web)

| Módulo | Dónde |
|---|---|
| Administración general SIGUE (instituciones, licencias, planes, módulos, soporte) | `Superadmin/InstitutionController` |
| Dashboard institucional con filtros por plantel, ciclo, grado y grupo | `DashboardController` |
| Alumnos: expediente personal, académico, salud (restringido), tutores, documentos, finanzas, incidencias, constancia PDF | `Admin/StudentController` |
| Docentes, padres/tutores (validación de identidad, vinculación), usuarios con varios roles | `Admin/*` |
| Grupos, asignaturas por grupo, docentes, horarios sin empalmes, lista PDF | `Admin/GroupController` |
| Matriculación: inscripción, reinscripción (individual y masiva), autorización, bajas, cambio de grupo, egreso, titulación | `Admin/EnrollmentController` |
| Gestión documental: carga, validación, rechazo, corrección, expedientes incompletos | `Admin/DocumentController` |
| Finanzas: adeudos/cooperaciones (individual, por grupo, masivo), pagos con folio y recibo PDF, becas con descuento automático | `Admin/FinanceController` |
| Docente: mi día, mis clases, pase de lista (sin duplicados, con bitácora), classroom, evaluación ponderada (suma 100 %), publicación, incidencias, tutorías | `Docente/*` |
| Portal de alumno y de padres (selector de hijo): calificaciones, tareas y entregas, horario, asistencias, incidencias, documentos, pagos | `Portal/PortalController` |
| Notificaciones internas, mensajería y solicitudes de reunión | `NotificationController`, `MessageController` |
| Reportes en PDF, Excel y CSV (10 reportes) | `ReportController`, `Support/Reports.php` |
| Configuración: institución, logo, notificaciones, catálogos (planteles, ciclos, periodos, grados, carreras, asignaturas, tipos de documento), roles y permisos, bitácora | `Admin/SettingsController`, `CatalogController`, `RoleController`, `AuditController` |

## Arquitectura

- **Multi-tenant por columna `institution_id`**: `App\Support\Tenant` + trait `BelongsToInstitution` (scope global y relleno automático). El middleware `SetTenant` corre antes del route-model binding, así un ID de otra institución da 404.
- **Permisos**: tablas `roles`, `permissions`, `permission_role`, `role_user`; catálogo en `App\Support\Permissions`. Middleware `perm:a|b` y `Gate::before`.
- **Auditoría**: trait `Auditable` guarda valores anteriores/nuevos en `audit_logs`.
- **Archivos privados** en `storage/app/instituciones/{id}/…`, servidos por `FileController` con verificación de acceso.

## Pendientes / siguientes fases

- Aplicación móvil (Flutter) y API REST con Sanctum.
- Canales externos de notificación (correo, push, SMS) — hoy solo buzón interno.
- MFA para perfiles privilegiados, anuario exportable, calendario de eventos, modo offline.
- La especificación recomienda PostgreSQL y React; se mantuvo MySQL + Vue por continuidad con SAINS (las migraciones son portables).
