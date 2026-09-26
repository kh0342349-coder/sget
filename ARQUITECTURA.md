# SGET — Arquitectura del sistema

> Sistema Inteligente de Transporte. PHP 8 + MySQL/MariaDB + Tailwind (CDN) + JS vanilla.
> Documento de referencia para corregir errores rápido y mantener el código ordenado.

---

## 1. El error que se corrigió: *Inconsistencia de Datos / Data Default Fallback*

El síntoma era que el sistema "funcionaba" pero mostraba datos incoherentes y
fechas imposibles. La causa raíz no era una sola línea, sino **el motor de base
de datos en modo permisivo en modo permisivo** combinado con **columnas mal tipadas** y
**código que no validaba nada**.

### 1.1 Fecha cero en viajes (el caso más grave)

```
viaje.fec_via      DATETIME   <input type="date">  ->  2026-09-26 00:00:00
viaje.hor_sal_via  DATETIME   <input type="time">  ->  0000-00-00 06:00:00  ← FECHA CERO
```

MySQL rellenaba la parte que faltaba con ceros. Efectos:

| Síntoma | Causa |
|---|---|
| `TIMESTAMP(fec_via, hor_sal_via)` devolvía `NULL` | La fecha cero no es una fecha válida |
| El cierre automático de viajes nunca se activaba | La comparación con `NOW()` fallaba |
| No se podía saber si un viaje ya había salido | La comparación con `time()` fallaba |
| Al editar un viaje se perdía la hora | El formulario devolvía `06:00` y se volvía a guardar como `0000-00-00` |

**Corrección:** `fec_via` es `DATE`, `hor_sal_via` es `TIME`, ambas `NOT NULL`,
y toda escritura pasa por `core/Fecha.php`, que **lanza excepción** en vez de
dejar que MySQL invente un valor.

### 1.2 Rutas sin salida ni destino

`rutas.ori_rut` y `rutas.des_rut` eran `NOT NULL`, pero `Admin/rutas.php` hacía
`INSERT INTO rutas (nom_rut, val_rut, img_rut)` — sin esos campos. Con el motor
permisivo se guardaba cadena vacía, no un error.

```
Ruta #1  "Fusagasugá - Silvania"   ori_rut=''   des_rut=''   ← dato perdido
```

**Corrección:** el formulario exige origen y destino, `RutaService` los valida
y la migración recupera los valores a partir del nombre de la ruta.

### 1.3 Estados con tres definiciones distintas

El mismo concepto se representaba de cuatro formas según el archivo:

| Concepto | `assets/conexion.php` | Consultas SQL | Vistas |
|---|---|---|---|
| Vehículo disponible | `ESTADO_DISPONIBLE = 1` | `est_veh = 1 OR est_veh = 'Activo'` | `== 1` → "Disponible" |
| Vehículo en servicio | `ESTADO_OCUPADO = 2` | — | `else` → "Fuera de servicio" |
| Conductor | — | `est_con_usu = 1 OR = 'Disponible'` | — |

Un vehículo marcado `0` (ocupado) no era ni `1` ni `2`: el sistema no sabía
interpretarlo. Lo mismo pasaba con `usuario.estado = NULL`, que hacía que un
usuario **no apareciera ni en la lista de activos ni en la de desactivados**.

**Corrección:** `core/Config.php` es la única fuente de verdad, las columnas son
`NOT NULL` y las consultas usan siempre `Config::*`.

### 1.4 Otros problemas encontrados de paso

| Problema | Dónde | Estado |
|---|---|---|
| Inyección SQL (`$id` concatenado) | `Admin/get_precio_ruta.php` | Eliminado |
| Cambio de estado SIN autenticación | `Admin/cambiar_estado_veh.php` | Eliminado |
| Enlace a un archivo inexistente (404) | `usuarios.php` → `cambiar_estado_usu.php` | Resuelto (API) |
| Contraseñas en texto plano | `Admin/procesar_usuario.php` | `password_hash()` |
| Se borraba la foto de la carpeta equivocada | `Admin/eliminar.php` (`uploads/` vs `img/`) | Eliminado |
| Botón de ayuda lanzaba `abrirModalAyuda is not defined` | `includes/help_modal.php` nunca se incluía | Resuelto |
| Recursos liberados sin transacción | `Admin/viajes.php` | `Database::begin/commit/rollback` |

