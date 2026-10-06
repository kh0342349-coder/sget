<?php
/**
 * migraciones/010_unico_sistema_permisos_y_tickets.php
 * DESC: Un solo sistema de permisos, calificación única, freno de login persistente y retiro de tablas huérfanas
 *
 * -----------------------------------------------------------------------------
 * QUÉ ARREGLA (segunda ronda de auditoría)
 * -----------------------------------------------------------------------------
 *
 * 1. SISTEMA DE PERMISOS ÚNICO
 *    Existían DOS mecanismos decidiendo quién puede hacer qué:
 *
 *      NUEVO   permisos · rol_permiso · usuario_permisos   (el que usa Auth)
 *      VIEJO   tabla `restricciones` + columna `usuario.restricciones`
 *
 *    El viejo lo escribía `Admin/gestion_permisos.php` desde un formulario sin
 *    token CSRF, así que además de estar muerto (nadie leía esas tablas para
 *    autorizar: `Conductor/conductor.php` sí las leía, pero solo para esconder
 *    tarjetas) era una puerta abierta. Se elimina:
 *
 *      · tabla  `restricciones`
 *      · tabla  `usuario_permiso_denegada` → `usuario_permiso_denegado`
 *        (nunca la leyó nadie: la denegación se resuelve con
 *         `usuario_permisos.permitido = 0`)
 *      · columna `usuario.restricciones`
 *
 *    Antes de borrarlas se MIGRA lo que hubiera:
 *      · `restricciones` (módulos denegados a administradores) se traduce a
 *        filas `usuario_permisos` con `permitido = 0` sobre el permiso del
 *        módulo equivalente, de modo que la denegación sigue existiendo pero
 *        dentro del sistema único.
 *      · `usuario.restricciones` (CSV para conductores y pasajeros) se descarta:
 *        sus etiquetas (`ver_rutas`, `ver_ranking`, `ver_viajes`, `historial`,
 *        `calificar`) no corresponden a ningún permiso del catálogo; el sistema
 *        nuevo los decide por rol.
 *
 * 2. `crear_reporte` (permiso nuevo)
 *    El pasajero necesita un permiso propio para abrir reportes: antes la
 *    acción existía en la API pero quedaba detrás de `requerirAdmin()`, así
 *    que era inalcanzable.
 *
 * 3. UNA CALIFICACIÓN POR PASAJERO Y VIAJE
 *    La comprobación en PHP no sobrevive a dos peticiones simultáneas. Se añade
 *        UNIQUE (id_via_cal, id_usu_rem)
 *    más un `CHECK` de 1..5 estrellas sobre `pun_cal`. Si ya hay duplicados se
 *    borran conservando la más antigua antes de crear la restricción.
 *
 * 4. ÍNDICES QUE FALTABAN
 *      · `calificacion (id_via_cal, id_usu_rem)` — la consulta de «¿ya
 *        califiqué?» es la más repetida de todo el módulo.
 *      · `reportes_pasajeros (estado, fecha DESC)` — el panel del admin ordena
 *        y filtra siempre por esas dos columnas.
 *
 * 5. `sget_login_intentos`
 *    El freno a la fuerza bruta vivía en `$_SESSION`: crear una sesión nueva
 *    lo reiniciaba. Ahora se persiste en el servidor, con la clave
 *    `documento|IP`.
 *
 * 6. TABLAS HUÉRFANAS
 *      · `programacion` — no la referencia NINGÚN archivo del proyecto. Es un
 *        duplicado de `viaje.id_veh`: no añade información.
 *      · `asignacion` — solo la usaba un `LEFT JOIN` decorativo en
 *        `Admin/admin.php`, y sus datos se contradecían con `viaje`
 *        (conductor y vehículo ya viven en el viaje).
 *    Se eliminan las dos.
 *
 * 7. `usuario.num_doc_usu` / `corre_usu`
 *    Se normalizan a mayúsculas para que el login no dependa de cómo lo escribió
 *    el usuario la primera vez.
 * -----------------------------------------------------------------------------
 * IDEMPOTENCIA
 *    Cada bloque comprueba antes de actuar y tolera los errores de DDL que
 *    MySQL envuelve con el errno genérico 1005, así que se puede aplicar sobre
 *    una base recién importada de `sget.sql` sin romper nada.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

/* -------------------------------------------------------------------------- */
/* Utilidades                                                                  */
/* -------------------------------------------------------------------------- */

function ddl010(string $sql): void
{
    try {
        Database::query($sql);
    } catch (Throwable $e) {
        if (!Database::errorEs($e, [1050, 1060, 1061, 1062, 1091, 1168, 1215, 1264, 1280, 1305, 1557, 1586, 1005])) {
            error_log('[SGET][mig 010] ' . $e->getMessage());
            throw $e;
        }
    }
}

function existeIndice010(string $tabla, string $indice): bool
{
    return Database::scalar(
        'SELECT COUNT(*) FROM information_schema.statistics
          WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
        [$tabla, $indice]
    ) > 0;
}

