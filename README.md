# SGET — Sistema Inteligente de Transporte

Aplicación web en PHP + MySQL para la gestión de rutas, viajes, flota, usuarios y
reservas de transporte terrestre.

📖 **[Ver `ARQUITECTURA.md`](ARQUITECTURA.md)** — es el documento que explica la
estructura por capas, el error de datos que se corrigió y cómo trabajar sobre
el proyecto sin volver a introducirlo.

📖 **[Ver `docs/COMO-HACER-COMMITS.md`](docs/COMO-HACER-COMMITS.md)** — si el
repositorio vuelve a romperse, ahí está la causa explicada, con los comandos
exactos para arreglarla y las reglas para que no se repita.

---

## Puesta en marcha

1. **Base de datos** — `sget.sql` es un volcado completo (estructura + datos) que
   ya se crea y selecciona la base, así que basta con importarlo:
   ```bash
   mysql -uroot < sget.sql
   ```

2. **Migraciones** (obligatorio: la 007 copia el banner por defecto, que un
   volcado SQL no puede llevar dentro):
   ```bash
   php migraciones/migrar.php --estado
   php migraciones/migrar.php
   ```

3. **Configuración** — credenciales en `core/Config.php` (hojas: base de datos,
   zona horaria, estados). En `config/config.php` lo de la API de Google.

4. **Anuncios de la landing** — el banner por defecto se copia solo al aplicar
   las migraciones (`img/anuncios/portada.svg` → `uploads/anuncios/`). Se
   gestiona desde `Admin/anuncios.php`.

4. **Servidor** — el proyecto está pensado para `http://localhost/sget`.
   `Config::basePath()` detecta la ruta automáticamente, así que también funciona
   en la raíz del dominio o en otra carpeta.

---

## Cuentas de prueba

`sget.sql` incluye las tres, con las **mismas** credenciales para que probar sea
cómodo. Las contraseñas se guardan con `password_hash(..., PASSWORD_DEFAULT)`
(bcrypt): en el volcado no hay ningún texto plano.

| Rol | Documento | Correo | Contraseña |
|---|---|---|---|
| Administrador | `900000001` | `admin@sget.local` | `Sget#2026!` |
| Conductor | `900000002` | `conductor@sget.local` | `Sget#2026!` |
| Pasajero | `900000003` | `pasajero@sget.local` | `Sget#2026!` |

> En un entorno real hay que cambiar la del administrador.

---

## Pruebas

```bash
# 1) Reglas de negocio y datos (no necesita servidor)
php pruebas/smoke.php          # 68 · viajes, cierre automático, recurrencia
php pruebas/_reserva_test.php  # 41 · apartado duplicado, cobro real, embarque, avisos
php pruebas/_concurrencia_test.php  #  6 · dos pasajeros a la vez sobre el último cupo

# 2-4) Render, API y navegador real (necesitan servidor)
touch pruebas/.habilitar
SGET_DEBUG=1 php -S 127.0.0.1:8899 -t .      # en Windows: set SGET_DEBUG=1 && php -S ...
php pruebas/render.php        # 92 · páginas de los 3 roles + módulo de auditoría
php pruebas/api.php           # 10 · CSRF, errores por campo, auth, exportación
node pruebas/anuncios-visual.js    # 81 · módulo de anuncios: alta, filtros, carrusel y vistas
node pruebas/tema-visual.js     # 441 · tema claro/oscuro y contraste en 17 páginas
node pruebas/visual-visual.js   # 76 · modales de la landing y CRUD, sin draws
node pruebas/modal-visual.js    # 65 · modales de la landing (abrir/cerrar)
node pruebas/transicion-visual.js  # 18 · transiciones entre módulos
node pruebas/cancelacion-visual.js  # 39 · flujo de cancelación de viaje
node pruebas/recaudo-visual.js      # 36 · cobro en terminal: el importe NO se guarda en $0
node pruebas/informacion-visual.js  # 59 · panel de información, incluida la pestaña de pasajeros
rm pruebas/.habilitar        # ¡bórralo siempre!
```

`SGET_DEBUG=1` hace visibles los errores PHP. **Sin él, un error fatal deja
la página en blanco sin explicación**: por eso está en el comando.

---

## Estructura resumida

```
core/         Config · Database(PDO) · Fecha · Auth · Password · Flash/Validator · bootstrap
services/     RutaService · VehiculoService · UsuarioService · ViajeService · NotificacionService
              ReservaService (cupos, cobro, embarque, no-presentación) · InformacionService
views/        modals/ (ruta, viaje, vehiculo, usuario, cancelar-viaje, ayuda, notificaciones)
              partials/ (head, foot)
assets/css/   01-base · 02-layout · 03-componentes · 04-modales · 05-tablas · 06-responsive · index
assets/js/    sget-modal (motor de modales) · sget-cru · sget-page
              sget-anuncios (carrusel de la landing + contador de vistas)
api/index.php API JSON única
migraciones/  migrar.php + 002…009
              009 · estados de vehículo, unicidad de `usuario`, catálogo de
                   permisos y usuarios de prueba
pruebas/      suites de humo, API y navegador. FUERA del webroot por .htaccess
              y por `pruebas/_guardia.php`: sin `pruebas/.habilitar` no corren.
Admin/ · Conductor/ · Pasajero/   páginas (pintan y delegan)
includes/     header · sidebar · tema · modal inactividad
img/anuncios/ portada.svg (banner por defecto; la migración lo copia a uploads)
```

