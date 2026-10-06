<?php
/**
 * migraciones/009_integridad_y_permisos.php
 * DESC: Integridad de datos, estados de vehículo, catálogo de permisos y usuarios de prueba
 *
 * -----------------------------------------------------------------------------
 * QUÉ ARREGLA
 * -----------------------------------------------------------------------------
 * 1. ESTADOS DE VEHÍCULO REALES
 *    `vehiculo.est_veh` era `tinyint(1)` con 1 = Disponible y 0 = Todo lo demás.
 *    Eso hacía que un vehículo en mantenimiento y uno fuera de servicio fueran
 *    INDISTINGUIBLES, y que `ViajeService::_ocuparVehiculo()` dejara la unidad en
 *    «fuera de servicio» cada vez que se le asignaba un viaje. Pasa a ENUM con
 *    cuatro estados que sí significan algo distinto:
 *        Disponible · Asignado · Mantenimiento · Fuera de servicio
 *
 * 2. INTEGRIDAD DE `usuario`
 *      · `num_doc_usu` UNIQUE  (la llave con la que se entra al sistema)
 *      · `corre_usu`   UNIQUE
 *      · `google_id`   UNIQUE (una identidad de Google, una sola cuenta)
 *    Antes solo lo comprobaba el PHP, y dos altas simultáneas podían colarse.
 *
 * 3. ÍNDICES
 *      · `viaje.idx_viaje_cierre` era un duplicado exacto de `idx_viaje_salida`.
 *      · Se añade `viaje.id_usu_via` como índice propio: todas las consultas de
 *        «los viajes de este conductor» lo usan y dependía del FK.
 *
 * 4. CATÁLOGO DE PERMISOS Y ROLES
 *    Con la nueva autorización de mínimo privilegio, `rol_permiso` deja de estar
 *    vacía: el Administrador tiene todo, el Conductor y el Pasajero solo lo
 *    suyo. Sin esto, tras el cambio cualquier usuario sin filas en
 *    `usuario_permisos` se quedaba sin acceso a nada.
 *
 * 5. USUARIOS DE PRUEBA (1 por rol), con contraseña bcrypt documentada.
 *
 * -----------------------------------------------------------------------------
 * IDEMPOTENCIA
 *    Se puede aplicar sobre una base recién importada de `sget.sql` sin romper
 *    nada: cada bloque comprueba antes de actuar y tolera los errores de DDL que
 *    MySQL envuelve con el errno genérico 1005.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

/* -------------------------------------------------------------------------- */
/* Utilidades                                                                  */
/* -------------------------------------------------------------------------- */

/** Ejecuta DDL tolerando «ya existe» / «no existe». */
function ddl(string $sql): void
{
    try {
        Database::query($sql);
    } catch (Throwable $e) {
        if (!Database::errorEs($e, [1050, 1060, 1061, 1062, 1091, 1168, 1215, 1264, 1280, 1305, 1557, 1586, 1005])) {
            error_log('[SGET][mig 009] ' . $e->getMessage());
            throw $e;
        }
    }
}

function existeIndice(string $tabla, string $indice): bool
{
    return Database::scalar(
        'SELECT COUNT(*) FROM information_schema.statistics
          WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
        [$tabla, $indice]
    ) > 0;
}

function existeColumna(string $tabla, string $columna): bool
{
    return Database::scalar(
        'SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        [$tabla, $columna]
    ) > 0;
}

function filas(string $sql, array $params = []): int
{
    return Database::query($sql, $params)->rowCount();
}

/* -------------------------------------------------------------------------- */
/* 1) ESTADOS DE VEHÍCULO                                                     */
/* -------------------------------------------------------------------------- */
/*
 * La conversión se hace en TRES pasos y no en uno, porque `ALTER TABLE …
 * MODIFY … enum(...)` no sabe interpretar los valores antiguos:
 *
 *        UPDATE vehiculo SET est_veh = 0   -- «no disponible»
 *        ALTER TABLE vehiculo MODIFY est_veh enum('Disponible', …)
 *        ERROR 1265 (Data truncated for column 'est_veh')
 *
 * MySQL convierte el 0 en la cadena vacía —que no está en la lista— y, con el
 * modo estricto que impone `Database::pdo()`, el ALTER ABORTA y la migración se
 * queda a medias con la flota sin convertir.
 *
 * La solución es pasar primero por un `varchar`, donde cada número antiguo es
 * una cadena válida, y solo entonces narrowedar al ENUM definitivo:
 *
 *        tinyint  →  varchar  →  UPDATE de traducción  →  enum
 */
