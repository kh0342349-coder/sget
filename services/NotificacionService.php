<?php
/**
 * services/NotificacionService.php
 * -----------------------------------------------------------------------------
 * MÓDULO: NOTIFICACIONES (buzón interno de avisos)
 * -----------------------------------------------------------------------------
 * POR QUÉ EXISTE
 *   El sistema tiene un buzón (tabla `notificacion`) que se muestra en la
 *   cabecera de TODOS los módulos. Antes solo se usaba en un caso: avisar a los
 *   pasajeros cuando se cancelaba un viaje. Por eso el buzón de los conductores
 *   estaba siempre vacío y para los pasajeros solo sonaba una vez.
 *
 * AHORA es un canal de avisos real, con un generador por cada evento del
 * negocio que tiene un destinatario obvious:
 *
 *     evento                        → a quién se avisa
 *     ----------------------------------------------------------------------
 *     viaje cancelado               → pasajeros reservados + conductor
 *     viaje asignado                → conductor
 *     viaje en curso                → pasajeros reservados
 *     viaje finalizado              → conductor
 *     reserva confirmada / recaudo  → pasajero
 *     reserva cancelada             → pasajero
 *     calificación recibida         → conductor
 *     recordatorio de salida        → pasajero y conductor (1 por viaje)
 *     aviso general / difusión      → el rol o usuario que elija el admin
 *
 * DISEÑO
 *   · Escribir una notificación NUNCA puede romper la operación que la originó:
 *     todo se envuelve y los fallos solo se registran en el log.
 *   · Un mismo evento no duplica avisos: hay "una vez por" con una firma.
 *   · `firma` es una clave de unicidad lógica (ej. 'recordatorio:12:7'); sirve
 *     para no repetir el mismo aviso aunque se dispare dos veces.
 *
 * Write path: este servicio (los eventos lo llaman).
 * Read path:  este servicio + api/index.php?modulo=notificacion.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class NotificacionService
{
    /* ================================================================== */
    /* Tipos de aviso                                                      */
    /* ================================================================== */
    /* Un tipo nuevo obliga a tocar: la lista de aquí, el icono/clase que
       devuelve self::presentacion() y la traducción de la etiqueta.        */

    public const TIPO_GENERAL     = 'aviso';                  // difusión del admin
    public const TIPO_SISTEMA     = 'sistema';                // avisos técnicos
    public const TIPO_CANCELACION = 'viaje_cancelado';
    public const TIPO_ASIGNACION  = 'viaje_asignado';
    public const TIPO_SALIDA      = 'viaje_en_curso';
    public const TIPO_FINALIZADO  = 'viaje_finalizado';
    public const TIPO_RESERVA     = 'reserva_confirmada';
    public const TIPO_RECAUDO     = 'pago_registrado';
    public const TIPO_RECORDATORIO= 'recordatorio_salida';
    public const TIPO_CALIFICACION= 'calificacion';
    public const TIPO_NO_PRESENTE = 'no_presente';
    public const TIPO_VIAJE_PERDIDO= 'viaje_perdido';

    public const ESTADO_NO_LEIDA = 0;
    public const ESTADO_LEIDA    = 1;

    /** Metadatos de cada tipo: icono, color y etiqueta legible. */
    public const TIPOS = [
        self::TIPO_CANCELACION  => ['icono' => 'fa-ban',                 'tono' => 'rojo',    'etiqueta' => 'Cancelación'],
        self::TIPO_ASIGNACION   => ['icono' => 'fa-route',               'tono' => 'azul',    'etiqueta' => 'Asignación'],
        self::TIPO_SALIDA       => ['icono' => 'fa-play',                'tono' => 'emerald', 'etiqueta' => 'Salida'],
        self::TIPO_FINALIZADO   => ['icono' => 'fa-flag-checkered',     'tono' => 'emerald', 'etiqueta' => 'Finalizado'],
        self::TIPO_RESERVA      => ['icono' => 'fa-ticket',              'tono' => 'azul',    'etiqueta' => 'Reserva'],
        self::TIPO_RECAUDO      => ['icono' => 'fa-cash',                'tono' => 'emerald', 'etiqueta' => 'Pago'],
        self::TIPO_RECORDATORIO => ['icono' => 'fa-clock',               'tono' => 'ambars',  'etiqueta' => 'Recordatorio'],
        self::TIPO_CALIFICACION => ['icono' => 'fa-star',                'tono' => 'ambars',  'etiqueta' => 'Calificación'],
        self::TIPO_NO_PRESENTE  => ['icono' => 'fa-user-slash',         'tono' => 'rojo',    'etiqueta' => 'No se presentó'],
        self::TIPO_VIAJE_PERDIDO => ['icono' => 'fa-triangle-exclamation', 'tono' => 'rojo',  'etiqueta' => 'Viaje perdido'],
        self::TIPO_GENERAL      => ['icono' => 'fa-bullhorn',            'tono' => 'morado',  'etiqueta' => 'Comunicado'],
        self::TIPO_SISTEMA      => ['icono' => 'fa-circle-info',         'tono' => 'azul',    'etiqueta' => 'Sistema'],
    ];

    /* ================================================================== */
    /* Escritura                                                           */
    /* ================================================================== */

    /**
     * Envía un aviso. Nunca lanza: si algo falla, lo registra y sigue.
     *
     * @param string|null $firma  Clave de unicidad lógica. Si ya existe un
     *                            aviso con esa firma para el usuario, no se
     *                            vuelve a enviar (evita duplicados).
     * @return int  id de la notificación creada, o 0 si no se creó.
     */
    public static function enviar(
        int $idUsuario,
        string $tipo,
        string $titulo,
        string $cuerpo,
        ?int $idViaje = null,
        ?string $firma = null
    ): int {
        if ($idUsuario <= 0 || trim($titulo) === '') return 0;

        try {
            if ($firma !== null && $firma !== '' && self::existeFirma($idUsuario, $firma)) {
                return 0;
            }

            return Database::insert(
                "INSERT INTO notificacion (id_usu, tipo, titulo, cuerpo, id_via, firma, leida, fec_envio)
                 VALUES (?, ?, ?, ?, ?, ?, 0, NOW())",
                [
                    $idUsuario,
                    self::tipoValido($tipo),
                    $titulo,
                    $cuerpo,
                    $idViaje,
                    ($firma === null || $firma === '') ? null : substr($firma, 0, 160),
                ]
            );
        } catch (Throwable $e) {
            // Índice único (id_usu, firma): si dos peticiones del mismo evento
            // llegan a la vez, la segunda choca contra la primera. No es un error.
            if (self::esFirmaDuplicada($e)) return 0;
            error_log('[SGET][NotificacionService::enviar] ' . $e->getMessage());
            return 0;
        }
    }

    /** Envía el mismo aviso a varios usuarios y devuelve cuántos lo recibieron. */
    public static function enviarVarios(array $idsUsuarios, string $tipo, string $titulo, string $cuerpo, ?int $idViaje = null): int
    {
        $n = 0;
        foreach (array_unique(array_map('intval', $idsUsuarios)) as $id) {
            if ($id > 0 && self::enviar($id, $tipo, $titulo, $cuerpo, $idViaje) > 0) $n++;
        }
        return $n;
    }

    /* ================================================================== */
    /* Difusión (admin → usuarios)                                         */
    /* ================================================================== */

    /**
     * Aviso general a un conjunto de destinatarios.
     *
     * @param array $destinatarios  ['rol' => 3] y/o ['usuarios' => [5, 7]]
     * @return array{enviados:int, destinatarios:int}
     */
    public static function difundir(array $destinatarios, string $titulo, string $cuerpo, string $tipo = self::TIPO_GENERAL): array
    {
        $usuarios = self::resolverDestinatarios($destinatarios);

        $enviados = 0;
        foreach ($usuarios as $id) {
            if (self::enviar($id, $tipo, $titulo, $cuerpo) > 0) $enviados++;
        }

        Logger::registrar(Database::pdo(), 'DIFUSION_AVISO',
            sprintf('Comunicado «%s» enviado a %d usuario(s) por %s.', $titulo, $enviados, Auth::nombre()));

        return ['enviados' => $enviados, 'destinatarios' => count($usuarios)];
    }

    /**
     * Traduce el filtro del admin a una lista de ids de usuario.
     * Un destinatario excluido tiene prioridad sobre el rol ("todos menos X").
     */
    public static function resolverDestinatarios(array $filtro): array
    {
        $rol       = (int)($filtro['rol'] ?? 0);
        $excluir   = array_map('intval', (array)($filtro['excluir'] ?? []));
        $usuarios  = array_map('intval', (array)($filtro['usuarios'] ?? []));

        if ($usuarios) {
            $base = $usuarios;
        } elseif ($rol > 0) {
            $base = array_map(static fn($f) => (int)$f['id_usu'], Database::all(
                "SELECT id_usu FROM usuario WHERE id_rol_usu = ? AND estado = ? ORDER BY id_usu",
                [$rol, Config::USU_ACTIVO]
            ));
        } else {
            $base = array_map(static fn($f) => (int)$f['id_usu'], Database::all(
                "SELECT id_usu FROM usuario WHERE estado = ? ORDER BY id_usu",
                [Config::USU_ACTIVO]
            ));
        }

        // El admin no se avisa a sí mismo: los avisos son para la operación.
        $yo = Auth::id();
        if ($yo > 0) $excluir[] = $yo;

        return array_values(array_diff(array_unique($base), array_filter($excluir)));
    }

    /* ================================================================== */
    /* Eventos de negocio (los llama el resto del sistema)                 */
    /* ================================================================== */

    /**
     * Pasajeros que tenían reserva activa ANTES de la cancelación.
     *
     * IMPORTANTE: se calcula antes de que las reservas pasen a 'Cancelada'.
     * Si se hiciera después, la consulta no encontraría a nadie y los
     * pasajeros se quedarían sin aviso (que es justamente el fallo que hay
     * que evitar).
     *
     * @return array<int, array{id_usu:int, contenido:string}>
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
    public static function notificarCancelacionViaje(int $idViaje, array $datosViaje, string $anotacion, ?array $pasajeros = null): int
    {
        $pasajeros = $pasajeros ?? self::pasajerosReservados($idViaje);

        $ruta    = $datosViaje['nom_ruta'] ?? 'la ruta del viaje';
        $salida  = !empty($datosViaje['salida']) ? Fecha::legible($datosViaje['salida']) : 'la hora programada';
        $motivo  = $datosViaje['motivo'] ?? 'No especificado';
        $anotacion = $anotacion !== '' ? $anotacion : 'Sin anotación adicional.';

        $titulo = "Viaje cancelado: {$ruta}";
        $cuerpo = sprintf(
            "Lamentamos informarte que el viaje #%d con destino %s (salida %s) ha sido CANCELADO.\n\n"
          . "Motivo: %s\n"
          . "Anotación del operador: %s\n\n"
          . "Tu reserva fue marcada automáticamente como cancelada y no se realizó ningún cobro. "
          . "Ingresa al sistema para reservar una nueva salida o comunícate con la administración para reprogramar.\n\n"
          . "— Equipo SGET",
            $idViaje, $ruta, $salida, $motivo, $anotacion
        );

        $ids = array_map(static fn($p) => (int)$p['id_usu'], $pasajeros);
        return self::enviarVarios($ids, self::TIPO_CANCELACION, $titulo, $cuerpo, $idViaje);
    }

    /**
     * El conductor también debe saber que se canceló su viaje: era el destinatario
     * que faltaba, y por eso su buzón estaba siempre vacío.
     */
    public static function notificarCancelacionConductor(int $idConductor, int $idViaje, string $ruta, string $salida, string $motivo, string $anotacion): void
    {
        if ($idConductor <= 0) return;

        self::enviar($idConductor, self::TIPO_CANCELACION, "Viaje cancelado: {$ruta}", sprintf(
            "El viaje #%d que tenías asignado ha sido CANCELADO.\n\n"
          . "Ruta: %s\nSalida programada: %s\nMotivo: %s\nAnotación de la administración: %s\n\n"
          . "El sistema liberó automáticamente la unidad y tu disponibilidad. No debas presentarte a la terminal.\n\n"
          . "— Equipo SGET",
            $idViaje, $ruta, $salida !== '' ? $salida : 'por definir', $motivo, $anotacion
        ), $idViaje, 'cancelacion_conductor:' . $idViaje);
    }

    /** El conductor recibe la asignación de un viaje. */
    public static function notificarAsignacionConductor(int $idConductor, int $idViaje, array $datos): void
    {
        if ($idConductor <= 0) return;

        $placa = !empty($datos['placa']) ? $datos['placa'] : 'sin placa asignada';

        self::enviar($idConductor, self::TIPO_ASIGNACION, 'Nuevo viaje asignado: ' . ($datos['ruta'] ?? 'viaje'), sprintf(
            "Se te asignó el viaje #%d.\n\n"
          . "Ruta: %s\nSalida: %s\nUnidad: %s\nCupos: %d\n\n"
          . "Preséntate con tiempo en la terminal y confirma tu llegada desde el módulo de viajes.\n\n"
          . "— Equipo SGET",
            $idViaje,
            trim(($datos['ruta'] ?? '') . ' (' . ($datos['origen'] ?? '?') . ' → ' . ($datos['destino'] ?? '?') . ')'),
            $datos['salida'] ?? 'por definir',
            $placa,
            (int)($datos['cupos'] ?? 0)
        ), $idViaje, 'asignacion:' . $idViaje);
    }

    /** Aviso al pasajero: su reserva quedó confirmada con el recaudo registrado. */
    public static function notificarReservaConfirmada(int $idPasajero, array $reserva): void
    {
        if ($idPasajero <= 0) return;

        $ruta   = $reserva['ruta'] ?? 'la ruta del viaje';
        $salida = !empty($reserva['fec_via'])
            ? Fecha::legible($reserva['fec_via'] . ' ' . ($reserva['hor_sal_via'] ?? ''))
            : 'la hora programada';

        self::enviar($idPasajero, self::TIPO_RESERVA, "Reserva confirmada: {$ruta}", sprintf(
            "Tu reserva #%d quedó CONFIRMADA.\n\n"
          . "Viaje: #%d · %s\nSalida: %s\nValor pagado: $%s\nMétodo: %s\n\n"
          . "Preséntate con tu documento y esta hora. Puedes ver el detalle desde tu historial.\n\n"
          . "— Equipo SGET",
            (int)($reserva['id_res'] ?? 0),
            (int)($reserva['id_via'] ?? 0),
            $ruta,
            $salida,
            number_format((float)($reserva['valor'] ?? 0), 0, ',', '.'),
            $reserva['metodo'] ?? 'no especificado'
        ), (int)($reserva['id_via'] ?? 0), 'reserva:' . (int)($reserva['id_res'] ?? 0));
    }

    /** Aviso al pasajero: su reserva fue cancelada. */
    public static function notificarReservaCancelada(int $idPasajero, int $idReserva, int $idViaje, string $ruta, string $motivo = ''): void
    {
        if ($idPasajero <= 0) return;

        self::enviar($idPasajero, self::TIPO_RESERVA, "Reserva cancelada: {$ruta}", sprintf(
            "Tu reserva #%d del viaje #%d fue cancelada.\n\n"
          . "Motivo: %s\n\n"
          . "No se realizó ningún cobro. Puedes reservar otra salida desde el módulo de viajes.\n\n"
          . "— Equipo SGET",
            $idReserva, $idViaje, $motivo !== '' ? $motivo : 'cancelación de la salida'
        ), $idViaje, 'reserva_cancelada:' . $idReserva);
    }

    /** Los pasajeros reservados quedan avisados de que el viaje arrancó. */
    public static function notificarSalida(int $idViaje, string $ruta, string $salida): void
    {
        $ids = array_map(static fn($p) => (int)$p['id_usu'], Database::all(
            "SELECT DISTINCT u.id_usu
               FROM reserva r
               INNER JOIN usuario u ON u.id_usu = r.id_usu_res
              WHERE r.id_via_res = ? AND r.estado_pago = ?",
            [$idViaje, Config::RES_CONFIRMADA]
        ));

        self::enviarVarios($ids, self::TIPO_SALIDA, "Tu viaje salió: {$ruta}", sprintf(
            "El viaje #%d (%s) ha iniciado su recorrido.\n\n"
          . "Salida: %s\n\n"
          . "Sigue el avance desde el sistema. ¡Buen viaje!\n\n"
          . "— Equipo SGET",
            $idViaje, $ruta, $salida !== '' ? $salida : 'hora programada'
        ), $idViaje);
    }

    /** El conductor recibe la calificación de un pasajero. */
    public static function notificarCalificacion(int $idConductor, int $idViaje, string $ruta, int $puntos, string $comentario = ''): void
    {
        if ($idConductor <= 0) return;

        self::enviar($idConductor, self::TIPO_CALIFICACION, "Nueva calificación: {$ruta}", sprintf(
            "Un pasajero calificó tu servicio en el viaje #%d (%s).\n\n"
          . "Puntaje: %d de 5%s\n\n"
          . "Puedes ver el detalle y el promedio acumulado en el módulo de reseñas.\n\n"
          . "— Equipo SGET",
            $idViaje, $ruta, $puntos,
            $comentario !== '' ? "\nComentario: " . $comentario : ''
        ), $idViaje);
    }

    /**
     * RECORDATORIO DE SALIDA: se genera una sola vez por usuario y viaje.
     *
     * Se dispara desde el propio buzón (acción `recordatorios`) y revisa los
     * viajes del usuario que salen en los próximos minutos, incluidos los que
     * tiene como conductor.
     *
     * CORRECCIÓN IMPORTANTE
     *   Antes un `LEFT JOIN reserva` + `GROUP BY res.estado_pago` devolvía una
     *   fila por cada combinación de estado: un pasajero con 3 puestos (una
     *   pagada y dos pendientes) recibía DOS recordatorios del mismo viaje. Y
     *   el LEFT JOIN traía también las reservas canceladas, que ya no iban en
     *   el bus. Ahora se filtra por `estado_pago` en el WHERE y se agrupa solo
     *   por viaje.
     */
    public static function recordatorios(int $idUsuario, int $minutos = 90): int
    {
        if ($idUsuario <= 0) return 0;

        $desde = date('Y-m-d H:i:s');
        $hasta = date('Y-m-d H:i:s', strtotime('+' . max(10, $minutos) . ' minutes'));

        $filas = Database::all(
            "SELECT v.id_via, v.fec_via, v.hor_sal_via, v.hor_lleg_via,
                    r.nom_rut, r.ori_rut, r.des_rut
               FROM viaje v
               INNER JOIN rutas r ON r.id_rut = v.id_rut_via
              WHERE v.est_via = ?
                AND DATE(v.fec_via) = CURDATE()
                AND TIMESTAMP(v.fec_via, v.hor_sal_via) BETWEEN ? AND ?
                AND (v.id_usu_via = ?
                     OR EXISTS (SELECT 1 FROM reserva res
                                 WHERE res.id_via_res = v.id_via
                                   AND res.id_usu_res = ?
                                   AND res.estado_pago <> ?))
              GROUP BY v.id_via, v.fec_via, v.hor_sal_via, v.hor_lleg_via,
                       r.nom_rut, r.ori_rut, r.des_rut",
            [Config::VIA_PROGRAMADO, $desde, $hasta, $idUsuario, $idUsuario, Config::RES_CANCELADA]
        );

        $n = 0;
        foreach ($filas as $f) {
            $ruta = trim(($f['nom_rut'] ?? '') . ' (' . ($f['ori_rut'] ?? '?') . ' → ' . ($f['des_rut'] ?? '?') . ')');
            $llegada = RutaService::llegadaPrevista(
                $f['fec_via'] . ' ' . $f['hor_sal_via'],
                (int)($f['hor_lleg_via'] ? self::minutosDeHora((string)$f['hor_lleg_via']) : 0)
            );

            if (self::enviar(
                $idUsuario,
                self::TIPO_RECORDATORIO,
                "Salida próxima: {$ruta}",
                sprintf(
                    "Recuerda: el viaje #%d sale a las %s.\n\n"
                  . "Ruta: %s\nLlegada estimada: %s\n\n"
                  . "Preséntate con tiempo y lleva tu documento.\n\n"
                  . "— Equipo SGET",
                    (int)$f['id_via'],
                    substr((string)$f['hor_sal_via'], 0, 5),
                    $ruta,
                    $llegada ?? 'según la ruta'
                ),
                (int)$f['id_via'],
                'recordatorio:' . (int)$f['id_via'] . ':' . $idUsuario
            ) > 0) {
                $n++;
            }
        }

        return $n;
    }

    private static function minutosDeHora(string $hora): int
    {
        $partes = explode(':', $hora);
        return ((int)($partes[0] ?? 0)) * 60 + (int)($partes[1] ?? 0);
    }

    /* ================================================================== */
    /* Lectura                                                             */
    /* ================================================================== */

    /**
     * Buzón del usuario, agrupado por tipo y con filtro opcional.
     * @param array{tipo?:string,soloNoLeidas?:bool,limite?:int} $filtros
     */
    public static function bandeja(int $idUsuario, array $filtros = []): array
    {
        if ($idUsuario <= 0) return [];

        $limite = max(1, min(200, (int)($filtros['limite'] ?? 40)));
        $w = ['id_usu = ?'];
        $p = [$idUsuario];

        if (!empty($filtros['tipo']) && self::tipoValido((string)$filtros['tipo']) !== self::TIPO_GENERAL) {
            $w[] = 'tipo = ?';
            $p[] = (string)$filtros['tipo'];
        }
        if (!empty($filtros['soloNoLeidas'])) {
            $w[] = 'leida = 0';
        }

        $filas = Database::all(
            "SELECT * FROM notificacion
              WHERE " . implode(' AND ', $w) . "
              ORDER BY leida ASC, fec_envio DESC, id_not DESC
              LIMIT {$limite}",
            $p
        );

        // Se enriquece con el estado REAL del viaje: un aviso de "salida" de un
        // viaje que luego se canceló debe verse como caducado, no vigente.
        foreach ($filas as &$f) {
            $f['presentacion'] = self::presentacion((string)$f['tipo']);
            if (!empty($f['id_via'])) {
                $v = Database::one("SELECT est_via, fec_via, hor_sal_via FROM viaje WHERE id_via = ?", [(int)$f['id_via']]);
                $f['viaje_estado'] = $v['est_via'] ?? null;
                $f['obsoleto'] = $v !== null && $v['est_via'] === Config::VIA_CANCELADO
                                 && $f['tipo'] !== self::TIPO_CANCELACION;
            } else {
                $f['viaje_estado'] = null;
                $f['obsoleto'] = false;
            }
        }
        unset($f);

        return $filas;
    }

    public static function noLeidas(int $idUsuario): int
    {
        if ($idUsuario <= 0) return 0;
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM notificacion WHERE id_usu = ? AND leida = 0",
            [$idUsuario]
        );
    }

    public static function marcarLeida(int $idNotificacion, int $idUsuario): bool
    {
        if ($idNotificacion <= 0 || $idUsuario <= 0) return false;
        return Database::query(
            "UPDATE notificacion SET leida = ? WHERE id_not = ? AND id_usu = ?",
            [self::ESTADO_LEIDA, $idNotificacion, $idUsuario]
        )->rowCount() > 0;
    }

    public static function marcarTodasLeidas(int $idUsuario): int
    {
        if ($idUsuario <= 0) return 0;
        return Database::query(
            "UPDATE notificacion SET leida = ? WHERE id_usu = ? AND leida = 0",
            [self::ESTADO_LEIDA, $idUsuario]
        )->rowCount();
    }

    /** Borra un aviso concreto del buzón del usuario. */
    public static function eliminar(int $idNotificacion, int $idUsuario): bool
    {
        if ($idNotificacion <= 0 || $idUsuario <= 0) return false;
        return Database::query(
            "DELETE FROM notificacion WHERE id_not = ? AND id_usu = ?",
            [$idNotificacion, $idUsuario]
        )->rowCount() > 0;
    }

    /** Vacía el buzón del usuario (solo las suyas). */
    public static function vaciar(int $idUsuario): int
    {
        if ($idUsuario <= 0) return 0;
        return Database::query("DELETE FROM notificacion WHERE id_usu = ?", [$idUsuario])->rowCount();
    }

    /* ================================================================== */
    /* Presentación                                                        */
    /* ================================================================== */

    /** Un tipo desconocido se muestra como aviso genérico, nunca revienta. */
    public static function tipoValido(string $tipo): string
    {
        return isset(self::TIPOS[$tipo]) ? $tipo : self::TIPO_GENERAL;
    }

    public static function presentacion(string $tipo): array
    {
        $t = self::TIPOS[self::tipoValido($tipo)];
        return $t + ['tipo' => self::tipoValido($tipo)];
    }

    public static function icono(string $tipo): string
    {
        return self::presentacion($tipo)['icono'];
    }

    public static function etiqueta(string $tipo): string
    {
        return self::presentacion($tipo)['etiqueta'];
    }

    public static function color(string $tipo): string
    {
        return 'var(--sget-' . self::presentacion($tipo)['tono'] . ')';
    }

    public static function claseTipo(string $tipo): string
    {
        $tono = self::presentacion($tipo)['tono'];
        $mapa = [
            'rojo'    => 'sget-badge sget-badge--error',
            'emerald' => 'sget-badge sget-badge--exito',
            'ambars'  => 'sget-badge sget-badge--aviso',
            'azul'    => 'sget-badge sget-badge--info',
            'morado'  => 'sget-badge sget-badge--neutro',
        ];
        return $mapa[$tono] ?? 'sget-badge sget-badge--info';
    }

    /* ================================================================== */
    /* Interno                                                             */
    /* ================================================================== */

    /**
     * ¿Este usuario ya tiene un aviso con esa firma?
     *
     * La columna `firma` la añade la migración 006 y lleva un índice único
     * junto a `id_usu`. Si la migración aún no se aplicó, el servicio NO se
     * rompe: simplemente no deduplica (es mejor un aviso de más que perder
     * avisos).
     */
    private static function existeFirma(int $idUsuario, string $firma): bool
    {
        try {
            return (bool) Database::scalar(
                "SELECT 1 FROM notificacion WHERE id_usu = ? AND firma = ? LIMIT 1",
                [$idUsuario, $firma]
            );
        } catch (Throwable $e) {
            return false;   // sin la columna `firma`: se envía igual
        }
    }

    private static function esFirmaDuplicada(Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'Duplicate entry')
            || (int)($e->getCode() ?? 0) === 23000;
    }
}
