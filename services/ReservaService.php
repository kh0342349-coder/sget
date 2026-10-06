<?php
/**
 * services/ReservaService.php
 * -----------------------------------------------------------------------------
 * MÓDULO: RESERVAS Y RECAUDO
 * -----------------------------------------------------------------------------
 * POR QUÉ EXISTE
 *   La reserva se escribía desde tres sitios distintos con SQL propio:
 *     · Admin/asignaciones.php   (recaudo en terminal)
 *     · Pasajero/procesar_reserva.php (reserva online)
 *     · y se leía en dos módulos más.
 *
 *   Y ahí estaba el bug más grave del sistema: el recaudo del admin insertaba
 *   `estado_pago = 'Completado'`, un valor que NO existe en el ENUM
 *   ('Pendiente','Confirmada','Cancelada'). El INSERT fallaba, el código no
 *   comprobaba el resultado y aun así pintaba "¡Asignación registrada con
 *   éxito!" con el ticket de la reserva 0. Como además todo lo demás usaba
 *   'Pendiente', nunca hubo una sola reserva 'Confirmada'… y por eso el módulo
 *   de Ganancias mostraba $0 siempre.
 *
 *   Aquí vive la ÚNICA lógica de reserva: capacidad, cobro, cancelación y aviso
 *   al pasajero. Las tres pantallas llaman a este servicio.
 *
 * MODELO DE DATOS
 *   Un PUESTO reservado = una fila en `reserva`. Si un pasajero compra 3
 *   puestos se crean 3 filas. Es lo que ya asumían la ocupación de cupos y los
 *   informes (`cupos_vendidos = COUNT(reservas)`), y se mantiene a propósito:
 *   cambiarlo ahora obligaría a rehacer el histórico de reportes.
 *
 *   `valor_pagado` guarda el VALOR PACTADO (lo que el pasajero debe), no solo
 *   lo cobrado: se escribe al crear la reserva con la tarifa del viaje. Antes
 *   se guardaba NULL y el total a pagar solo se calculaba para pintar el
 *   comprobante, así que en el recaudo el botón de cobrar acababa registrando
 *   $0. Los ingresos siguen sumando SOLO las reservas Confirmadas, de modo que
 *   un valor pactado pendiente nunca infla la caja.
 *
 *   `embarco` va aparte del estado de pago a propósito: se puede haber pagado
 *   antes y no subió nunca, o pagar al abordar y sí subir. Mezclar ambos
 *   conceptos en un único ENUM obligaba a elegir entre los dos casos.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class ReservaService
{
    private const MENSAJE_RESERVA_DUPLICADA = 'Ya cuentas con una reserva activa para este viaje. No es posible reservar el mismo viaje más de una vez.';

    /* ================================================================== */
    /* Consultas                                                           */
    /* ================================================================== */

    /** Cupos que quedan en un viaje (nunca negativo). */
    public static function cuposDisponibles(int $idViaje): int
    {
        $v = Database::one(
            "SELECT cup_tot, cup_dis,
                    (SELECT COALESCE(SUM(cantidad_puestos),0) FROM reserva r WHERE r.id_via_res = v.id_via AND r.estado_pago <> ? AND r.id_res IS NOT NULL) AS ocupados
               FROM viaje v WHERE v.id_via = ?",
            [Config::RES_CANCELADA, $idViaje]
        );
        if (!$v) return 0;

        $totales = (int)($v['cup_tot'] ?? 0);
        $libres  = $v['cup_dis'] === null ? $totales : (int)$v['cup_dis'];

        // `cup_dis` puede quedar desfasado si se borraron filas a mano: manda
        // el conteo real de reservas vivas.
        $real = $totales - (int)$v['ocupados'];
        return max(0, min($libres, $real));
    }

    /** Datos de reserva + viaje + ruta, para mostrar y notificar. */
    public static function porId(int $idReserva): ?array
    {
        return Database::one(
            "SELECT res.*, v.fec_via, v.hor_sal_via, v.val_via, v.est_via,
                    r.nom_rut, r.ori_rut, r.des_rut,
                    u.nom_usu AS pasajero, u.num_doc_usu, u.tel_usu
               FROM reserva res
               INNER JOIN viaje v   ON v.id_via = res.id_via_res
               LEFT  JOIN rutas r   ON r.id_rut = v.id_rut_via
               INNER JOIN usuario u ON u.id_usu = res.id_usu_res
              WHERE res.id_res = ?",
            [$idReserva]
        );
    }

    /**
     * Reservas de un viaje (recaudo del día).
     *
     * Trae `val_via` y el `valor_pactado` de cada puesto porque el botón de
     * cobrar necesita saber QUÉ COBRAR: antes el navegador mandaba el
     * `valor_pagado` guardado, que venía NULL en toda reserva online y acabó
     * registrando $0.
     */
    public static function porViaje(int $idViaje): array
    {
        return Database::all(
                "SELECT res.id_res, res.fech_res, res.metodo_pago, res.valor_pagado, res.estado_pago, res.fecha_pago,
                    res.embarco, res.motivo_cancelacion, res.fec_cancelacion, res.aviso_viaje_perdido,
                    res.es_temporal, res.punto_abordaje, res.destino_abordaje, res.registrada_por,
                    u.id_usu AS id_pasajero, u.nom_usu AS pasajero, u.num_doc_usu, u.tel_usu,
                    v.val_via
               FROM reserva res
               INNER JOIN usuario u ON u.id_usu = res.id_usu_res
               INNER JOIN viaje  v ON v.id_via = res.id_via_res
              WHERE res.id_via_res = ?
              ORDER BY res.estado_pago ASC, u.nom_usu ASC, res.id_res ASC",
            [$idViaje]
        );
    }

    /**
     * Manifiesto de pasajeros de un viaje: quién tiene puesto, cuánto debe y si
     * acabó subiendo al bus. Es la lista que necesitan el admin (recaudo e
     * informes) y el conductor (a quién espera en el paradero).
     *
     * @return array<int,array> una fila por PASAJERO, con el detalle de sus
     *                          puestos. Se agrupa en PHP y no en SQL para que el
     *                          total de cada uno sume bien aunque mezcle pagos.
     */
    public static function manifiesto(int $idViaje): array
    {
        $filas = Database::all(
                "SELECT res.id_res, res.id_usu_res, res.estado_pago, res.embarco,
                    res.valor_pagado, res.metodo_pago, res.fech_res, res.es_temporal,
                    res.punto_abordaje, res.destino_abordaje, res.registrada_por,
                    res.motivo_cancelacion,
                    u.nom_usu AS pasajero, u.num_doc_usu, u.tel_usu
               FROM reserva res
               INNER JOIN usuario u ON u.id_usu = res.id_usu_res
              WHERE res.id_via_res = ?
              ORDER BY u.nom_usu ASC, res.id_res ASC",
            [$idViaje]
        );

        $porPasajero = [];
        foreach ($filas as $f) {
            $id = (int)$f['id_usu_res'];
            if (!isset($porPasajero[$id])) {
                $porPasajero[$id] = [
                    'id_pasajero'  => $id,
                    'pasajero'     => (string)$f['pasajero'],
                    'num_doc_usu'  => (string)$f['num_doc_usu'],
                    'tel_usu'      => $f['tel_usu'] !== null ? (string)$f['tel_usu'] : '',
                    'puestos'      => 0,
                    'ids'          => [],
                    'ids_por_embarcar' => [],
                    'es_temporal'  => false,
                    'tramos_temporales' => [],
                    'pagados'      => 0,
                    'pendientes'   => 0,
                    'pendientes_al_abordar' => 0,
                    'cancelados'   => 0,
                    'embarcaron'   => 0,
                    'no_embarcaron'=> 0,
                    'sin_decidir'  => 0,
                    'debe'         => 0.0,
                    'motivo'       => '',
                ];
            }

            $p = &$porPasajero[$id];
            $estado = (string)$f['estado_pago'];
            $p['ids'][] = (int)$f['id_res'];
            $puestos = (int)($f['cantidad_puestos'] ?? 1);
            $p['puestos'] += $puestos;
            if ((int)($f['es_temporal'] ?? 0) === 1) {
                $p['es_temporal'] = true;
                $p['tramos_temporales'][] = [
                    'origen' => (string)($f['punto_abordaje'] ?? ''),
                    'destino' => (string)($f['destino_abordaje'] ?? ''),
                ];
            }

            if ($estado === Config::RES_CANCELADA) {
                $p['cancelados']++;
                if ((string)$f['motivo_cancelacion'] !== '') $p['motivo'] = (string)$f['motivo_cancelacion'];
            } else {
                $p['debe'] += (float)($f['valor_pagado'] ?? 0);
                $p[$estado === Config::RES_CONFIRMADA ? 'pagados' : 'pendientes']++;
                if ($estado === Config::RES_PENDIENTE
                    && in_array(mb_strtolower(trim((string)$f['metodo_pago'])), ['efectivo', 'efectivo al abordar', 'pago al abordar'], true)) {
                    $p['pendientes_al_abordar']++;
                }
                if ($estado === Config::RES_CONFIRMADA && ($f['embarco'] === null || $f['embarco'] === '')) {
                    $p['ids_por_embarcar'][] = (int)$f['id_res'];
                }
            }

            // `embarco` va por PUESTO: si un pasajero compró 3 y solo subió
            // con 1, cuenta como embarcó pero los otros 2 son no-presentación.
            if ($estado !== Config::RES_CANCELADA) {
                if ($f['embarco'] === null || $f['embarco'] === '') $p['sin_decidir']++;
                elseif ((int)$f['embarco'] === 1) $p['embarcaron']++;
                else $p['no_embarcaron']++;
            }
            unset($p);
        }

        return array_values($porPasajero);
    }

    /**
     * Puestos de un viaje que siguen sin decidirse (el pasajero aún no embarcó
     * ni se marcó como no presentado). Se usa al cerrar el viaje para avisar a
     * quien se quedó en casa.
     */
    public static function pendientesDeEmbarque(int $idViaje): array
    {
        return Database::all(
            "SELECT res.id_res, res.id_usu_res, res.estado_pago, res.embarco, res.valor_pagado,
                    u.nom_usu AS pasajero, u.num_doc_usu
               FROM reserva res
               INNER JOIN usuario u ON u.id_usu = res.id_usu_res
              WHERE res.id_via_res = ?
                AND res.estado_pago <> ?
                AND res.embarco IS NULL
                AND res.aviso_viaje_perdido = 0",
            [$idViaje, Config::RES_CANCELADA]
        );
    }

    /** Historial de reservas de un pasajero. */
    public static function porPasajero(int $idPasajero, int $limite = 200): array
    {
        return Database::all(
            "SELECT res.id_res, res.fech_res, res.estado_pago, res.metodo_pago, res.valor_pagado,
                    v.id_via, v.fec_via, v.hor_sal_via, v.est_via,
                    r.nom_rut, r.ori_rut, r.des_rut,
                    (SELECT COUNT(*) FROM calificacion c
                      WHERE c.id_via_cal = v.id_via AND c.id_usu_rem = res.id_usu_res) AS calificado
               FROM reserva res
               INNER JOIN viaje v ON v.id_via = res.id_via_res
               LEFT  JOIN rutas r ON r.id_rut = v.id_rut_via
              WHERE res.id_usu_res = ?
              ORDER BY v.fec_via DESC, v.hor_sal_via DESC, res.id_res DESC
              LIMIT " . (int)$limite,
            [$idPasajero]
        );
    }

    /** ¿Este pasajero ya tiene puesto en este viaje? */
    public static function pasajeroYaReservo(int $idViaje, int $idPasajero): int
    {
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM reserva
              WHERE id_via_res = ? AND id_usu_res = ? AND estado_pago <> ?",
            [$idViaje, $idPasajero, Config::RES_CANCELADA]
        );
    }

    /**
     * ¿Por qué NO puede este pasajero apartar en este viaje?
     *
     * Devuelve un texto listo para pintar, o null si puede. La regla pedida es
     * «un pasajero no puede apartar dos veces el mismo viaje, salvo que sea
     * otra fecha y hora», y detrás hay dos Confederation distinta:
     *
     *   · MISMO VIAJE: ya tiene puesto ahí y quería comprar otro. Como un
     *     puesto es una fila, esto se traducía en comprar N puestos sin
     *     querer: con el boton «reservar» del listado se acababa ocupando un
     *     segundo asiento, o varios, y luego aparecía «2 puestos» en un
     *     sitio donde el pasajero creía haber cogido uno.
     *   · OTRO VIAJE A LA MISMA HORA: puede reservar viajes distintos, pero no
     *     dos que salen en el mismo instante: no puede estar en dos buses.
     *
     * Se comparan fecha y hora de SALIDA exactas: si el otro viaje es a una
     * hora distinta, se permite.
     */
    public static function conflictoReserva(int $idViaje, int $idPasajero, int $puestos = 1): ?string
    {
        if ($idPasajero <= 0) return null;

        $mismo = self::pasajeroYaReservo($idViaje, $idPasajero);
        if ($mismo > 0) {
            return self::MENSAJE_RESERVA_DUPLICADA;
        }

        $choque = Database::one(
            "SELECT v.id_via, v.fec_via, v.hor_sal_via, r.nom_rut, r.ori_rut, r.des_rut
               FROM reserva res
               INNER JOIN viaje v ON v.id_via = res.id_via_res
               LEFT  JOIN rutas r ON r.id_rut = v.id_rut_via
              WHERE res.id_usu_res = ?
                AND res.estado_pago <> ?
                AND res.id_via_res <> ?
                AND DATE(v.fec_via) = DATE((SELECT fec_via FROM viaje WHERE id_via = ?))
                AND v.hor_sal_via = (SELECT hor_sal_via FROM viaje WHERE id_via = ?)
              LIMIT 1",
            [$idPasajero, Config::RES_CANCELADA, $idViaje, $idViaje, $idViaje]
        );

        if ($choque) {
            $ruta = trim(($choque['nom_rut'] ?? '') . ' (' . ($choque['ori_rut'] ?? '?') . ' → ' . ($choque['des_rut'] ?? '?') . ')');
            return sprintf(
                'Ya tienes un puesto en otro viaje que sale a la MISMA fecha y hora (viaje #%d · %s). '
                . 'No puedes estar en dos viajes a la vez: reserva otra hora o cancela el anterior.',
                (int)$choque['id_via'], $ruta
            );
        }

        return null;
    }

    public static function metodosPago(): array
    {
        return ['Efectivo', 'Efectivo al Abordar', 'Transferencia', 'Tarjeta', 'Nequi', 'Daviplata', 'PSE'];
    }

    /* ================================================================== */
    /* Creación (reserva online del pasajero)                              */
    /* ================================================================== */

