<?php
/**
 * services/NotificacionService.php
 * -----------------------------------------------------------------------------
 * MODULO: NOTIFICACIONES
 * -----------------------------------------------------------------------------
 * Canal de avisos al pasajero. Cuando un viaje se cancela, cada pasajero que
 * tenia reserva activa recibe un mensaje con el motivo, la anotacion
 * obligatoria y la fecha limite para reprogramar.
 *
 * Los mensajes quedan persistidos (tabla notificacion) y ademas se publica un
 * aviso en el buzon del pasajero; si el proyecto tiene salida de correo/sms,
 * este es el unico punto donde hay que engancharla.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class NotificacionService
{
    public const TIPO_CANCELACION = 'viaje_cancelado';
    public const TIPO_GENERAL     = 'aviso';

    public const ESTADO_NO_LEIDA = 0;
    public const ESTADO_LEIDA    = 1;

    /* ------------------------------------------------------------------ */
    /* Escritura                                                           */
    /* ------------------------------------------------------------------ */

    public static function enviar(int $idUsuario, string $tipo, string $titulo, string $cuerpo, ?int $idViaje = null): int
    {
        return Database::insert(
            "INSERT INTO notificacion (id_usu, tipo, titulo, cuerpo, id_via, leida, fec_envio)
             VALUES (?, ?, ?, ?, ?, 0, NOW())",
            [$idUsuario, $tipo, $titulo, $cuerpo, $idViaje]
        );
    }

    /**
     * Pasajeros que tenían reserva activa ANTES de la cancelación.
     *
     * IMPORTANTE: se calcula antes de que las reservas pasen a 'Cancelada'.
     * Si se hiciera después, la consulta no encontraría a nadie y los
     * pasajeros se quedarían sin aviso (que es justamente el fallo que hay
     * que evitar).
     *
     * @return array<int, array{contenido:string}>
     */
    public static function pasajerosReservados(int $idViaje): array
    {
        return Database::all(
            "SELECT u.id_usu,
                    CONCAT(COALESCE(NULLIF(u.nom_usu,''), 'Pasajero'), ' (', u.num_doc_usu, ')') AS contenido
               FROM reserva r
               INNER JOIN usuario u ON u.id_usu = r.id_usu_res
              WHERE r.id_via_res = ?
                AND r.estado_pago IN ('Confirmada','Pendiente')
              GROUP BY u.id_usu, contenido",
            [$idViaje]
        );
    }

    /**
     * Notifica a todos los pasajeros con reserva activa de un viaje.
     *
     * @return int numero de pasajeros notificados
     */
    public static function notificarCancelacionViaje(int $idViaje, array $datosViaje, string $anotacion, array $pasajeros = null): int
    {
        $pasajeros = $pasajeros ?? self::pasajerosReservados($idViaje);

        $ruta = $datosViaje['nom_ruta'] ?? 'la ruta del viaje';
        $salida = isset($datosViaje['salida']) && $datosViaje['salida'] !== ''
            ? Fecha::legible($datosViaje['salida'])
            : 'la hora programada';

        $titulo = "Viaje cancelado: {$ruta}";
        $cuerpo = sprintf(
            "Hola %s,\n\n"
          . "Lamentamos informarte que el viaje #%d con destino %s (salida %s) ha sido CANCELADO.\n\n"
          . "Motivo: %s\n"
          . "Anotación del operador: %s\n\n"
          . "Tu reserva fue marcada automáticamente como cancelada y no se realizó ningún cobro. "
          . "Ingresa al sistema para reservar una nueva salida o comunícate con la administración para reprogramar.\n\n"
          . "— Equipo SGET",
            $datosViaje['conductor'] ?? 'pasajero',
            $idViaje,
            $ruta,
            $salida,
            $datosViaje['motivo'] ?? 'No especificado',
            $anotacion
        );

        $n = 0;
        foreach ($pasajeros as $p) {
            self::enviar((int)$p['id_usu'], self::TIPO_CANCELACION, $titulo, $cuerpo, $idViaje);
            $n++;
        }

        return $n;
    }

    /* ------------------------------------------------------------------ */
    /* Lectura                                                             */
    /* ------------------------------------------------------------------ */

    public static function bandeja(int $idUsuario, int $limite = 30): array
    {
        return Database::all(
            "SELECT * FROM notificacion
              WHERE id_usu = ?
              ORDER BY leida ASC, fec_envio DESC
              LIMIT " . (int)$limite,
            [$idUsuario]
        );
    }

    public static function noLeidas(int $idUsuario): int
    {
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM notificacion WHERE id_usu = ? AND leida = 0",
            [$idUsuario]
        );
    }

    public static function marcarLeida(int $idNotificacion, int $idUsuario): void
    {
        Database::query(
            "UPDATE notificacion SET leida = 1 WHERE id_not = ? AND id_usu = ?",
            [$idNotificacion, $idUsuario]
        );
    }

    public static function marcarTodasLeidas(int $idUsuario): int
    {
        $r = Database::query("UPDATE notificacion SET leida = 1 WHERE id_usu = ? AND leida = 0", [$idUsuario]);
        return $r->rowCount();
    }

    /* ------------------------------------------------------------------ */

    public static function claseTipo(string $tipo): string
    {
        return $tipo === self::TIPO_CANCELACION
            ? 'sget-badge sget-badge--error'
            : 'sget-badge sget-badge--info';
    }
}