---

## 2. Estructura de carpetas

```
sget/
├── core/                     ← NÚCLEO (no depende de nada del proyecto)
│   ├── Config.php            Credenciales, rutas y TODOS los estados
│   ├── Database.php          PDO singleton, SQL estricto, helpers de consulta
│   ├── Fecha.php             Normalización de fechas/horas (anti-fallback)
│   ├── Auth.php              Sesión, roles, permisos, CSRF, guardas
│   ├── Flash.php             Mensajes de una lectura + Validator
│   └── bootstrap.php         Único punto de entrada
│
├── services/                 ← REGLAS DE NEGOCIO (una clase por módulo)
│   ├── RutaService.php
│   ├── VehiculoService.php
│   ├── UsuarioService.php
│   ├── ViajeService.php
│   └── NotificacionService.php
│
├── views/                    ← PRESENTACIÓN (partiales reutilizables)
│   ├── modals/               ruta, viaje, vehiculo, usuario, cancelar-viaje,
│   │                         ayuda, notificaciones
│   └── partials/             head.php, foot.php
│
├── assets/                   ← FRONT-END
│   ├── css/                  01-base … 06-responsive + index (landing)
│   └── js/                   sget-modal, sget-cru, sget-page
│
├── api/
│   └── index.php             API JSON única (CRUD de todos los módulos)
│
├── migraciones/              ← CONTROL DE CAMBIOS DE LA BASE DE DATOS
│   ├── migrar.php            Runner (--estado, --reset)
│   ├── 002_corregir_datos_por_defecto.php
│   └── 003_estructura_y_tipos.php
│
├── pruebas/                  smoke.php · render.php · api.php
│
├── Admin/  Conductor/  Pasajero/    ← páginas (solo pintan y delegan)
└── includes/                 header, sidebar, tema, modal inactividad
```

### Regla de dependencia (importa en el orden de corrección)

```
index.php  →  includes/  →  views/  →  services/  →  core/  →  MySQL
```

Nunca al revés: `core/` no sabe qué es una ruta ni un viaje; `services/` no sabe
cómo se ve una pantalla.

---

## 3. Los tres archivos que resuelven el 80 % de los errores

### `core/Fecha.php`
Única puerta para convertir fechas. **Nunca uses `date()`/`strtotime()` sobre
datos de la base directamente.**

```php
Fecha::fecha('2026-09-26');            // '2026-09-26'
Fecha::hora('06:30');                  // '06:30:00'
Fecha::fecha('basura');                // lanza ValueError
Fecha::esVacia('0000-00-00 00:00:00'); // true  ← detecta el dato corrupto
[$salio, $instante] = Fecha::yaSalio($fec, $hora);
```

### `core/Config.php`
Si necesitas un estado, está aquí. No definas constantes nuevas.

```php
Config::VEH_DISPONIBLE   // 1
Config::CON_OCUPADO      // 0
Config::VIA_CANCELADO    // 'Cancelado'
Config::MIN_ANOTACION_CANCELACION  // 15
```

### `services/*Service.php`
Toda regla de negocio vive aquí, no en la página. Así un cambio se hace en un
sitio y el mismo error no puede reaparecer en otro módulo.

---

## 4. Cómo se agrega o modifica un CRUD (patrón)

```php
// 1) La página valida permisos y lee datos del servicio
Auth::requerirAdmin();
Auth::requerirAcceso('rutas');
$rutas = RutaService::todas();

// 2) Reutiliza el partial del modal y el motor común
include __DIR__ . '/../views/modals/ruta.php';
$jsExtra = ['sget-page.js'];
include __DIR__ . '/../views/partials/foot.php';
```