function existeColumna010(string $tabla, string $columna): bool
{
    return Database::scalar(
        'SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND column_name = ? AND table_name = ?',
        [$columna, $tabla]
    ) > 0;
}

function existeTabla010(string $tabla): bool
{
    return Database::scalar(
        'SELECT COUNT(*) FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_name = ?',
        [$tabla]
    ) > 0;
}

/* -------------------------------------------------------------------------- */
/* 1) UNICO SISTEMA DE PERMISOS                                               */
/* -------------------------------------------------------------------------- */

/* Catálogo que debe existir para que los tres roles sigan entrando en algo. */
$permiso = static function (string $nombre, string $modulo, int $rol, string $descripcion): int {
    $id = (int) Database::scalar('SELECT id_permiso FROM permisos WHERE nombre_permiso = ?', [$nombre]);
    if ($id > 0) {
        return $id;
    }
    Database::query(
        'INSERT INTO permisos (nombre_permiso, modulo, id_rol, descripcion) VALUES (?, ?, ?, ?)',
        [$nombre, $modulo, $rol > 0 ? $rol : null, $descripcion]
    );
    $id = (int) Database::pdo()->lastInsertId();

    if ($rol > 0) {
        Database::query('INSERT IGNORE INTO rol_permiso (id_rol, id_permiso) VALUES (?, ?)', [$rol, $id]);
    }
    return $id;
};

$crearReporte = $permiso(
    'crear_reporte',
    'reportes_pasajeros',
    3,
    'Permite al pasajero registrar un reporte sobre uno de sus viajes'
);

/* El administrador también puede (lo usa la interfaz de soporte). */
ddl010('INSERT IGNORE INTO rol_permiso (id_rol, id_permiso) VALUES (1, ' . $crearReporte . ')');

/* Migración de lo que hubiera en `restricciones`: cada módulo DENEGADO a un
   administrador se convierte en una denegación explícita dentro del sistema
   único. Se hace ANTES de borrar la tabla. */
if (existeTabla010('restricciones') && existeColumna010('usuario', 'restricciones')) {
    // Etiquetas del sistema viejo → permiso del catálogo con el mismo nombre.
    $equivalencia = [
        'admin'                 => 'acceder_admin',
        'asignaciones'          => 'gestionar_asignaciones',
        'gestion_permisos'      => 'gestionar_permisos',
        'ranking_conductores'   => 'ver_calificaciones',
        'anuncios'              => 'gestionar_anuncios',
        'reportes_pasajeros'    => 'gestionar_reportes_pasajeros',
        'comunicados'           => 'gestionar_comunicados',
        'reportes'              => 'ver_reportes_panel',
        'rutas'                 => 'crear_ruta',
        'usuarios'              => 'gestionar_usuarios',
        'vehiculos'             => 'gestionar_vehiculos',
        'viajes'                => 'gestionar_viajes',
    ];

    $denegados = Database::all('SELECT id_usu, modulo FROM restricciones');

    foreach ($denegados as $fila) {
        $nombrePermiso = $equivalencia[(string)$fila['modulo']] ?? null;
        if ($nombrePermiso === null) {
            continue;   // etiqueta sin equivalente: no hay dónde traducirla
        }
        $idPermiso = (int) Database::scalar(
            'SELECT id_permiso FROM permisos WHERE nombre_permiso = ?',
            [$nombrePermiso]
        );
        if ($idPermiso <= 0) {
            continue;
        }
        ddl010('INSERT IGNORE INTO usuario_permisos (id_usu, id_permiso, permitido) VALUES ('
            . (int)$fila['id_usu'] . ', ' . $idPermiso . ', 0)');
    }

    // Copia de seguridad antes de borrar (por si hay que auditar qué había).
    try {
        Database::query(
            'CREATE TABLE IF NOT EXISTS _mig_backup_restricciones AS SELECT * FROM restricciones'
        );
    } catch (Throwable $e) {
        error_log('[SGET][mig 010] sin copia de restricciones: ' . $e->getMessage());
    }

    ddl010('DROP TABLE IF EXISTS restricciones');
    ddl010('ALTER TABLE usuario DROP COLUMN restricciones');
    echo "   · restricciones migradas y retiradas\n";
}

/* `usuario_permiso_denegado` no la leía nadie: la denegación real se resuelve
   con `usuario_permisos.permitido = 0`. */
if (existeTabla010('usuario_permiso_denegado')) {
    $denegadosLegacy = Database::all(
        'SELECT d.id_usu, p.nombre_permiso
           FROM usuario_permiso_denegado d
           INNER JOIN permisos p ON p.id_permiso = d.id_permiso'
    );
    foreach ($denegadosLegacy as $fila) {
        $idPermiso = (int) Database::scalar(
            'SELECT id_permiso FROM permisos WHERE nombre_permiso = ?',
            [(string)$fila['nombre_permiso']]
        );
        if ($idPermiso > 0) {
            ddl010('INSERT IGNORE INTO usuario_permisos (id_usu, id_permiso, permitido) VALUES ('
                . (int)$fila['id_usu'] . ', ' . $idPermiso . ', 0)');
        }
    }
    ddl010('DROP TABLE IF EXISTS usuario_permiso_denegado');
    echo "   · usuario_permiso_denegado migrada y retirada\n";
}