/**
     * Reserva uno o varios puestos para un pasajero.
     *
     * -----------------------------------------------------------------------------
     * CONCURRENCIA · LA CARRERA QUE SE CIERRA AQUÍ
     * -----------------------------------------------------------------------------
     * El fallo clásico de este módulo:
     *
     *      cupos libres = 1
     *      Usuario A consulta → ve 1 libre
     *      Usuario B consulta → ve 1 libre        (los dos leen lo mismo)
     *      A reserva → INSERT
     *      B reserva → INSERT                    → 2 puestos en un bus de 1
     *
     * Comprobar los cupos FUERA de la transacción no sirve de nada: entre la
     * comprobación y el INSERT hay una ventana en la que otra petición ve el
     * mismo número. La comprobación tiene que hacerse DENTRO de la
     * transacción y con la fila del viaje BLOQUEADA (`SELECT … FOR UPDATE`),
     * de modo que la segunda transacción espere a que la primera confirme y
     * entonces vea el estado que dejó.
     *
     * El cupo se recalcula además con un COUNT real y no a ciegas, para que un
     * dato previo desfasado nunca deje el viaje con cupos negativos.
     *
     * @param array $op  ['metodo' => string, 'valor' => float, 'confirmar' => bool]
     * @return array{ok:bool, mensaje:string, ids:array<int>, puestos:int}
     */
    public static function crear(int $idViaje, int $idPasajero, int $puestos = 1, array $op = []): array
    {
        $puestos   = max(1, $puestos);
        $confirmar = !empty($op['confirmar']);

        $falla = static fn(string $m): array =>
            ['ok' => false, 'mensaje' => $m, 'ids' => [], 'puestos' => 0];

        /* ------------------------------------------------------------------
         * 1) Comprobaciones que NO dependen del estado del viaje.
         *    Van fuera de la transacción para no bloquearla más de lo necesario.
         * ---------------------------------------------------------------- */
        $viaje = Database::one(
            "SELECT v.*, r.nom_rut, r.ori_rut, r.des_rut
               FROM viaje v
               LEFT JOIN rutas r ON r.id_rut = v.id_rut_via
              WHERE v.id_via = ?",
            [$idViaje]
        );

        if (!$viaje) {
            return $falla('El viaje no existe.');
        }
        if (!in_array((string)$viaje['est_via'], [Config::VIA_PROGRAMADO, Config::VIA_EN_CURSO], true)) {
            return $falla('Ese viaje ya no está disponible para reservar.');
        }
        if ($idPasajero <= 0) {
            return $falla('Debes iniciar sesión para reservar un puesto.');
        }

        // Regla del apartado: un pasajero no puede volver a apartar en un viaje
        // en el que ya tiene puesto, ni en otro que salga a la misma fecha y hora.
        // Sin esto, el botón «reservar» del listado compraba un asiento extra
        // cada vez que se pulsaba.
        if ($conflicto = self::conflictoReserva($idViaje, $idPasajero, $puestos)) {
            return $falla($conflicto);
        }

        $metodo = (string)($op['metodo'] ?? 'Efectivo al Abordar');
        $tarifa = (float)($viaje['val_via'] ?? 0);

        // El VALOR PACTADO: lo que el pasajero debe por este puesto. Antes se
        // guardaba NULL y el total a pagar solo se pintaba en el comprobante, de
        // modo que el recaudo no tenía con qué cobrar y acababa guardando $0.
        $valor = (float)($op['valor'] ?? 0);
        if ($valor <= 0) $valor = $tarifa;

        $estado = $confirmar ? Config::RES_CONFIRMADA : Config::RES_PENDIENTE;

        try {
            Database::begin();

            /* ---------------------------------------------------------------
               2) BLOQUEO DEL VIAJE Y RE-COMPROBACIÓN DE CUPOS
               --------------------------------------------------------------- */
            $bloqueado = Database::one(
                'SELECT id_via, cup_tot FROM viaje WHERE id_via = ? FOR UPDATE',
                [$idViaje]
            );

            if (!$bloqueado) {
                Database::rollback();
                return $falla('El viaje ya no está disponible.');
            }

            // Repetir la comprobación bajo el bloqueo evita que dos solicitudes
            // simultáneas del mismo pasajero creen reservas para el mismo viaje.
            $reservaActiva = (int) Database::scalar(
                'SELECT COUNT(*) FROM reserva
                  WHERE id_via_res = ? AND id_usu_res = ? AND estado_pago <> ?',
                [$idViaje, $idPasajero, Config::RES_CANCELADA]
            );
            if ($reservaActiva > 0) {
                Database::rollback();
                return $falla(self::MENSAJE_RESERVA_DUPLICADA);
            }

            $ocupados = (int) Database::scalar(
                'SELECT COALESCE(SUM(cantidad_puestos), 0) FROM reserva WHERE id_via_res = ? AND estado_pago <> ?',
                [$idViaje, Config::RES_CANCELADA]
            );
            $libres = max(0, (int)$bloqueado['cup_tot'] - $ocupados);

            if ($puestos > $libres) {
                Database::rollback();
                return $falla($libres === 0
                    ? 'Ese viaje ya no tiene cupos disponibles.'
                    : sprintf('Solo quedan %d cupo(s) disponibles en este viaje y solicitaste %d.', $libres, $puestos));
            }

            /* ---------------------------------------------------------------
               3) Inserción agrupada: un registro por reserva (puede ser
                  1 puesto o varios). Se guarda cantidad_puestos y la lista
                  de asientos asignados.
               --------------------------------------------------------------- */
            $asientos = range(1, $puestos);
            $ids = Database::insert(
                "INSERT INTO reserva (id_via_res, id_usu_res, fech_res, metodo_pago, valor_pagado, estado_pago, fecha_pago, cantidad_puestos, asientos_asignados)
                 VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?)",
                [
                    $idViaje, $idPasajero, $metodo,
                    $valor > 0 ? ($valor * $puestos) : null,
                    $estado,
                    $confirmar ? date('Y-m-d H:i:s') : null,
                    $puestos,
                    implode(',', array_map('strval', $asientos)),
                ]
            );

            Database::query('UPDATE viaje SET cup_dis = ? WHERE id_via = ?', [$libres - $puestos, $idViaje]);

            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            error_log('[SGET][ReservaService::crear] ' . $e->getMessage());
            return $falla('No se pudo registrar la reserva. Inténtalo de nuevo.');
        }

        $primerId = (int)$ids;
        $ids = [$ids];
        $ruta = trim(($viaje['nom_rut'] ?? '') . ' (' . ($viaje['ori_rut'] ?? '?') . ' → ' . ($viaje['des_rut'] ?? '?') . ')');

        if ($confirmar && $primerId > 0) {
            NotificacionService::notificarReservaConfirmada($idPasajero, [
                'id_res'  => $primerId,
                'id_via'  => $idViaje,
                'ruta'    => $ruta,
                'fec_via' => (string)$viaje['fec_via'],
                'hor_sal_via' => (string)$viaje['hor_sal_via'],
                'valor'   => $valor,
                'metodo'  => $metodo,
            ]);
        }

        return [
            'ok'      => true,
            'ids'     => $ids,
            'puestos' => $puestos,
            'mensaje' => sprintf(
                'Reserva registrada: %d puesto(s) en el viaje #%d por $%s%s.',
                $puestos, $idViaje, number_format($valor * $puestos, 0, ',', '.'),
                $confirmar ? ' (pago confirmado)' : ' (pago pendiente en terminal)'
            ),
        ];
    }

    /* ================================================================== */
    /* Cobro / confirmación (recaudo en terminal)                            */
    /* ================================================================== */

    /**
     * Confirma el pago de UNA reserva (un puesto).
     *
     * @param float  $valor      importe cobrado. 0 = usar el valor pactado.
     * @param bool   $todosPuestos  cobrar también el resto de puestos PENDIENTES
     *                              del mismo pasajero en el mismo viaje, que es
     *                              lo que hace falta cuando un pasajero paga
     *                              los tres puestos de una vez en mostrador.
     * @return array{ok:bool, mensaje:string, cobradas:int}
     */
    public static function confirmarPago(int $idReserva, float $valor, string $metodo = 'Efectivo', bool $todosPuestos = false): array
    {
        $res = self::porId($idReserva);
        if (!$res) return ['ok' => false, 'mensaje' => 'La reserva no existe.', 'cobradas' => 0];

        if ($res['estado_pago'] === Config::RES_CANCELADA) {
            return ['ok' => false, 'mensaje' => 'No se puede cobrar una reserva cancelada.', 'cobradas' => 0];
        }

        // Con «cobrar todos los puestos» el clic puede caer sobre una reserva ya
        // cobrada (el pasajero pagó 2 de 3 y vuelve al mostrador por el
        // tercero). Se comprueba si queda algo pendiente de verdad, en vez de
        // rechazar en seco y dejar al administrador sin poder hacer el cobro.
        if ($res['estado_pago'] === Config::RES_CONFIRMADA) {
            if (!$todosPuestos) {
                return ['ok' => false, 'mensaje' => 'Esa reserva ya estaba confirmada.', 'cobradas' => 0];
            }
            $quedan = self::puestosPendientesDe(
                (int)$res['id_via_res'], (int)$res['id_usu_res'], 0
            );
            if (empty($quedan)) {
                return ['ok' => false, 'mensaje' => 'Este pasajero ya pagó todos sus puestos en el viaje.', 'cobradas' => 0];
            }
        }

        // Resolver el importe ANTES de escribir. Antes se hacía al revés: se
        // guardaba el 0 que mandaba el navegador y, como el valor pactado
        // tampoco estaba, la reserva quedaba confirmada en $0.
        $pactado = (float)($res['valor_pagado'] ?? 0);
        $total   = $todosPuestos ? self::totalPendienteDe($res) : $pactado;

        if ($valor <= 0) $valor = $total;

        if ($valor <= 0) {
            return ['ok' => false, 'cobradas' => 0,
                    'mensaje' => 'No hay valor pactado en esta reserva: escribe el importe que se cobró.'];
        }

        $aCobrar = [$idReserva];
        if ($todosPuestos) {
            $aCobrar = self::puestosPendientesDe(
                (int)$res['id_via_res'],
                (int)$res['id_usu_res'],
                $idReserva
            );
        }

        // Con un único puesto el importe es tal cual; con varios se reparte a
        // partes iguales y el último cierra la cuenta para que la suma cuadre.
        //
        // `$porPuesto` y `$asignado` se inicializan SIEMPRE: antes solo se
        // definían dentro de `if (count > 1)` y el bucle hacía `$asignado +=`
        // en el caso de un solo puesto, dejando un «Undefined variable» en cada
        // cobro normal.
        $porPuesto = null;
        $asignado  = 0.0;
        if (count($aCobrar) > 1) {
            $porPuesto = round($valor / count($aCobrar), 2);
        }

        try {
            Database::begin();
            foreach ($aCobrar as $i => $id) {
                $importe = $porPuesto ?? $valor;
                if ($porPuesto !== null && $i === count($aCobrar) - 1) {
                    $importe = round($valor - $asignado, 2);   // el último cierra la cuenta
                }
                $asignado += $importe;

                Database::query(
                    "UPDATE reserva
                        SET estado_pago = ?, valor_pagado = ?, metodo_pago = ?, fecha_pago = NOW()
                      WHERE id_res = ?",
                    [Config::RES_CONFIRMADA, $importe, $metodo !== '' ? $metodo : (string)$res['metodo_pago'], $id]
                );
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            error_log('[SGET][ReservaService::confirmarPago] ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo registrar el cobro.', 'cobradas' => 0];
        }

        NotificacionService::notificarReservaConfirmada((int)$res['id_usu_res'], [
            'id_res'  => $idReserva,
            'id_via'  => (int)$res['id_via_res'],
            'ruta'    => trim(($res['nom_rut'] ?? '') . ' (' . ($res['ori_rut'] ?? '?') . ' → ' . ($res['des_rut'] ?? '?') . ')'),
            'fec_via' => (string)$res['fec_via'],
            'hor_sal_via' => (string)$res['hor_sal_via'],
            'valor'   => $valor,
            'metodo'  => $metodo,
        ]);

        Logger::registrar(Database::pdo(), 'CONFIRMAR_PAGO', sprintf(
            'Reserva #%d cobrada ($%s, %s) por %s%s.',
            $idReserva, number_format($valor, 0, ',', '.'), $metodo, Auth::nombre(),
            count($aCobrar) > 1 ? ' · ' . count($aCobrar) . ' puestos de golpe' : ''
        ));

        return [
            'ok'       => true,
            'cobradas' => count($aCobrar),
            'mensaje'  => count($aCobrar) > 1
                ? sprintf('Cobro de $%s registrado en %d puestos.', number_format($valor, 0, ',', '.'), count($aCobrar))
                : sprintf('Cobro de $%s registrado y pasajero notificado.', number_format($valor, 0, ',', '.')),
        ];
    }

    /** Confirma en una sola transacción el pago en efectivo y el abordaje. */
    public static function cobrarYEmbarcarPasajero(int $idViaje, int $idPasajero): array
    {
        $falla = static fn(string $mensaje): array => ['ok' => false, 'mensaje' => $mensaje, 'cobradas' => 0];
        if (Auth::rol() !== Config::ROL_CONDUCTOR || Auth::id() <= 0) {
            return $falla('Solo el conductor asignado puede confirmar el pago y el abordaje.');
        }
        if ($idViaje <= 0 || $idPasajero <= 0) {
            return $falla('No se pudo identificar el viaje y el pasajero.');
        }

        try {
            Database::begin();
            $viaje = Database::one(
                'SELECT v.id_via, v.id_usu_via, v.est_via, v.fec_via, v.hor_sal_via,
                        r.nom_rut, r.ori_rut, r.des_rut
                   FROM viaje v
                   LEFT JOIN rutas r ON r.id_rut = v.id_rut_via
                  WHERE v.id_via = ? FOR UPDATE',
                [$idViaje]
            );
            if (!$viaje || (int)$viaje['id_usu_via'] !== Auth::id()) {
                Database::rollback();
                return $falla('Solo puedes confirmar pasajeros de tus propios viajes.');
            }
            if (!in_array((string)$viaje['est_via'], [Config::VIA_PROGRAMADO, Config::VIA_EN_CURSO], true)) {
                Database::rollback();
                return $falla('El viaje ya no admite confirmaciones de pago o abordaje.');
            }

            $reservas = Database::all(
                'SELECT id_res, estado_pago, metodo_pago, valor_pagado, embarco
                   FROM reserva
                  WHERE id_via_res = ? AND id_usu_res = ? AND estado_pago <> ?
                  ORDER BY id_res ASC FOR UPDATE',
                [$idViaje, $idPasajero, Config::RES_CANCELADA]
            );
            if (!$reservas) {
                Database::rollback();
                return $falla('No hay reservas activas de este pasajero en el viaje.');
            }

            $pendientes = array_values(array_filter(
                $reservas,
                static fn(array $reserva): bool => (string)$reserva['estado_pago'] === Config::RES_PENDIENTE
            ));
            if (!$pendientes) {
                Database::rollback();
                return $falla('Este pasajero no tiene pagos pendientes para confirmar.');
            }

            foreach ($pendientes as $reserva) {
                $metodo = mb_strtolower(trim((string)$reserva['metodo_pago']));
                if (!in_array($metodo, ['efectivo', 'efectivo al abordar', 'pago al abordar'], true)) {
                    Database::rollback();
                    return $falla('El pago de este pasajero debe confirmarse desde el módulo de recaudo.');
                }
                if ((float)($reserva['valor_pagado'] ?? 0) <= 0) {
                    Database::rollback();
                    return $falla('La reserva no tiene un importe válido para cobrar.');
                }
                if ((int)($reserva['embarco'] ?? -1) === 0) {
                    Database::rollback();
                    return $falla('El pasajero ya está marcado como no presentado.');
                }
            }

            $primerId = (int)$pendientes[0]['id_res'];
            $total = 0.0;
            foreach ($reservas as $reserva) {
                $idReserva = (int)$reserva['id_res'];
                if ((string)$reserva['estado_pago'] === Config::RES_PENDIENTE) {
                    $total += (float)$reserva['valor_pagado'];
                    Database::query(
                        'UPDATE reserva
                            SET estado_pago = ?, metodo_pago = ?, fecha_pago = NOW()
                          WHERE id_res = ? AND estado_pago = ?',
                        [Config::RES_CONFIRMADA, 'Efectivo', $idReserva, Config::RES_PENDIENTE]
                    );
                }
                if ($reserva['embarco'] === null || $reserva['embarco'] === '') {
                    Database::query(
                        'UPDATE reserva
                            SET embarco = 1, embarque_por = ?, embarque_fec = NOW()
                          WHERE id_res = ? AND embarco IS NULL',
                        [Auth::id(), $idReserva]
                    );
                }
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            error_log('[SGET][ReservaService::cobrarYEmbarcarPasajero] ' . $e->getMessage());
            return $falla('No se pudo confirmar el pago y el abordaje. Inténtalo de nuevo.');
        }

        $ruta = trim((string)($viaje['nom_rut'] ?? '') . ' (' . (string)($viaje['ori_rut'] ?? '?') . ' → ' . (string)($viaje['des_rut'] ?? '?') . ')');
        NotificacionService::notificarReservaConfirmada($idPasajero, [
            'id_res' => $primerId,
            'id_via' => $idViaje,
            'ruta' => $ruta,
            'fec_via' => (string)$viaje['fec_via'],
            'hor_sal_via' => (string)$viaje['hor_sal_via'],
            'valor' => $total,
            'metodo' => 'Efectivo al abordar',
        ]);
        Logger::registrar(Database::pdo(), 'CONFIRMAR_PAGO_Y_EMBARQUE', sprintf(
            'Pasajero #%d · viaje #%d · %d puesto(s), $%s, confirmado y embarcado por %s.',
            $idPasajero, $idViaje, count($pendientes), number_format($total, 0, ',', '.'), Auth::nombre()
        ));

        return [
            'ok' => true,
            'cobradas' => count($pendientes),
            'mensaje' => sprintf(
                'Pago de $%s confirmado y abordaje registrado para %d puesto(s).',
                number_format($total, 0, ',', '.'), count($pendientes)
            ),
        ];
    }

    /** Puestos PENDIENTES de un pasajero en un viaje. */
    private static function puestosPendientesDe(int $idViaje, int $idPasajero, int $incluirId): array
    {
        $filas = Database::all(
            "SELECT id_res FROM reserva
              WHERE id_via_res = ? AND id_usu_res = ? AND estado_pago = ?
              ORDER BY id_res ASC",
            [$idViaje, $idPasajero, Config::RES_PENDIENTE]
        );

        $ids = array_map(static fn(array $f): int => (int)$f['id_res'], $filas);
        if ($incluirId > 0 && !in_array($incluirId, $ids, true)) $ids[] = $incluirId;
        return $ids;
    }

    /** Total de los puestos pendientes de este pasajero en este viaje. */
    private static function totalPendienteDe(array $res): float
    {
        return (float) Database::scalar(
            "SELECT COALESCE(SUM(valor_pagado), 0) FROM reserva
              WHERE id_via_res = ? AND id_usu_res = ? AND estado_pago = ?",
            [(int)$res['id_via_res'], (int)$res['id_usu_res'], Config::RES_PENDIENTE]
        );
    }

    /* ================================================================== */
    /* Embarque y no-presentación                                          */
    /* ================================================================== */

    /**
     * Registra si un pasajero embarcó o no se presentó.
     *
     * POR QUÉ EXISTE
     *   «Reservó y pagó» no es lo mismo que «viajó». Sin este dato los informes
     *   no podían distinguir a quien se quedó en casa de quien sí lo hizo, y
     *   el pasajero no se enteraba de que su viaje había salido sin él. El
     *   conductor es quien lo ve en el paradero: por eso puede marcarlo.
     *
     * @param bool  $embarco    true = subió; false = no se presentó
     * @param string $motivo     obligatorio si NO embarcó
     * @return array{ok:bool, mensaje:string}
     */
    public static function marcarEmbarque(int $idReserva, bool $embarco, string $motivo = '', ?int $idViaje = null): array
    {
        $res = self::porId($idReserva);
        if (!$res) return ['ok' => false, 'mensaje' => 'La reserva no existe.'];
        if ($idViaje !== null && (int)$res['id_via_res'] !== $idViaje) {
            return ['ok' => false, 'mensaje' => 'La reserva no pertenece al viaje indicado.'];
        }

        if ($res['estado_pago'] === Config::RES_CANCELADA) {
            return ['ok' => false, 'mensaje' => 'Esa reserva está cancelada: no hay embarque que registrar.'];
        }
        if ($embarco && $res['estado_pago'] === Config::RES_PENDIENTE) {
            return ['ok' => false, 'mensaje' => 'Confirma el pago antes de registrar el abordaje.'];
        }
        if (!$embarco) {
            $motivo = trim($motivo);
            if ($motivo === '') {
                return ['ok' => false,
                        'mensaje' => 'Explica por qué no se presentó. Sin motivo, el informe no puede distinguir '
                                   . 'un no-show de una reserva cancelada por el pasajero.'];
            }
        }

        try {
            Database::query(
                "UPDATE reserva
                    SET embarco = ?, embarque_por = ?, embarque_fec = NOW(),
                        motivo_cancelacion = CASE WHEN ? = 1 THEN motivo_cancelacion ELSE ? END
                  WHERE id_res = ?",
                [$embarco ? 1 : 0, Auth::id() ?: null, $embarco ? 1 : 0, trim($motivo), $idReserva]
            );
        } catch (Throwable $e) {
            error_log('[SGET][ReservaService::marcarEmbarque] ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo registrar el embarque.'];
        }

        Logger::registrar(Database::pdo(), $embarco ? 'EMBARCAR_PASAJERO' : 'NO_PRESENTE', sprintf(
            'Reserva #%d · %s por %s%s.',
            $idReserva, $embarco ? 'embarcó' : 'no se presentó', Auth::nombre(),
            $embarco ? '' : ' · ' . trim($motivo)
        ));

        return [
            'ok'      => true,
            'mensaje' => $embarco
                ? 'Embarque registrado.'
                : 'No-presentación registrada y el motivo queda en el informe.',
        ];
    }

    /**
     * Marca como no presentado a TODOS los puestos pendientes de un pasajero en
     * un viaje. Es el atajo del conductor: «este señor no vino» y se cierra su
     * reserva entera de una vez.
     *
     * @return array{ok:bool, mensaje:string, marcadas:int}
     */
    public static function marcarNoPresente(int $idViaje, int $idPasajero, string $motivo): array
    {
        if (trim($motivo) === '') {
            return ['ok' => false, 'marcadas' => 0, 'mensaje' => 'Escribe el motivo de la no-presentación.'];
        }

        $n = Database::query(
            "UPDATE reserva
                SET embarco = 0, embarque_por = ?, embarque_fec = NOW(),
                    motivo_cancelacion = ?, aviso_viaje_perdido = 1
              WHERE id_via_res = ? AND id_usu_res = ?
                AND estado_pago <> ? AND embarco IS NULL",
            [Auth::id() ?: null, trim($motivo), $idViaje, $idPasajero, Config::RES_CANCELADA]
        )->rowCount();

        if ($n === 0) {
            return ['ok' => false, 'marcadas' => 0,
                    'mensaje' => 'No había puestos pendientes de este pasajero en el viaje.'];
        }

        return [
            'ok'       => true,
            'marcadas' => $n,
            'mensaje'  => sprintf('Se registró la no-presentación de %d puesto(s).', $n),
        ];
    }

    /**
     * Avisa a quienes tenían puesto y no embarcaron de que su viaje ya salió.
     *
     * Se llama al marcar el viaje «En curso». `aviso_viaje_perdido` evita que
     * se repita en cada sincronización de estado, que ocurre cada vez que
     * alguien abre cualquier módulo.
     *
     * @return array{ok:bool, mensaje:string, avisados:int}
     */
    public static function avisarViajePerdido(int $idViaje): array
    {
        $pendientes = self::pendientesDeEmbarque($idViaje);
        if (empty($pendientes)) {
            return ['ok' => true, 'avisados' => 0, 'mensaje' => 'No quedó nadie sin embarcar en este viaje.'];
        }

        $viaje = Database::one(
            "SELECT v.fec_via, v.hor_sal_via, r.nom_rut, r.ori_rut, r.des_rut
               FROM viaje v LEFT JOIN rutas r ON r.id_rut = v.id_rut_via
              WHERE v.id_via = ?",
            [$idViaje]
        );
        if (!$viaje) return ['ok' => false, 'avisados' => 0, 'mensaje' => 'El viaje no existe.'];

        $ruta  = trim(($viaje['nom_rut'] ?? '') . ' (' . ($viaje['ori_rut'] ?? '?') . ' → ' . ($viaje['des_rut'] ?? '?') . ')');
        $salida = Fecha::legible((string)$viaje['fec_via'] . ' ' . substr((string)$viaje['hor_sal_via'], 0, 5));

        // Se agrupa por pasajero: con 3 puestos son 3 filas y solo debe sonar
        // un aviso, no tres.
        $porPasajero = [];
        foreach ($pendientes as $p) {
            $porPasajero[(int)$p['id_usu_res']][] = $p;
        }

        $avisados = 0;
        foreach ($porPasajero as $idUsu => $filas) {
            $puestos = count($filas);
            $debe = 0.0;
            foreach ($filas as $f) $debe += (float)($f['valor_pagado'] ?? 0);

            $titulo  = 'Tu viaje salió sin ti';
            $cuerpo  = sprintf(
                "El viaje #%d (%s) salió a las %s del %s y no te presentaste al paradero.\n\n"
              . "Puestos que tenías: %d\n"
              . "Tu reserva queda marcada como NO PRESENTACIÓN.\n\n"
              . "Si ya habías pagado, ese importe no se devuelve: el puesto se libera y "
              . "queda disponible para otro pasajero.\n"
              . "Si crees que es un error, revisa este aviso en tu buzón y lo revisamos.\n\n— Equipo SGET",
                $idViaje, $ruta, substr((string)$viaje['hor_sal_via'], 0, 5), $salida,
                $puestos
            );

            try {
                NotificacionService::enviar(
                    $idUsu,
                    NotificacionService::TIPO_VIAJE_PERDIDO,
                    $titulo,
                    $cuerpo,
                    $idViaje,
                    'viaje_perdido:' . $idViaje . ':' . $idUsu
                );

                // Se sellan los puestos para no volver a avisar.
                Database::query(
                    "UPDATE reserva SET aviso_viaje_perdido = 1
                      WHERE id_via_res = ? AND id_usu_res = ? AND aviso_viaje_perdido = 0",
                    [$idViaje, $idUsu]
                );
                $avisados++;
            } catch (Throwable $e) {
                error_log('[SGET][ReservaService::avisarViajePerdido] ' . $e->getMessage());
            }
        }

        return [
            'ok'       => true,
            'avisados' => $avisados,
            'mensaje'  => $avisados > 0
                ? sprintf('Se avisó a %d pasajero(s) de que su viaje salió sin ellos.', $avisados)
                : 'No había pendientes que avisar.',
        ];
    }

    /* ================================================================== */
    /* Cancelación                                                          */
    /* ================================================================== */

    /**
     * Cancela un puesto reservado y devuelve el cupo al viaje.
     *
     * @return array{ok:bool, mensaje:string}
     */
    public static function cancelar(int $idReserva, string $motivo = ''): array
    {
        $res = self::porId($idReserva);
        if (!$res) return ['ok' => false, 'mensaje' => 'La reserva no existe.'];
        if ($res['estado_pago'] === Config::RES_CANCELADA) {
            return ['ok' => false, 'mensaje' => 'Esa reserva ya estaba cancelada.'];
        }

        try {
            Database::begin();
            Database::query(
                "UPDATE reserva
                    SET estado_pago = ?, motivo_cancelacion = ?, cancelado_por = ?, fec_cancelacion = NOW()
                  WHERE id_res = ?",
                [Config::RES_CANCELADA, trim($motivo) !== '' ? trim($motivo) : null, Auth::id() ?: null, $idReserva]
            );
            self::recalcularCupos((int)$res['id_via_res']);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            error_log('[SGET][ReservaService::cancelar] ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo cancelar la reserva.'];
        }

        NotificacionService::notificarReservaCancelada(
            (int)$res['id_usu_res'],
            $idReserva,
            (int)$res['id_via_res'],
            trim(($res['nom_rut'] ?? '') . ' (' . ($res['ori_rut'] ?? '?') . ' → ' . ($res['des_rut'] ?? '?') . ')'),
            $motivo
        );

        Logger::registrar(Database::pdo(), 'CANCELAR_RESERVA', sprintf(
            'Reserva #%d cancelada por %s. Motivo: %s.',
            $idReserva, Auth::nombre(), $motivo !== '' ? $motivo : '—'
        ));

        return ['ok' => true, 'mensaje' => 'Reserva cancelada; el cupo volvió al viaje y el pasajero fue avisado.'];
    }

    /**
     * Cancela TODOS los puestos vivos de un pasajero en un viaje.
     *
     * Es la operación que usa el recaudo y el manifiesto, que trabajan por
     * persona. Cancelar por filas obligaba a repetir la acción por cada
     * asiento y era fácil dejar la mitad de una reserva viva.
     *
     * @return array{ok:bool, mensaje:string, canceladas:int}
     */
    public static function cancelarPuestosDe(int $idViaje, int $idPasajero, string $motivo = ''): array
    {
        $puestos = Database::all(
            "SELECT id_res FROM reserva
              WHERE id_via_res = ? AND id_usu_res = ? AND estado_pago <> ?
              ORDER BY id_res ASC",
            [$idViaje, $idPasajero, Config::RES_CANCELADA]
        );

        if (empty($puestos)) {
            return ['ok' => false, 'canceladas' => 0,
                    'mensaje' => 'Este pasajero no tiene puestos activos en ese viaje.'];
        }

        $n = 0;
        foreach ($puestos as $p) {
            $r = self::cancelar((int)$p['id_res'], $motivo);
            if ($r['ok']) $n++;
        }

        if ($n === 0) {
            return ['ok' => false, 'canceladas' => 0, 'mensaje' => 'No se pudo cancelar ningún puesto.'];
        }

        return [
            'ok'         => true,
            'canceladas' => $n,
            'mensaje'    => sprintf('Se cancelaron %d puesto(s); volvieron al bus y el pasajero fue avisado.', $n),
        ];
    }

    /* ================================================================== */
    /* Pasajero ocasional (recaudo sin cuenta registrada)                   */
    /* ================================================================== */

    /**
     * Crea (o reutiliza) un usuario pasajero para una atención en mostrador.
     * Se usa en el recaudo de personas que no se han registrado nunca.
     *
     * @return array{ok:bool, mensaje:string, id:int}
     */
    public static function pasajeroOcasional(string $nombre, string $documento = '', string $telefono = ''): array
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return ['ok' => false, 'mensaje' => 'Escribe el nombre del pasajero.', 'id' => 0];
        }

        if ($documento !== '') {
            $existente = Database::one(
                "SELECT id_usu FROM usuario WHERE num_doc_usu = ? AND id_rol_usu = ?",
                [$documento, Config::ROL_PASAJERO]
            );
            if ($existente) return ['ok' => true, 'id' => (int)$existente['id_usu'], 'mensaje' => 'Pasajero ya registrado.'];
        }

        $doc = $documento !== '' ? $documento : 'OCAS-' . date('ymd') . '-' . random_int(1000, 9999);
        $correo = 'ocasional-' . preg_replace('/\D+/', '', $doc) . '@sget.local';

        try {
            $id = Database::insert(
                "INSERT INTO usuario
                    (num_doc_usu, tip_doc_usu, nom_usu, corre_usu, tel_usu, id_rol_usu, pass_usu, estado)
                 VALUES (?, 'CC', ?, ?, ?, ?, ?, ?)",
                [
                    $doc, $nombre, $correo,
                    $telefono !== '' ? $telefono : null,
                    Config::ROL_PASAJERO,
                    Password::hash(Password::aleatoria()),
                    Config::USU_ACTIVO,
                ]
            );
        } catch (Throwable $e) {
            error_log('[SGET][ReservaService::pasajeroOcasional] ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo registrar el pasajero ocasional.', 'id' => 0];
        }

        return ['ok' => true, 'id' => $id, 'mensaje' => 'Pasajero ocasional registrado.'];
    }

    /** Registra en la ruta a una persona que aborda durante un viaje en curso. */
    public static function agregarTemporalEnRuta(int $idViaje, array $datos): array
    {
        $falla = static fn(string $mensaje): array => ['ok' => false, 'mensaje' => $mensaje, 'id_res' => 0];
        if (Auth::rol() !== Config::ROL_CONDUCTOR || Auth::id() <= 0) {
            return $falla('Solo el conductor asignado puede agregar pasajeros en ruta.');
        }

        $nombre = trim((string)($datos['nombre'] ?? ''));
        $documento = strtoupper(trim((string)($datos['documento'] ?? '')));
        $telefono = trim((string)($datos['telefono'] ?? ''));
        $origen = trim((string)($datos['punto_abordaje'] ?? ''));
        $destino = trim((string)($datos['destino_abordaje'] ?? ''));
        $metodo = trim((string)($datos['metodo_pago'] ?? ''));
        $valor = (float)($datos['valor_pagado'] ?? 0);

        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100) {
            return $falla('Escribe el nombre del pasajero (3 a 100 caracteres).');
        }
        if (mb_strlen($documento) > 30 || mb_strlen($telefono) > 30) {
            return $falla('El documento o el teléfono excede el tamaño permitido.');
        }
        if ($origen === '' || mb_strlen($origen) > 120 || $destino === '' || mb_strlen($destino) > 120) {
            return $falla('Indica un punto de abordaje y un destino válidos.');
        }
        if ($valor <= 0 || $valor > 99999999) {
            return $falla('El valor del pasaje debe ser mayor que cero.');
        }
        if (!in_array($metodo, ['Efectivo', 'Pago al abordar'], true)) {
            return $falla('Selecciona Efectivo o Pago al abordar.');
        }

        $conductorId = Auth::id();
        $pagadoAhora = $metodo === 'Efectivo';
        $estadoPago = $pagadoAhora ? Config::RES_CONFIRMADA : Config::RES_PENDIENTE;
        $marcaTiempo = date('Y-m-d H:i:s');

        try {
            Database::begin();
            $viaje = Database::one(
                'SELECT v.id_via, v.id_usu_via, v.est_via, v.cup_tot,
                        r.nom_rut, r.ori_rut, r.des_rut
                   FROM viaje v
                   LEFT JOIN rutas r ON r.id_rut = v.id_rut_via
                  WHERE v.id_via = ? FOR UPDATE',
                [$idViaje]
            );
            if (!$viaje || (int)$viaje['id_usu_via'] !== $conductorId) {
                Database::rollback();
                return $falla('Solo puedes agregar pasajeros a tus propios viajes.');
            }
            if ((string)$viaje['est_via'] !== Config::VIA_EN_CURSO) {
                Database::rollback();
                return $falla('Solo se pueden agregar pasajeros cuando el viaje está en curso.');
            }

            $ocupados = (int) Database::scalar(
                'SELECT COALESCE(SUM(cantidad_puestos), 0) FROM reserva WHERE id_via_res = ? AND estado_pago <> ?',
                [$idViaje, Config::RES_CANCELADA]
            );
            if ($ocupados >= (int)$viaje['cup_tot']) {
                Database::rollback();
                return $falla('El viaje ya no tiene cupos disponibles.');
            }

            $pasajero = self::pasajeroOcasional($nombre, $documento, $telefono);
            if (empty($pasajero['ok'])) {
                Database::rollback();
                return $falla((string)$pasajero['mensaje']);
            }
            $idPasajero = (int)$pasajero['id'];
            $duplicada = (int) Database::scalar(
                'SELECT COUNT(*) FROM reserva
                  WHERE id_via_res = ? AND id_usu_res = ? AND estado_pago <> ?',
                [$idViaje, $idPasajero, Config::RES_CANCELADA]
            );
            if ($duplicada > 0) {
                Database::rollback();
                return $falla('Este pasajero ya tiene una reserva activa para este viaje.');
            }

            $idReserva = Database::insert(
                'INSERT INTO reserva
                    (id_via_res, id_usu_res, fech_res, metodo_pago, valor_pagado, estado_pago,
                     fecha_pago, embarco, embarque_por, embarque_fec, es_temporal,
                     punto_abordaje, destino_abordaje, registrada_por)
                 VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
                [
                    $idViaje,
                    $idPasajero,
                    $metodo,
                    $valor,
                    $estadoPago,
                    $pagadoAhora ? $marcaTiempo : null,
                    $pagadoAhora ? 1 : null,
                    $pagadoAhora ? $conductorId : null,
                    $pagadoAhora ? $marcaTiempo : null,
                    $origen,
                    $destino,
                    $conductorId,
                ]
            );
            Database::query('UPDATE viaje SET cup_dis = ? WHERE id_via = ?', [
                max(0, (int)$viaje['cup_tot'] - $ocupados - 1), $idViaje,
            ]);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            error_log('[SGET][ReservaService::agregarTemporalEnRuta] ' . $e->getMessage());
            return $falla('No se pudo registrar al pasajero temporal. Inténtalo de nuevo.');
        }

        $conductor = Auth::nombre();
        $hora = date('H:i:s');
        $ruta = trim((string)($viaje['nom_rut'] ?? '') . ' (' . (string)($viaje['ori_rut'] ?? '?') . ' → ' . (string)($viaje['des_rut'] ?? '?') . ')');
        $detalle = sprintf(
            'El Conductor %s agregó un pasajero temporal (Monto: $%s) en el Viaje #%d a las %s. Pasajero: %s. Abordaje: %s. Destino: %s. Método: %s. Ruta: %s.',
            $conductor,
            number_format($valor, 2, ',', '.'),
            $idViaje,
            $hora,
            $nombre,
            $origen,
            $destino,
            $pagadoAhora ? 'Efectivo pagado' : 'Pago al abordar pendiente',
            $ruta
        );

        $administradores = array_map(
            static fn(array $fila): int => (int)$fila['id_usu'],
            Database::all('SELECT id_usu FROM usuario WHERE id_rol_usu = ? AND estado = ?', [Config::ROL_ADMIN, Config::USU_ACTIVO])
        );
        NotificacionService::enviarVarios(
            $administradores,
            NotificacionService::TIPO_RECAUDO,
            'Pasajero temporal agregado en ruta',
            $detalle,
            $idViaje
        );
        Logger::registrar(Database::pdo(), 'PASAJERO_TEMPORAL_RUTA', $detalle, $conductorId, $conductor, 'Conductor');

        return [
            'ok' => true,
            'id_res' => $idReserva,
            'estado_pago' => $estadoPago,
            'embarco' => $pagadoAhora ? 1 : null,
            'mensaje' => $pagadoAhora
                ? 'Pasajero temporal registrado, pagado y abordado.'
                : 'Pasajero temporal registrado. El pago y el abordaje quedan pendientes de confirmación.',
        ];
    }

    /* ================================================================== */
    /* Interno                                                             */
    /* ================================================================== */

    private static function ocupadosEn(int $idViaje): int
    {
        return (int) Database::scalar(
            "SELECT COALESCE(SUM(cantidad_puestos), 0) FROM reserva WHERE id_via_res = ? AND estado_pago <> ?",
            [$idViaje, Config::RES_CANCELADA]
        );
    }

    /** Deja `cup_dis` igual a la realidad (cupos libres = total - reservados). */
    public static function recalcularCupos(int $idViaje): int
    {
        $totales = (int) Database::scalar("SELECT cup_tot FROM viaje WHERE id_via = ?", [$idViaje]);
        $libres  = max(0, $totales - self::ocupadosEn($idViaje));
        Database::query("UPDATE viaje SET cup_dis = ? WHERE id_via = ?", [$libres, $idViaje]);
        return $libres;
    }
}