---

## Reglas de la casa

1. **No escribas lógica de negocio en las páginas.** Va en `services/`.
2. **No escribas JavaScript en las páginas.** Usa los atributos `data-sget-*`.
3. **No definas estados nuevos.** Todo está en `core/Config.php`.
4. **No conviertas fechas a mano.** Usa `core/Fecha.php`.
5. **Cambios de base de datos → migración nueva** en `migraciones/`.
6. **Nada de datos por defecto inventados.** Si un campo es obligatorio, se
   valida; si es opcional, se guarda `NULL`, nunca `''` ni `0000-00-00`.
7. **Una imagen que referencie a un archivo inexistente es un fallo**, no un
   borde: se comprueba en disco (`AnuncioService::imagenExiste`) y se dice en
   pantalla. Los SVG se validan como XML, porque `getimagesize()` no los lee y
   un SVG mal formado se sirve con 200 sin dibujarse nunca.
8. **`reserva.valor_pagado` es el VALOR PACTADO**, no solo lo cobrado: se escribe
   al crear la reserva. Si se guarda `NULL` o `0`, el botón de cobrar del recaudo
   acaba registrando `$0` y los ingresos no suman nada. Los informes suman
   únicamente las reservas `Confirmada`, así que un valor pendiente nunca infla
   la caja.
9. **`reserva.embarco` va aparte del estado de pago.** Se puede pagar antes y no
   subir, o pagar al abordar y sí subir: en un solo ENUM no caben los dos
   casos. `NULL` significa «nadie lo ha decidido todavía», que no es lo mismo que
   «no vino».

---

## Seguridad · lo que garantiza esta versión

* **Autorización de mínimo privilegio.** `Auth::tieneAcceso()` decide en cascada:
  1. `usuario_permisos` gana siempre (concedido o denegado explícito);
  2. si el usuario no ha decidido nada, decide `rol_permiso`;
  3. si tampoco, **se deniega**. Antes la ausencia de permiso significaba
     «permitido», de modo que el botón oculto era la única barrera real.
* **Permiso Y propiedad.** El conductor puede cambiar el estado de un viaje,
  pero `Auth::exigirViaje()` comprueba además que sea suyo: sin eso,
  `api/index.php?modulo=viaje&accion=finalizar&id=…` le cerraba el viaje de otro.
* **CSRF en todas las acciones que modifican datos**: login, registro, perfil,
  idioma, permisos, anuncios, reservas, viajes y el cierre de viaje del
  conductor. El token vive en la sesión y lo emite `Auth::campoToken()`.
* **Contraseñas solo con `password_hash` / `password_verify`**, todo pasa por
  `core/Password.php`. Las cuentas heredadas en MD5 o texto plano se migran al
  vuelo al iniciar sesión, sin perder el acceso legítimo. La alta con Google crea
  la cuenta con una contraseña aleatoria inutilizable, nunca vacía.
* **Login con Google verificado por completo**: `iss`, `aud`, `azp`, `exp`,
  `sub` y `email_verified`. Antes solo se comprobaba que el token trajera un
  correo, lo que no es autenticación sino suposición.
* **Login bloqueado por fuerza bruta**: a partir del quinto fallo se bloquea la
  cuenta durante 5 minutos; el mensaje es el mismo para «no existe» y «contraseña
  incorrecta», para no permitir enumerar cuentas.
* **Cookie de sesión coherente con el protocolo**: `Auth::iniciar()` marca
  `secure` solo cuando la petición es HTTPS. Fijado a `true` sin mirar, en la red
  local el navegador descartaba la cookie y el login se perdía al instante.
* **Cupos bajo concurrencia**: `ReservaService::crear()` bloquea la fila del viaje
  (`SELECT … FOR UPDATE`) dentro de la transacción y vuelve a contar los puestos
  vivos antes de insertar. Dos pasajeros simultáneos no pueden agotar el mismo
  último cupo.
* **La carpeta `pruebas/` no es pública**: `.htaccess` la limita a la máquina
  local y `pruebas/_guardia.php` exige además el interruptor `pruebas/.habilitar`.

## Ayudas del sistema

Hay **un único** botón «?» flotante en la esquina inferior derecha de todas las
pantallas del panel. Abre el modal **«Ayudas del sistema»**, cuyo contenido
depende del rol y del módulo en el que se esté.

El sistema es declarativo: para documentar una pantalla nueva basta con añadir su
entrada al catálogo de `views/modals/ayuda.php`. No hay que tocar la página ni
ningún JavaScript, y no se duplican modales.

## Vistas previas y enlaces

* **Anuncios → «Ver landing»** abre una **vista previa en un modal** (un `<iframe>`
  de `procesos/anuncio_vista.php`), no la portada real: el administrador no pierde
  el listado ni el contexto. Se avisa de si el anuncio se está mostrando o no.
* **Anuncios → «Dirección del botón»** conserva su diseño, pero el botón abre la
  portada en una pestaña nueva (antes era un `<button>` sin `type` dentro del
  formulario, así que recargaba la página y perdía lo escrito).
