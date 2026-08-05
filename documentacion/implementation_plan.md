# Plan: Migración a `spatie/laravel-permission`

## Situación actual
Tu sistema tiene un campo `role` varchar en `users` y un `AdminMiddleware` que solo verifica si el rol está en `['admin', 'employee']`. Es un sistema binario sin granularidad.

---

## Roles Propuestos y Acceso por Módulo

| Módulo / Ruta | `admin` | `supervisor` | `vendedor` | `almacen` | `logistica` | `cliente` |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| Dashboard `/admin/dashboard` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Productos `/admin/productos` | ✅ | ✅ | 👁 ver | ❌ | ❌ | ❌ |
| Guías de Inventario `/admin/guias` | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ |
| Kardex `/admin/kardex` | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| Ventas/POS `/admin/ventas` | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Promociones `/admin/promociones` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Mantenimiento `/admin/mantenimiento` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Catálogo E-Commerce `/catalogo` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Checkout `/checkout` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

👁 = solo lectura (sin crear/editar)

---

## Permisos Granulares a Crear

```
panel.acceder          → Puede ingresar al /admin

dashboard.ver          → Ver Dashboard financiero
productos.ver          → Listar productos
productos.crear        → Crear nuevos productos
productos.editar       → Editar y gestionar productos
guias.ver              → Ver guías de inventario
guias.crear            → Crear guías (entrada, salida, transferencia)
kardex.ver             → Ver movimientos del Kardex
ventas.ver             → Ver historial de ventas
ventas.crear           → Registrar nueva venta (POS)
promociones.ver        → Ver campañas y cupones
promociones.crear      → Crear campañas y cupones
mantenimiento.ver      → Ver configuración base
mantenimiento.editar   → Editar proveedores, almacenes, series
```

---

## Propuesta de Cambios

### 1. Instalar `spatie/laravel-permission`
```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

### 2. Actualizar `User.php`
- Agregar el trait `HasRoles` de Spatie
- Eliminar el campo `role` del PHPDoc (mantenemos la columna como fallback temporal)

### 3. Actualizar `bootstrap/app.php`
- Reemplazar alias `'admin'` → `'role'` de Spatie
- Registrar alias `'permission'` de Spatie

### 4. Actualizar `routes/web.php`
- Reemplazar middleware `'admin'` por `'role:admin|supervisor|vendedor|almacen|logistica'`
- Añadir sub-grupos con middleware `'permission:*'` para rutas sensibles

### 5. Crear `RolesAndPermissionsSeeder`
- Definir todos los permisos
- Asignar permisos a cada rol
- Asignar rol `admin` al usuario existente (ID 1)
- Actualizar `DatabaseSeeder` para llamar al nuevo seeder

### 6. Eliminar `AdminMiddleware.php`
- Ya no se necesita, Spatie provee `role` y `permission` nativamente

### 7. Actualizar `sidebar.blade.php`
- Usar directivas `@can('permiso')` / `@role('admin')` para mostrar/ocultar ítems según el rol del usuario logueado

---

## Impacto en tu código actual

> [!IMPORTANT]
> La columna `role` en `users` **no se elimina**. Spatie crea sus propias tablas (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`). Podemos dejar la columna para referencia o eliminarla en una migración posterior.

> [!WARNING]
> El `AdminMiddleware` actual se elimina y reemplaza por los middlewares nativos de Spatie: `role` y `permission`.

---

## ¿Apruebas este plan?

Si apruebas, ejecutaré los pasos en este orden:
1. `composer require` + migraciones
2. Seeder de roles y permisos
3. User model + bootstrap/app.php
4. routes/web.php 
5. sidebar.blade.php con directivas `@can`