/* -------------------------------------------------------------------------- */
/* 2) CALIFICACIONES: una por pasajero y viaje                                */
/* -------------------------------------------------------------------------- */

/* Si ya hay duplicados (posible porque nada lo impedía), se conserva el más
   antiguo: es el que el pasajero envió. */
$duplicados = (int) Database::scalar(
    'SELECT COUNT(*) FROM (
        SELECT id_via_cal, id_usu_rem FROM calificacion
         GROUP BY id_via_cal, id_usu_rem HAVING COUNT(*) > 1
    ) d'
);
if ($duplicados > 0) {
    Database::query(
        'DELETE c FROM calificacion c
          INNER JOIN (
            SELECT MIN(id_cal) AS conservar
              FROM calificacion GROUP BY id_via_cal, id_usu_rem HAVING COUNT(*) > 1
          ) d ON d.conservar = c.id_cal'
    );
    echo "   · {$duplicados} calificación(es) duplicada(s) consolidadas\n";
}

if (!existeIndice010('calificacion', 'uq_calificacion_viaje_usuario')) {
    ddl010('ALTER TABLE calificacion
            ADD UNIQUE KEY uq_calificacion_viaje_usuario (id_via_cal, id_usu_rem)');
}

/* Índice de la consulta de "¿ya calificaste?" */
if (!existeIndice010('calificacion', 'idx_calificacion_lookup')) {
    ddl010('ALTER TABLE calificacion
            ADD INDEX idx_calificacion_lookup (id_usu_rem, id_via_cal)');
}

/* La nota solo puede estar entre 1 y 5. */
if (!existeIndice010('calificacion', 'chk_calificacion_puntos')) {
    Database::query(
        "UPDATE calificacion SET pun_cal = 1 WHERE pun_cal < 1 OR pun_cal > 5 OR pun_cal IS NULL"
    );
    ddl010('ALTER TABLE calificacion
            ADD CONSTRAINT chk_calificacion_puntos CHECK (pun_cal BETWEEN 1 AND 5)');
}

if (!existeIndice010('reportes_pasajeros', 'idx_reportes_estado_fecha')) {
    ddl010('ALTER TABLE reportes_pasajeros
            ADD INDEX idx_reportes_estado_fecha (estado, fecha)');
}

/* -------------------------------------------------------------------------- */
/* 3) FRENO DE FUERZA BRUTA PERSISTENTE                                       */
/* -------------------------------------------------------------------------- */
ddl010("CREATE TABLE IF NOT EXISTS sget_login_intentos (
    clave           CHAR(64)     NOT NULL,
    documento       VARCHAR(20)  NOT NULL,
    ip              VARCHAR(45)  NOT NULL DEFAULT '',
    intentos        INT(11)      NOT NULL DEFAULT 0,
    ventana_inicio  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_intento  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (clave),
    KEY idx_login_intentos_doc (documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* -------------------------------------------------------------------------- */
/* 4) TABLAS HUÉRFANAS                                                         */
/* -------------------------------------------------------------------------- */
/*
 * `programacion` no la referencia ningún archivo: ni páginas, ni servicios,
 * ni consultas. Es un segundo sitio donde guardar «qué vehículo tiene qué
 * viaje», o sea, duplicaba `viaje.id_veh` y podía contradecirlo.
 *
 * `asignacion` sí se leía, pero en un único sitio y solo para una lista de
 * «conductores disponibles» que, además, se calculaba con un LEFT JOIN sobre
 * datos desincronizados. La fuente de verdad es `viaje.id_usu_via` / `viaje.id_veh`
 * y `ViajeService::conductoresDisponibles()` ya la usa.
 */
foreach (["programacion", "asignacion"] as $tablaRetirar) {
    if (existeTabla010($tablaRetirar)) {
        ddl010('DROP TABLE IF EXISTS `' . $tablaRetirar . '`');
        echo "   · tabla huérfana `{$tablaRetirar}` retirada\n";
    }
}

/* -------------------------------------------------------------------------- */
/* 5) NORMALIZACIÓN DE IDENTIFICADORES                                         */
/* -------------------------------------------------------------------------- */
/*
 * El login busca por `num_doc_usu` con comparación EXACTA. Si alguien se
 * registró como «abc123» y luego escribe «ABC123», MySQL no lo encuentra (la
 * collation general_ci solo iguala mayúsculas en algunos juegos de caracteres
 * y no es una garantía). Se normaliza la columna a mayúsculas.
 *
 * Antes se corrigen los datos duplicados que impedirían el índice único.
 */
if (existeColumna010('usuario', 'num_doc_usu')) {
    Database::query("UPDATE usuario SET num_doc_usu = UPPER(TRIM(num_doc_usu)) WHERE num_doc_usu <> UPPER(TRIM(num_doc_usu))");
    Database::query("UPDATE usuario SET corre_usu = LOWER(TRIM(corre_usu)) WHERE corre_usu <> LOWER(TRIM(corre_usu))");
}

echo "   · migración 010 completada\n";