En el HTML, el comportamiento se **declara**, no se programa:

```html
<!-- Abrir el modal en modo alta -->
<button data-sget-modal="modalRuta" data-sget-nuevo="Registrar Nueva Ruta">Nueva Ruta</button>

<!-- Abrir el modal en modo edición, con datos -->
<button data-sget-modal="modalRuta"
        data-sget-datos='{"id_rut":3,"nom_rut":"Fusagasugá - Bogotá","ori_rut":"Fusagasugá",...}'>Editar</button>

<!-- Acción puntual con confirmación -->
<button data-sget-accion="eliminar" data-sget-modulo="ruta"
        data-sget-dato='{"id":3}'
        data-sget-titulo="Eliminar ruta"
        data-sget-texto='Se eliminará la ruta <strong>Fusagasugá - Bogotá</strong>.'
        data-sget-ok="Sí, eliminar">🗑</button>
```

**No escribas JavaScript dentro de las páginas.** Todo lo que necesitas ya está
en `assets/js/sget-page.js`.

---

## 5. Sistema de modales

Un solo motor (`assets/js/sget-modal.js`) reemplaza las ~300 líneas de JS inline
repetidas por módulo. Aporta lo que faltaba en el código anterior:

- cierre con `Esc`
- foco atrapado dentro del diálogo (accesibilidad de teclado)
- scroll del `body` bloqueado mientras hay un overlay
- `role="dialog"` + `aria-modal` + región `aria-live` para lectores de pantalla
- restauración del foco al elemento que abrió el modal
- API declarativa de llenado: `data-sget-campo`, `data-sget-texto`, `data-sget-html`

### Tipos de diálogo

| Componente | Clase | Uso |
|---|---|---|
| Modal centrado | `.sget-modal-wrap > .sget-modal` | Confirmaciones, avisos, buzón |
| Drawer lateral | `.sget-drawer-wrap > .sget-drawer` | Formularios largos de CRUD |
| Confirmación | `SGETModal.confirmar(opts)` | Eliminar, finalizar |
| Confirmación con motivo | `SGETModal.confirmarMotivo(opts)` | **Cancelar viaje** |

En móvil (`≤640px`) los modales y drawers se convierten en hojas inferiores
(`92dvh`, `border-radius` solo arriba) y los botones se apilan en columna
invertida: se llega al botón de confirmar con el pulgar.

---

## 6. Flujo de cancelación de viaje

Es la regla de negocio nueva más importante:

```
Admin pulsa "Cancelar"
   └─> SGETModal.confirmarMotivo(...)
         Se muestra cuántos pasajeros hay afectados ANTES de confirmar
         └─> ¿El viaje ya salió?
              │                        │
             SÍ                        NO
              │                        │
     Motivo obligatorio        Motivo + ANOTACIÓN OBLIGATORIA (≥15 car.)
              │                        │
              └────────┬───────────────┘
                       ▼
              ViajeService::cancelar()
                 1. marca est_via = 'Cancelado'
                 2. guarda motivo, anotación, quién y cuándo
                 3. cancela las reservas activas (quedan trazables)
                 4. libera conductor y vehículo
                 5. notifica a cada pasajero reservado
                 6. registra la acción en la auditoría
```

Detalle importante: **la lista de pasajeros se calcula ANTES de cancelar las
reservas.** Si se hiciera después, la consulta no encontraría a nadie y los
pasajeros se quedarían sin aviso.

El pasajero ve el aviso en el **buzón de la cabecera** (campana con contador),
accesible desde cualquier pantalla.

---

## 7. Migraciones de base de datos

```bash
php migraciones/migrar.php --estado    # ver qué falta
php migraciones/migrar.php            # aplicar
php migraciones/migrar.php --reset     # olvidar la 002 (no la deshace)
```

Cada migración se registra en la tabla `migracion` y solo corre una vez.
Antes de aplicar se guardan copias en `_backup_pre_migracion_viaje`,
`_mig_backup_*` y `sget_logs_auditoria`.

