<?php
/**
 * DESC: Endurecer la estructura de la base de datos (tipos correctos, ENUMs, índices y tabla de notificaciones)
 *
 * Ejecuta DESPUÉS de 002_corregir_datos_por_defecto.php, porque el motor ya
 * puede estar en modo estricto: cualquier valor fuera de rango ahora es ERROR
 * en lugar de un "valor por defecto" silencioso.
 */
declare(strict_types=1);

$migrar = function (): void {
    $log = fn(string $m) => imprimir('      ' . $m);

    $ddl = [
        // ------------------------------------------------------------------
        // RUTAS
        // ------------------------------------------------------------------
        'RUTAS · origen/destino obligatorios' =>
            "ALTER TABLE rutas MODIFY COLUMN ori_rut VARCHAR(100) NOT NULL DEFAULT 'Por definir'",

        'RUTAS · destino obligatorio' =>
            "ALTER TABLE rutas MODIFY COLUMN des_rut VARCHAR(100) NOT NULL DEFAULT 'Por definir'",

        'RUTAS · distancia y tarifa sin NULL' =>
            "ALTER TABLE rutas MODIFY COLUMN dis_rut DECIMAL(10,2) NOT NULL DEFAULT 0.00",

        'RUTAS · tarifa como DECIMAL (no FLOAT)' =>
            "ALTER TABLE rutas MODIFY COLUMN val_rut DECIMAL(12,2) NOT NULL DEFAULT 0.00",

        'RUTAS · hora de salida por defecto' =>
            "ALTER TABLE rutas ADD COLUMN IF NOT EXISTS hora_salida TIME NULL DEFAULT NULL",

        'RUTAS · estado (activa / suspendida)' =>
            "ALTER TABLE rutas ADD COLUMN IF NOT EXISTS estado TINYINT(1) NOT NULL DEFAULT 1",

        // ------------------------------------------------------------------
        // VIAJES  (aquí desaparece la fecha cero)
        // ------------------------------------------------------------------
        'VIAJES · fec_via pasa a DATE' =>
            "ALTER TABLE viaje MODIFY COLUMN fec_via DATE NOT NULL",

        'VIAJES · hor_sal_via pasa a TIME' =>
            "ALTER TABLE viaje MODIFY COLUMN hor_sal_via TIME NOT NULL",

        'VIAJES · hor_lleg_via pasa a TIME' =>
            "ALTER TABLE viaje MODIFY COLUMN hor_lleg_via TIME NULL DEFAULT NULL",

        'VIAJES · est_via como ENUM controlado' =>
            "ALTER TABLE viaje MODIFY COLUMN est_via ENUM('Programado','En curso','Finalizado','Cancelado') NOT NULL DEFAULT 'Programado'",

        'VIAJES · indicador de salida' =>
            "ALTER TABLE viaje ADD COLUMN IF NOT EXISTS salio TINYINT(1) NOT NULL DEFAULT 0",

        'VIAJES · tarifa y cupos sin NULL' =>
            "ALTER TABLE viaje MODIFY COLUMN val_via DECIMAL(12,2) NOT NULL DEFAULT 0.00",

        'VIAJES · cupos sin NULL' =>
            "ALTER TABLE viaje MODIFY COLUMN cup_tot INT(11) NOT NULL DEFAULT 0",

        'VIAJES · cupos disponibles sin NULL' =>
            "ALTER TABLE viaje MODIFY COLUMN cup_dis INT(11) NOT NULL DEFAULT 0",

        'VIAJES · motivo de cancelación' =>
            "ALTER TABLE viaje ADD COLUMN IF NOT EXISTS motivo_cancelacion VARCHAR(60) NULL DEFAULT NULL",

        'VIAJES · anotación obligatoria de cancelación' =>
            "ALTER TABLE viaje ADD COLUMN IF NOT EXISTS anotacion_cancelacion TEXT NULL DEFAULT NULL",

        'VIAJES · quién canceló' =>
            "ALTER TABLE viaje ADD COLUMN IF NOT EXISTS cancelado_por INT(11) NULL DEFAULT NULL",

        'VIAJES · cuándo se canceló' =>
            "ALTER TABLE viaje ADD COLUMN IF NOT EXISTS fec_cancelacion DATETIME NULL DEFAULT NULL",

        'VIAJES · índice de consulta de salidas' =>
            "CREATE INDEX idx_viaje_salida ON viaje (est_via, fec_via, hor_sal_via)",

        // ------------------------------------------------------------------
        // RESERVAS
        // ------------------------------------------------------------------
        'RESERVAS · estado como ENUM' =>
            "ALTER TABLE reserva MODIFY COLUMN estado_pago ENUM('Pendiente','Confirmada','Cancelada') NOT NULL DEFAULT 'Pendiente'",

        'RESERVAS · método de pago obligatorio' =>
            "ALTER TABLE reserva MODIFY COLUMN metodo_pago VARCHAR(50) NOT NULL DEFAULT 'Por definir'",

        'RESERVAS · fecha de pago coherente' =>
            "UPDATE reserva SET fecha_pago = NULL WHERE fecha_pago IS NULL OR estado_pago <> 'Confirmada'",

        // ------------------------------------------------------------------
        // USUARIOS
        // ------------------------------------------------------------------
        'USUARIOS · estado NOT NULL (fin de usuarios invisibles)' =>
            "ALTER TABLE usuario MODIFY COLUMN estado TINYINT(1) NOT NULL DEFAULT 1",

        'USUARIOS · longitudes de campo correctas' =>
            "ALTER TABLE usuario MODIFY COLUMN num_doc_usu VARCHAR(20) NOT NULL, MODIFY COLUMN tip_doc_usu VARCHAR(10) NOT NULL, MODIFY COLUMN nom_usu VARCHAR(100) NOT NULL, MODIFY COLUMN corre_usu VARCHAR(100) NOT NULL, MODIFY COLUMN pass_usu VARCHAR(200) NOT NULL",

        'USUARIOS · disponibilidad del conductor documentada' =>
            "ALTER TABLE usuario MODIFY COLUMN est_con_usu INT(11) NULL DEFAULT NULL COMMENT '1 = Disponible, 0 = Ocupado. NULL para otros roles.'",

        'USUARIOS · elimina columna heredada sin uso' =>
            "ALTER TABLE usuario DROP COLUMN IF EXISTS restricciones",

        // ------------------------------------------------------------------
        // VEHICULOS
        // ------------------------------------------------------------------
        'VEHICULO · estado NOT NULL 1/0' =>
            "ALTER TABLE vehiculo MODIFY COLUMN est_veh TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Disponible, 0 = Fuera de servicio'",

        'VEHICULO · capacidad NOT NULL' =>
            "ALTER TABLE vehiculo MODIFY COLUMN cap_veh INT(11) NOT NULL DEFAULT 0",

        'VEHICULO · modelo obligatorio' =>
            "ALTER TABLE vehiculo MODIFY COLUMN mode_veh VARCHAR(50) NOT NULL DEFAULT 'Sin modelo'",

        'VEHICULO · placa con longitud correcta' =>
            "ALTER TABLE vehiculo MODIFY COLUMN pla_veh VARCHAR(10) NOT NULL",

        // ------------------------------------------------------------------
        // ASIGNACION / CALIFICACION / REPORTES
        // ------------------------------------------------------------------
        'ASIGNACION · fecha sin NULL' =>
            "ALTER TABLE asignacion MODIFY COLUMN fec_asig DATE NULL DEFAULT NULL",

        'CALIFICACION · comentario opcional' =>
            "ALTER TABLE calificacion MODIFY COLUMN com_cal VARCHAR(255) NULL DEFAULT NULL",

        'REPORTES · estado controlado' =>
            "ALTER TABLE reportes_pasajeros MODIFY COLUMN estado VARCHAR(20) NOT NULL DEFAULT 'Abierto'",

        // ------------------------------------------------------------------
        // TABLA NUEVA: NOTIFICACIONES
        // ------------------------------------------------------------------
        'NOTIFICACIONES · tabla de avisos al pasajero' => <<<'SQL'
CREATE TABLE IF NOT EXISTS notificacion (
    id_not     INT(11)      NOT NULL AUTO_INCREMENT,
    id_usu     INT(11)      NOT NULL,
    tipo       VARCHAR(40)  NOT NULL DEFAULT 'aviso',
    titulo     VARCHAR(150) NOT NULL,
    cuerpo     TEXT         NOT NULL,
    id_via     INT(11)      NULL DEFAULT NULL,
    leida      TINYINT(1)   NOT NULL DEFAULT 0,
    fec_envio  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_not),
    KEY fk_notif_usuario (id_usu),
    KEY fk_notif_viaje   (id_via),
    KEY idx_notif_buzon  (id_usu, leida, fec_envio),
    CONSTRAINT fk_notif_usuario FOREIGN KEY (id_usu) REFERENCES usuario(id_usu) ON DELETE CASCADE,
    CONSTRAINT fk_notif_viaje   FOREIGN KEY (id_via) REFERENCES viaje(id_via)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    ];

    foreach ($ddl as $descripcion => $sql) {
        try {
            Database::query($sql);
            $log('[OK] ' . $descripcion);
        } catch (Throwable $e) {
            // 1060 = columna ya existe, 1061 = índice ya existe, 1091 = columna a eliminar no existe
            if (in_array($e->errorInfo[1] ?? 0, [1060, 1061, 1091, 1062], true)) {
                $log('[--] ' . $descripcion . ' (ya estaba aplicado)');
            } else {
                $log('[!!] ' . $descripcion . ' :: ' . $e->getMessage());
                throw $e;
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Permisos nuevos                                                    */
    /* ------------------------------------------------------------------ */
    $log('PERMISOS · registrando las acciones nuevas...');
    $nuevos = [
        ['suspender_ruta',      'rutas',    Config::ROL_ADMIN, 'Permite suspender o reactivar una ruta'],
        ['suspender_usuario',   'usuario',  Config::ROL_ADMIN, 'Permite suspender o reactivar una cuenta'],
        ['suspender_vehiculo',  'vehiculo', Config::ROL_ADMIN, 'Permite poner un vehículo fuera de servicio'],
        ['cancelar_viaje_admin','viaje',    Config::ROL_ADMIN, 'Permite cancelar un viaje con anotación obligatoria'],
        ['ver_notificaciones',  'pasajero', Config::ROL_PASAJERO, 'Permite ver el buzón de notificaciones'],
    ];
    foreach ($nuevos as [$clave, $modulo, $rol, $desc]) {
        Database::query(
            "INSERT INTO permisos (nombre_permiso, modulo, id_rol, descripcion) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE modulo = VALUES(modulo), id_rol = VALUES(id_rol), descripcion = VALUES(descripcion)",
            [$clave, $modulo, $rol, $desc]
        );
    }
    $log('PERMISOS · ' . count($nuevos) . ' permisos verificados.');

    /* ------------------------------------------------------------------ */
    /* Permisos existentes sin rol                                        */
    /* ------------------------------------------------------------------ */
    Database::query("UPDATE permisos SET id_rol = 1 WHERE modulo IN ('rutas','vehiculo','asignacion','usuario','reportes_pasajeros')");
    Database::query("UPDATE permisos SET id_rol = 3 WHERE modulo = 'reserva'");
    Database::query("UPDATE permisos SET id_rol = 2 WHERE modulo = 'viaje' AND nombre_permiso IN ('cancelar_viaje','redirigir_viaje')");
    Database::query("UPDATE permisos SET id_rol = 1 WHERE modulo = 'viaje' AND nombre_permiso = 'cancelar_viaje_admin'");
    $log('PERMISOS · roles consistentes.');
};
