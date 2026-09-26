<?php
/**
 * helpers/Logger.php
 * -----------------------------------------------------------------------------
 * AUDITORÍA
 * -----------------------------------------------------------------------------
 * Acepta tanto un PDO (arquitectura nueva) como un mysqli (código heredado),
 * para que la traza de auditoría no se pierda durante la migración gradual.
 *
 * Si la tabla de auditoría no existe, el error se registra en el log del
 * servidor pero NUNCA interrumpe la operación de negocio: un fallo de logging
 * no debe impedir guardar una ruta o cancelar un viaje.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class Logger
{
    private static bool $advertido = false;

    public static function registrar(
        $conexion,
        string $accion,
        string $descripcion,
        $id_usu = null,
        $nom_usu = null,
        $nom_rol = null
    ): void {
        if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
            session_start();
        }

        $id     = $id_usu ?? ($_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? null);
        $nombre = $nom_usu ?? ($_SESSION['nombre_usuario'] ?? $_SESSION['nom_usu'] ?? 'Anónimo / Sistema');
        $rol    = $nom_rol ?? ($_SESSION['nom_rol'] ?? 'Sin Rol');

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido'), 0, 255);

        try {
            if ($conexion instanceof PDO) {
                $stmt = $conexion->prepare(
                    "INSERT INTO sget_logs_auditoria
                        (id_usu, nom_usu_log, nom_rol_log, accion, descripcion, ip_origen, user_agent)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$id ?: null, $nombre, (string)$rol, $accion, $descripcion, $ip, $ua]);
            } elseif ($conexion instanceof mysqli) {
                $stmt = $conexion->prepare(
                    "INSERT INTO sget_logs_auditoria
                        (id_usu, nom_usu_log, nom_rol_log, accion, descripcion, ip_origen, user_agent)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("issssss", $id, $nombre, $rol, $accion, $descripcion, $ip, $ua);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            error_log('[SGET][Logger] ' . $e->getMessage());
            if (!self::$advertido) {
                self::$advertido = true;
                error_log('[SGET][Logger] La auditoría no está disponible. '
                    . 'Ejecuta: php migraciones/migrar.php  (o revisa la tabla sget_logs_auditoria).');
            }
        }
    }
}