> **Por qué el saneo de datos está en PHP y no en SQL:**
> MySQL/MariaDB no puede convertir de forma fiable una fecha cero
> (`'0000-00-00 00:00:00'`) a texto: `DATE()`, `TIME()` y `REGEXP` devuelven
> basura sobre esas filas. El DDL sí se puede aplicar con SQL normal, por eso
> hay dos migraciones: **002** sanea datos en PHP y **003** endurece la
> estructura.

### Motor SQL

La conexión fuerza el modo estricto:

```sql
STRICT_ALL_TABLES, NO_ZERO_DATE, NO_ZERO_IN_DATE,
ERROR_FOR_DIVISION_BY_ZERO, NO_ENGINE_SUBSTITUTION
```

Recomendado en `my.ini`:

```ini
[mysqld]
sql_mode="STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION"
```

---

## 8. Pruebas

```bash
# 1) Datos y reglas de negocio (no necesita servidor)
php pruebas/smoke.php

# 2) Renderizado de páginas (necesita servidor)
touch pruebas/.habilitar
php -S 127.0.0.1:8899 -t .
php pruebas/render.php
php pruebas/api.php
rm pruebas/.habilitar     # ¡bórralo siempre!
```

- `smoke.php` — 42 comprobaciones: integridad de fechas, estados, validación,
  alta real de ruta y viaje, cancelación con notificación.
- `render.php` — 35 comprobaciones: las páginas responden 200, contienen los
  campos clave y **no emiten errores PHP**.
- `api.php` — 10 comprobaciones: CSRF, errores por campo, alta real y auth.

> `pruebas/_sesion_test.php` solo responde desde `127.0.0.1` **y** si existe
> `pruebas/.habilitar` (ignorado por git). En un servidor real es un 404.

---

## 9. CSS modular

| Archivo | Responsabilidad | Pregunta que responde |
|---|---|---|
| `01-base.css` | Reset, variables, tipografía, modo oscuro | *¿De dónde sale este color?* |
| `02-layout.css` | Shell, sidebar, cabeceras, rejillas | *¿Por qué se desplaza el contenido?* |
| `03-componentes.css` | Botones, badges, formularios, toasts | *¿Cómo se ve un botón?* |
| `04-modales.css` | Overlays, modales, drawers, confirmaciones | *¿Por qué el modal no cierra?* |
| `05-tablas.css` | Tabla de datos + modo tarjeta en móvil | *¿Por qué la tabla no cabe en el móvil?* |
| `06-responsive.css` | Breakpoints, objetivos táctiles, impresión | *¿En qué tamaño se rompe?* |
| `index.css` | **Solo la landing page** | *¿Dónde busco los estilos del sitio público?* |

Todos los componentes usan el prefijo `sget-`, así que no dependen de utilidades
arbitrarias de Tailwind: una corrección se hace **una vez** y aplica a todo.

Breakpoints estándar: `640` móvil · `768` tableta · `1024` laptop · `1280`
escritorio · `1536` grande.

---

## 10. Pendientes recomendados

1. **Carpeta `SGET/` duplicada** — hay una copia completa de 31 MB de la
   aplicación dentro del webroot (además es un submódulo git que se apunta a sí
   mismo).debe eliminarse del servidor: duplica rutas, desordena el árbol y
   multiplica los puntos de entrada accesibles.
2. **Migrar páginas restantes** al patrón nuevo: `admin.php`, `asignaciones.php`,
   `reportes.php`, `logs.php`, `gestion_permisos.php` y todo `Conductor/` y
   `Pasajero/`.
3. **Conectar notificaciones reales**: `NotificacionService` es el único punto
   donde enganchar correo o SMS a los pasajeros.
4. **Reemplazar `helpers/AuthHelper.php`** por `core/Auth.php` (ya es
   equivalente y más estricto) y eliminarlo.
5. **Recuperación de contraseña** y **verificación de correo**: hoy no existen.