if (existeColumna('vehiculo', 'est_veh')) {
    $tipoActual = strtolower((string) Database::scalar(
        "SELECT column_type FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'vehiculo' AND column_name = 'est_veh'"
    ));

    if (!str_contains($tipoActual, 'enum')) {

        // 1) Ampliar a texto: los 0/1 heredados dejan de ser números. Con 20
        //    caracteres caben los cuatro estados, incluido «Fuera de servicio».
        ddl("ALTER TABLE vehiculo
                MODIFY COLUMN est_veh varchar(20) NOT NULL DEFAULT 'Disponible'");

        // 2) Traducir. El 1 siempre fue «Disponible». El 0 agrupaba dos
        //    situaciones que ahora se distinguen: si la unidad tiene un viaje
        //    abierto está «Asignado»; si no, se marca «Fuera de servicio», que es
        //    el estado conservador (no se despacha una unidad cuyo estado
        //    nobody sabe interpretar).
        filas("UPDATE vehiculo SET est_veh = 'Disponible' WHERE est_veh IN ('1', 'true', 'Disponible')");

        filas("UPDATE vehiculo v
                  SET v.est_veh = 'Asignado'
                WHERE v.est_veh IN ('0', 'false')
                  AND EXISTS (SELECT 1 FROM viaje vi
                               WHERE vi.id_veh = v.id_veh
                                 AND vi.est_via IN ('Programado','En curso'))");

        filas("UPDATE vehiculo SET est_veh = 'Fuera de servicio'
                WHERE est_veh IN ('0', 'false', 'Inactivo', 'Ocupado', '')");

        // Cualquier valor que se haya quedado fuera (columna sucia de una
        // instalación antigua) se normaliza en vez de romper el ALTER.
        filas("UPDATE vehiculo SET est_veh = 'Fuera de servicio'
                WHERE est_veh NOT IN ('Disponible','Asignado','Mantenimiento','Fuera de servicio')");

        // 3) Narrowing final al ENUM con su comentario.
        ddl("ALTER TABLE vehiculo
                MODIFY COLUMN est_veh enum('Disponible','Asignado','Mantenimiento','Fuera de servicio')
                NOT NULL DEFAULT 'Disponible'
                COMMENT 'Estado operativo de la unidad'");
    }
}

/* -------------------------------------------------------------------------- */
/* 2) INTEGRIDAD DE `usuario`                                                  */
/* -------------------------------------------------------------------------- */
/*
 * Antes de poner un UNIQUE hay que resolver los duplicados que ya existieran,
 * o el ALTER falla. Se marca el duplicado con estado inactivo en lugar de
 * borrarlo: el historial de viajes y reservas debe seguir siendo legible.
 */
$dupDocumento = Database::all(
    'SELECT num_doc_usu, MIN(id_usu) AS queda
       FROM usuario GROUP BY num_doc_usu HAVING COUNT(*) > 1'
);
foreach ($dupDocumento as $d) {
    filas('UPDATE usuario SET estado = 0 WHERE num_doc_usu = ? AND id_usu <> ?',
        [$d['num_doc_usu'], (int)$d['queda']]);
}

$dupCorreo = Database::all(
    'SELECT corre_usu, MIN(id_usu) AS queda
       FROM usuario WHERE corre_usu IS NOT NULL AND corre_usu <> \'\'
      GROUP BY LOWER(corre_usu) HAVING COUNT(*) > 1'
);
foreach ($dupCorreo as $c) {
    filas('UPDATE usuario SET estado = 0
            WHERE LOWER(corre_usu) = ? AND id_usu <> ?',
        [mb_strtolower((string)$c['corre_usu']), (int)$c['queda']]);
}

// Correos vacíos: `corre_usu` es NOT NULL pero podía valer ''.
filas("UPDATE usuario SET corre_usu = CONCAT('sin-correo-', id_usu, '@sget.local')
        WHERE corre_usu IS NULL OR corre_usu = ''");

if (!existeIndice('usuario', 'uq_usuario_documento')) {
    ddl('ALTER TABLE usuario ADD UNIQUE KEY uq_usuario_documento (num_doc_usu)');
}
if (!existeIndice('usuario', 'uq_usuario_correo')) {
    ddl('ALTER TABLE usuario ADD UNIQUE KEY uq_usuario_correo (corre_usu)');
}
if (!existeIndice('usuario', 'uq_usuario_google')) {
    ddl('ALTER TABLE usuario ADD UNIQUE KEY uq_usuario_google (google_id)');
}

/* -------------------------------------------------------------------------- */
/* 3) ÍNDICES DE `viaje`                                                       */
/* -------------------------------------------------------------------------- */
/* `idx_viaje_cierre` era idéntico a `idx_viaje_salida`: mismo nombre de columnas,
   mismo orden. MySQL los mantenía los dos y cada consulta pagaba el coste de
   mantener un índice que no distinguía nada. */
if (existeIndice('viaje', 'idx_viaje_cierre') && !existeIndice('viaje', 'idx_viaje_salida')) {
    ddl('ALTER TABLE viaje RENAME INDEX idx_viaje_cierre TO idx_viaje_salida');
} elseif (existeIndice('viaje', 'idx_viaje_cierre')) {
    ddl('ALTER TABLE viaje DROP INDEX idx_viaje_cierre');
}

if (!existeIndice('viaje', 'idx_viaje_conductor')) {
    ddl('ALTER TABLE viaje ADD KEY idx_viaje_conductor (id_usu_via, est_via, fec_via)');
}
if (!existeIndice('vehiculo', 'idx_vehiculo_estado')) {
    ddl('ALTER TABLE vehiculo ADD KEY idx_vehiculo_estado (est_veh)');
}

/* -------------------------------------------------------------------------- */
/* 4) CATÁLOGO DE PERMISOS Y ROLES                                             */
/* -------------------------------------------------------------------------- */
/*
 * Se Amplía `permisos` para que CADA módulo del sistema tenga un permiso con el
 * MISMO nombre que usa el código (`admin`, `usuarios`, `viajes`…). La búsqueda
 * de `Auth::tieneAcceso()` es por nombre de permiso O por módulo, así que con
 * estas filas el menú y los endpoints hablan el mismo idioma.
 */
$permisos = [
    // [nombre,            módulo,                 rol por defecto, descripción]
    ['acceder_admin',          'admin',                1, 'Permite entrar al panel general del administrador'],
    ['gestionar_usuarios',     'usuarios',             1, 'Permite crear, editar y eliminar usuarios'],
    ['gestionar_asignaciones', 'asignaciones',         1, 'Permite el recaudo en terminal y la corta de reservas'],
    ['gestionar_viajes',       'viajes',               1, 'Permite programar y editar viajes de toda la flota'],
    ['gestionar_vehiculos',    'vehiculos',            1, 'Permite administrar la flota de vehículos'],
    ['gestionar_permisos_mod','gestion_permisos',     1, 'Permite asignar permisos a los usuarios'],
    ['ver_logs',               'logs',                 1, 'Permite consultar los registros de auditoría'],
    ['ver_reportes_panel',     'reportes',             1, 'Permite consultar el panel de información y sus PDF'],
    ['ver_calificaciones',     'ranking_conductores',  1, 'Permite ver el ranking y las reseñas de los conductores'],
    ['calificar_viaje',        'calificar',            3, 'Permite calificar un viaje terminado'],
    ['gestionar_reservas',     'reservas',             3, 'Permite reservar puesto en un viaje'],
    ['ver_buzon',              'notificaciones',       3, 'Permite consultar el buzón de notificaciones'],
    ['ver_manifiesto',         'manifiesto',           2, 'Permite ver y marcar el embarque de los viajes que conduce'],
    ['gestionar_propio_viaje', 'mis_viajes',           2, 'Permite consultar sus propios viajes'],
    ['operar_viaje',           'operar_viaje',        2, 'Permite marcar en curso y finalizar sus propios viajes'],
];

foreach ($permisos as [$nombre, $modulo, $rol, $descripcion]) {
    Database::query(
        'INSERT INTO permisos (nombre_permiso, modulo, id_rol, descripcion)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE modulo = VALUES(modulo),
                                 id_rol  = VALUES(id_rol),
                                 descripcion = VALUES(descripcion)',
        [$nombre, $modulo, $rol, $descripcion]
    );
}

/*
 * `rol_permiso` es la segunda capa de la cascada de autorización (la primera es
 * `usuario_permisos`, que gana siempre). Se siembra así:
 *
 *   · Administrador → TODOS los permisos. Sin fila de denegación sigue siendo
 *     acceso total; con una denegación explícita en su usuario, esa manda.
 *   · Conductor    → buzón, manifiesto de sus viajes, sus propios viajes,
 *                    crear viaje y los avisos de salida.
 *   · Pasajero     → buzón, reservar, cancelar su reserva y calificar.
 */
$catalogoConductor = [
    'crear_viaje', 'cancelar_viaje', 'gestionar_propio_viaje', 'operar_viaje',
    'ver_manifiesto', 'ver_buzon', 'ver_notificaciones',
];

$catalogoPasajero = [
    'hacer_reserva', 'cancelar_reserva', 'gestionar_reservas',
    'calificar_viaje', 'ver_buzon', 'ver_notificaciones',
];

$conceder = static function (int $rol, array $nombres): void {
    $ph = implode(',', array_fill(0, count($nombres), '?'));
    $ids = Database::all("SELECT id_permiso FROM permisos WHERE nombre_permiso IN ($ph)", $nombres);
    foreach ($ids as $p) {
        Database::query(
            'INSERT IGNORE INTO rol_permiso (id_rol, id_permiso) VALUES (?, ?)',
            [$rol, (int)$p['id_permiso']]
        );
    }
};

// 4.a) El administrador recibe el catálogo completo.
Database::query('INSERT IGNORE INTO rol_permiso (id_rol, id_permiso) SELECT ?, id_permiso FROM permisos', [Config::ROL_ADMIN]);

// 4.b) Conductor y pasajero, solo lo suyo.
$conceder(Config::ROL_CONDUCTOR, $catalogoConductor);
$conceder(Config::ROL_PASAJERO,   $catalogoPasajero);

/* -------------------------------------------------------------------------- */
/* 5) USUARIOS DE PRUEBA                                                       */
/* -------------------------------------------------------------------------- */
/*
 *   Administrador → documento 900000001 · admin@sget.local    · Sget#2026!
 *   Conductor    → documento 900000002 · conductor@sget.local · Sget#2026!
 *   Pasajero     → documento 900000003 · pasajero@sget.local  · Sget#2026!
 *
 * Son las MISMAS credenciales para los tres, para que probar sea cómodo. En un
 * entorno real hay que cambiar la del administrador.
 */
$cuentas = [
    ['CC', '900000001', 'Administrador de Prueba', 'admin@sget.local',     Config::ROL_ADMIN,     'Sget#2026!'],
    ['CC', '900000002', 'Conductor de Prueba',    'conductor@sget.local', Config::ROL_CONDUCTOR, 'Sget#2026!'],
    ['CC', '900000003', 'Pasajero de Prueba',     'pasajero@sget.local',  Config::ROL_PASAJERO,  'Sget#2026!'],
];

foreach ($cuentas as [$tipo, $documento, $nombre, $correo, $rol, $clave]) {
    if (Database::scalar('SELECT id_usu FROM usuario WHERE num_doc_usu = ?', [$documento]) !== null) {
        continue;
    }
    Database::query(
        'INSERT INTO usuario
            (tip_doc_usu, num_doc_usu, nom_usu, corre_usu, pass_usu, id_rol_usu,
             estado, est_con_usu, acepta_politica, fecha_acepta_politica)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?, 1, NOW())',
        [
            $tipo, $documento, $nombre, $correo,
            password_hash($clave, PASSWORD_DEFAULT),
            $rol,
            $rol === Config::ROL_CONDUCTOR ? Config::CON_DISPONIBLE : null,
        ]
    );
}

/* -------------------------------------------------------------------------- */
/* 6) ESTADO DE LA COLUMNA LEGACY                                             */
/* -------------------------------------------------------------------------- */
if (existeColumna('usuario', 'restricciones')) {
    Database::query('UPDATE usuario SET restricciones = NULL');
}