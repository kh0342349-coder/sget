<?php
/**
 * DESC: Buzón de notificaciones con firma + módulo de anuncios de la landing
 *
 * CONTEXTO
 *   1) NOTIFICACIONES
 *      El buzón solo servía para un evento (cancelación de viaje) y no tenía
 *      forma de evitar duplicados: el mismo aviso podía repetirse cada vez que
 *      se recargaba la página que lo disparaba.
 *      Se agrega `notificacion.firma`, una clave lógica de "una sola vez por
 *      evento" (ej. 'recordatorio:12:7'), con índice ÚNICO junto a `id_usu`.
 *      MySQL no considera duplicados los NULL, así que los avisos sin firma
 *      (los difusiones del admin) pueden repetirse libremente.
 *
 *   2) ANUNCIOS
 *      Tabla nueva para los anuncios banners de la landing: imagen, textos,
 *      enlace, ventana de publicación y orden. El admin los sube, edita,
 *      activa/desactiva y elimina desde un módulo propio.
 *      Se guardan en `uploads/anuncios` (fuera de la raíz de código, para que
 *      el archivo servido no se pueda ejecutar).
 *
 * TODO ES IDEMPOTENTE: se puede ejecutar tantas veces como haga falta.
 */
declare(strict_types=1);

$migrar = function (): void {
    $log = fn(string $m) => imprimir('      ' . $m);

    /* ================================================================== */
    /* 1) notificacion.firma  (anti-duplicados)                            */
    /* ================================================================== */
    try {
        Database::query("ALTER TABLE notificacion ADD COLUMN IF NOT EXISTS firma VARCHAR(160) NULL DEFAULT NULL");
        $log('[OK] notificacion.firma agregada.');
    } catch (Throwable $e) {
        if (!in_array($e->errorInfo[1] ?? 0, [1060, 1061], true)) throw $e;
        $log('[--] notificacion.firma ya existía.');
    }

    try {
        Database::query("ALTER TABLE notificacion ADD UNIQUE INDEX IF NOT EXISTS ux_notif_firma (id_usu, firma)");
        $log('[OK] Índice único (id_usu, firma): un evento = un aviso.');
    } catch (Throwable $e) {
        if (!in_array($e->errorInfo[1] ?? 0, [1060, 1061, 1062], true)) throw $e;
        $log('[--] Índice de firma ya existía.');
    }

    // Avisos antiguos: se les da una firma derivada para que un reenvío del
    // mismo evento no duplique lo que el usuario ya tiene en su buzón.
    try {
        Database::query(
            "UPDATE notificacion
                SET firma = CONCAT('legado:', id_not)
              WHERE firma IS NULL AND leida = 0"
        );
        $log('[OK] Avisos pendientes legacy adicionalados con firma propia.');
    } catch (Throwable $e) {
        $log('[--] No se pudieron sellar los avisos anteriores: ' . $e->getMessage());
    }

    /* ================================================================== */
    /* 2) Tabla de anuncios                                                 */
    /* ================================================================== */
    Database::query(
        "CREATE TABLE IF NOT EXISTS anuncio (
            id_ann        INT(11) NOT NULL AUTO_INCREMENT,
            titulo        VARCHAR(150) NOT NULL,
            subtitulo     VARCHAR(255) NULL DEFAULT NULL,
            descripcion   TEXT NULL,
            imagen        VARCHAR(255) NOT NULL COMMENT 'Archivo dentro de uploads/anuncios',
            enlace        VARCHAR(255) NULL DEFAULT NULL COMMENT 'URL o modulo interno (rutas.php, viajes.php…)',
            boton_texto    VARCHAR(60) NULL DEFAULT NULL,
            color_tema    VARCHAR(20) NOT NULL DEFAULT 'azul' COMMENT 'azul|emerald|ambars|morado|rojo',
            activo        TINYINT(1) NOT NULL DEFAULT 1,
            destacado     TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = aparece como destacado en la landing',
            fec_inicio    DATE NULL DEFAULT NULL COMMENT 'NULL = desde ya',
            fec_fin       DATE NULL DEFAULT NULL COMMENT 'NULL = sin vencimiento',
            Orden         INT(11) NOT NULL DEFAULT 0,
            veces_vista   INT(11) NOT NULL DEFAULT 0,
            creado_por    INT(11) NULL DEFAULT NULL,
            fec_creacion  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_ann),
            KEY ux_anuncio_activo (activo, fec_inicio, fec_fin),
            KEY ux_anuncio_orden (orden)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $log('[OK] Tabla `anuncio` creada.');

    /* ------------------------------------------------------------------ */
    /* 3) Carpeta de imágenes                                              */
    /* ------------------------------------------------------------------ */
    $carpeta = dirname(__DIR__) . '/uploads/anuncios';
    if (!is_dir($carpeta)) {
        if (@mkdir($carpeta, 0755, true)) {
            $log('[OK] uploads/anuncios creada.');
        } else {
            $log('[!!] No se pudo crear uploads/anuncios: revisa los permisos.');
        }
    } else {
        $log('[--] uploads/anuncios ya existía.');
    }

    // Blindaje: aunque el servidor entregue estáticos, nunca ejecuta PHP.
    $htaccess = $carpeta . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi)$\">\n    Require all denied\n</FilesMatch>\n");
        $log('[OK] .htaccess de bloqueo escrito en uploads/anuncios.');
    }

    /* ------------------------------------------------------------------ */
    /* 4) Anuncio de ejemplo para que la landing no se vea vacía           */
    /* ------------------------------------------------------------------ */
    // El banner por defecto se COPIA desde img/anuncios/portada.svg. Antes se
    // insertaba la fila apuntando a 'portada.svg' sin que el archivo existiera
    // nunca: la landing pintaba un rectángulo negro con el texto alt y el
    // módulo mostraba una tarjeta sin foto. Ahora la imagen es real.
    $portada = $carpeta . '/portada.svg';
    $origen  = dirname(__DIR__) . '/img/anuncios/portada.svg';
    if (!is_file($portada) && is_file($origen) && @copy($origen, $portada)) {
        $log('[OK] Banner por defecto copiado a uploads/anuncios/portada.svg.');
    } elseif (is_file($portada)) {
        $log('[--] El banner por defecto ya estaba copiado.');
    } else {
        $log('[!!] No se encontró img/anuncios/portada.svg: no se creará el anuncio de ejemplo.');
    }

    $hay = (int) Database::scalar("SELECT COUNT(*) FROM anuncio");
    if ($hay === 0 && is_file($portada)) {
        Database::query(
            "INSERT INTO anuncio (titulo, subtitulo, descripcion, imagen, enlace, boton_texto, color_tema, activo, destacado, orden)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, 1)",
            [
                'Bienvenido a SGET',
                'Reserva tu cupo en línea y monitorea tu viaje en tiempo real',
                'Consulta los viajes disponibles, asegura tu puesto y recibe avisos si algo cambia.',
                'portada.svg',
                'Admin/viajes.php',
                'Ver viajes',
                'azul',
            ]
        );
        $log('[OK] Anuncio de ejemplo creado (portada.svg).');
    } elseif ($hay === 0) {
        $log('[--] Sin anuncio de ejemplo: la landing arranca sin carrusel, como está previsto.');
    }

    /* ------------------------------------------------------------------ */
    /* 5) Permisos de los módulos nuevos                                   */
    /* ------------------------------------------------------------------ */
    $log('[OK] Registrando los permisos de «anuncios» y «comunicados».');
    foreach ([
        ['gestionar_anuncios', 'anuncios', Config::ROL_ADMIN,
         'Permite subir, editar, publicar y eliminar anuncios de la página de inicio'],
        ['gestionar_comunicados', 'comunicados', Config::ROL_ADMIN,
         'Permite enviar avisos y comunicados al buzón de pasajeros y conductores'],
    ] as [$clave, $modulo, $rol, $desc]) {
        Database::query(
            "INSERT INTO permisos (nombre_permiso, modulo, id_rol, descripcion)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE modulo = VALUES(modulo), id_rol = VALUES(id_rol), descripcion = VALUES(descripcion)",
            [$clave, $modulo, $rol, $desc]
        );
    }
};
