# SGET — Sistema Inteligente de Transporte

Aplicación web en PHP + MySQL para la gestión de rutas, viajes, flota, usuarios y
reservas de transporte terrestre.

📖 **[Ver `ARQUITECTURA.md`](ARQUITECTURA.md)** — es el documento que explica la
estructura por capas, el error de datos que se corrigió y cómo trabajar sobre
el proyecto sin volver a introducirlo.

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

4. **Servidor** — el proyecto está pensado para `http://localhost/sget`.
   `Config::basePath()` detecta la ruta automáticamente, así que también funciona
   en la raíz del dominio o en otra carpeta.

---

## Pruebas

```bash
# Reglas de negocio y datos (no necesita servidor)
php pruebas/smoke.php

# Renderizado y errores PHP de todas las páginas (necesita servidor)
touch pruebas/.habilitar
php -S 127.0.0.1:8899 -t .
php pruebas/render.php
php pruebas/api.php
rm pruebas/.habilitar      # ¡bórralo siempre!
```

---

## Estructura resumida

```
core/         Config · Database(PDO) · Fecha · Auth · Flash/Validator · bootstrap
services/     RutaService · VehiculoService · UsuarioService · ViajeService · NotificacionService
views/        modals/ (ruta, viaje, vehiculo, usuario, cancelar-viaje, ayuda, notificaciones)
              partials/ (head, foot)
assets/css/   01-base · 02-layout · 03-componentes · 04-modales · 05-tablas · 06-responsive · index
assets/js/    sget-modal (motor de modales) · sget-cru · sget-page
api/index.php API JSON única
migraciones/  migrar.php + 002/003/004
pruebas/      smoke.php · render.php · api.php
Admin/ · Conductor/ · Pasajero/   páginas (pintan y delegan)
includes/     header · sidebar · tema · modal inactividad
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
