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

1. **Base de datos** — crear e importar `sget.sql`:
   ```bash
   mysql -uroot -e "CREATE DATABASE sget DEFAULT CHARACTER SET utf8mb4"
   mysql -uroot sget < sget.sql
   ```

2. **Migraciones** (obligatorio la primera vez: corrige los datos por defecto):
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

## Pruebas

```bash
# 1) Reglas de negocio y datos (no necesita servidor)
php pruebas/smoke.php

# 2-4) Render, API y navegador real (necesitan servidor)
touch pruebas/.habilitar
SGET_DEBUG=1 php -S 127.0.0.1:8899 -t .      # en Windows: set SGET_DEBUG=1 && php -S ...
php pruebas/render.php        # 92 · páginas de los 3 roles + módulo de auditoría
php pruebas/api.php           # 10 · CSRF, errores por campo, auth, exportación
node pruebas/anuncios-visual.js    # 79 · módulo de anuncios: alta, filtros, carrusel y vistas
node pruebas/tema-visual.js     # 441 · tema claro/oscuro y contraste en 17 páginas
node pruebas/visual-visual.js   # 76 · modales de la landing y CRUD, sin draws
node pruebas/modal-visual.js    # 65 · modales de la landing (abrir/cerrar)
node pruebas/transicion-visual.js  # 18 · transiciones entre módulos
node pruebas/cancelacion-visual.js  # 39 · flujo de cancelación de viaje
rm pruebas/.habilitar        # ¡bórralo siempre!
```

`SGET_DEBUG=1` hace visibles los errores PHP. **Sin él, un error fatal deja
la página en blanco sin explicación**: por eso está en el comando.

---

## Estructura resumida

```
core/         Config · Database(PDO) · Fecha · Auth · Flash/Validator · bootstrap
services/     RutaService · VehiculoService · UsuarioService · ViajeService · NotificacionService
views/        modals/ (ruta, viaje, vehiculo, usuario, cancelar-viaje, ayuda, notificaciones)
              partials/ (head, foot)
assets/css/   01-base · 02-layout · 03-componentes · 04-modales · 05-tablas · 06-responsive · index
assets/js/    sget-modal (motor de modales) · sget-cru · sget-page
              sget-anuncios (carrusel de la landing + contador de vistas)
api/index.php API JSON única
migraciones/  migrar.php + 002/003/004/005/006/007
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
