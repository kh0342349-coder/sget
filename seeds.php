<?php
/**
 * seeds.php · Limpieza y reinicio de datos de prueba para SGET
 * -------------------------------------------------------------------------
 * Uso:
 *     php seeds.php
 *
 * Hace:
 *   1) Limpia las tablas secundarias (reserva, calificacion, asignacion,
 *      notificacion, sget_logs_auditoria, reportes_pasajeros, etc.).
 *   2) Elimina todos los usuarios y crea solo los 3 de prueba con
 *      contraseñas BCRYPT.
 *   3) Restablece AUTO_INCREMENT para que las pruebas inicien desde 1.
 */
declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';

try {
    $pdo = Database::pdo();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    /* -------------------------------------------------------------------- */
    /* 1) Tablas secundarias / dependientes                                 */
    /* -------------------------------------------------------------------- */
    $tablasSecundarias = [
        'calificacion',
        'reserva',
        'notificacion',
        'programacion',
        'asignacion',
        'sget_logs_auditoria',
        'reportes_pasajeros',
        'restricciones',
        'usuario_permiso_denegado',
        'usuario_permisos',
    ];

    foreach ($tablasSecundarias as $t) {
        try {
            $pdo->exec("TRUNCATE TABLE `{$t}`");
            echo "   [TRUNCATE] {$t}\n";
        } catch (Throwable $e) {
            echo "   [TRUNCATE SKIP] {$t} (" . $e->getMessage() . ")\n";
        }
    }

    /* -------------------------------------------------------------------- */
    /* 2) Eliminar usuarios existentes                                      */
    /* -------------------------------------------------------------------- */
    $pdo->exec("DELETE FROM usuario");
    echo "   [DELETE] usuario (todos los registros)\n";

    /* -------------------------------------------------------------------- */
    /* 3) Crear los 3 usuarios de prueba                                    */
    /* -------------------------------------------------------------------- */
    $contraseñas = [
        'Administradores' => '123456789',
        'Conductores'    => '123456789',
        'Pasajeros'      => '123456789',
    ];

    $hashAdmin     = Password::hash('123456789');
    $hashConductor = Password::hash('123456789');
    $hashPasajero  = Password::hash('123456789');

    $usuarios = [
        [
            'num_doc_usu' => '100001',
            'tip_doc_usu' => 'CC',
            'nom_usu'     => 'Administrador SGET',
            'corre_usu'   => 'admin@sget.local',
            'tel_usu'     => '3000000000',
            'id_rol_usu'  => Config::ROL_ADMIN,
            'pass_usu'    => $hashAdmin,
            'estado'      => Config::USU_ACTIVO,
            'est_con_usu' => null,
        ],
        [
            'num_doc_usu' => '100002',
            'tip_doc_usu' => 'CC',
            'nom_usu'     => 'Conductor de Prueba',
            'corre_usu'   => 'conductor@sget.local',
            'tel_usu'     => '3000000001',
            'id_rol_usu'  => Config::ROL_CONDUCTOR,
            'pass_usu'    => $hashConductor,
            'estado'      => Config::USU_ACTIVO,
            'est_con_usu' => Config::CON_DISPONIBLE,
        ],
        [
            'num_doc_usu' => '100003',
            'tip_doc_usu' => 'CC',
            'nom_usu'     => 'Pasajero de Prueba',
            'corre_usu'   => 'pasajero@sget.local',
            'tel_usu'     => '3000000002',
            'id_rol_usu'  => Config::ROL_PASAJERO,
            'pass_usu'    => $hashPasajero,
            'estado'      => Config::USU_ACTIVO,
            'est_con_usu' => null,
        ],
    ];

    foreach ($usuarios as $u) {
        $pdo->prepare("INSERT INTO usuario
            (tip_doc_usu, num_doc_usu, nom_usu, corre_usu, tel_usu, id_rol_usu, pass_usu, estado, est_con_usu)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $u['tip_doc_usu'],
                $u['num_doc_usu'],
                $u['nom_usu'],
                $u['corre_usu'],
                $u['tel_usu'],
                $u['id_rol_usu'],
                $u['pass_usu'],
                $u['estado'],
                $u['est_con_usu'],
            ]);
        echo "   [INSERT] usuario {$u['nom_usu']} (doc: {$u['num_doc_usu']}, rol: {$u['id_rol_usu']})\n";
    }

    /* -------------------------------------------------------------------- */
    /* 4) Restablecer AUTO_INCREMENT                                         */
    /* -------------------------------------------------------------------- */
    $tablasAuto = [
        'reserva' => 1,
        'calificacion' => 1,
        'asignacion' => 1,
        'programacion' => 1,
        'notificacion' => 1,
        'sget_logs_auditoria' => 1,
        'reportes_pasajeros' => 1,
        'restricciones' => 1,
        'anuncio' => 1,
        'viaje' => 1,
        'vehiculo' => 1,
        'usuario' => 1,
    ];

    // Algunos AUTO_INCREMENT ya empiezan en 1 por defecto; se dejan explícitos
    // para que el seeder sea idempotente y repetible.
    foreach ($tablasAuto as $t => $inicio) {
        try {
            $pdo->exec("ALTER TABLE `{$t}` AUTO_INCREMENT = {$inicio}");
            echo "   [AUTO_INC] {$t} = {$inicio}\n";
        } catch (Throwable $e) {
            echo "   [AUTO_INC SKIP] {$t}\n";
        }
    }

    /* -------------------------------------------------------------------- */
    /* 5) Finalizar                                                         */
    /* -------------------------------------------------------------------- */
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    echo "\n========================================\n";
    echo "Limpieza completada.\n";
    echo "Usuarios de prueba creados con BCRYPT (password_hash).\n";
    echo "Contraseña: 123456789\n";
    echo "========================================\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
/* Nota adicional: si TRUNCATE falla (restricciones o tabla inexistente), 
   los datos ya fueron limpiados por el archivo database-clean.sql 
   ejecutado previamente. */
