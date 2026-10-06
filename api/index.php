<?php
/**
 * api/index.php
 * -----------------------------------------------------------------------------
 * API ÚNICA DE SGET (JSON)
 * -----------------------------------------------------------------------------
 * Todo el CRUD de los módulos pasa por aquí:
 *     POST api/index.php?modulo=<ruta|vehiculo|usuario|viaje|notificacion>
 *                   &accion=<guardar|eliminar|cambiarEstado|cancelar|...>
 *
 * VENTAJAS frente a los N archivos `procesar_*.php` sueltos que había:
 *   - Una sola puerta de entrada → un solo punto donde aplicar autenticación,
 *     permisos, CSRF y formato de respuesta.
 *   - Respuesta uniforme: { status, mensaje, errores, datos, redirect }.
 *   - Los `procesar_*.php` antiguos se pueden ir borrando sin romper nada,
 *     porque nada depende ya de ellos.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';

// ---------------------------------------------------------------- Seguridad
Auth::requerirSesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Auth::json(['status' => 'error', 'mensaje' => 'Método no permitido.'], 405);
}

/** Token anti-CSRF (se emite en la sesión y viaja en _token). */
if (!Auth::validarToken((string)($_POST['_token'] ?? ''))) {
    // 403 y no 419: Apache rechaza los códigos no estándar y devolvía un 500
    // que el navegador mostraba como «error del servidor» en vez de «tu sesión
    // caducó», que es lo que había que arreglar.
    Auth::json(['status' => 'error', 'mensaje' => 'La sesión expiró o el formulario no es válido. Recarga la página.'], 403);
}

$modulo = (string)($_POST['modulo'] ?? $_GET['modulo'] ?? '');
$accion = (string)($_POST['accion'] ?? $_GET['accion'] ?? '');

if ($modulo === '' || $accion === '') {
    Auth::json(['status' => 'error', 'mensaje' => 'Falta el módulo o la acción solicitada.'], 400);
}

/**
 * Permiso de ENTRADA a cada módulo.
 *
 * Solo es la puerta general: la autorización fina vive en `Auth::exigir()`
 * dentro de cada `case`, que además puede exigir la propiedad del recurso
 * (que el conductor sea el dueño del viaje, que el usuario sea el dueño de la
 * notificación…). Ocultar el botón en el frontend NO cuenta como seguridad.
 */
$permisos = [
    'ruta'         => 'rutas',
    'vehiculo'     => 'vehiculos',
    'usuario'      => 'usuarios',
    'viaje'        => 'viajes',
    'notificacion' => null,   // el buzón es de todos los roles
    'anuncio'      => 'anuncios',
    'reserva'      => null,   // cada acción exige SU permiso (ver más abajo)
    'calificacion' => 'calificar',
    // El módulo de reportes NO tiene permiso único: `crear` es del pasajero y
    // el resto es del administrador (ver `Auth::PERMISOS_ACCION`).
    'reporte'      => null,
];

// El CONDUCTOR no administra la flota: entra al módulo de viajes solo para
// sus propios despachos, y eso se comprueba acción por acción más abajo.
$esConductorEnViajes = ($modulo === 'viaje' && Auth::rol() === Config::ROL_CONDUCTOR);

/* El conductor tampoco gestiona el recaudo, pero sí necesita el manifiesto y
   el registro de embarque de SUS viajes. Esas acciones llevan su propia
   comprobación de propiedad (`ViajeService::puedeVerManifiesto`), así que se
   deja pasar el módulo y se filtra dentro. */
$esConductorEnReservas = ($modulo === 'reserva' && Auth::rol() === Config::ROL_CONDUCTOR);

if (!empty($permisos[$modulo])
    && !$esConductorEnViajes
    && !$esConductorEnReservas
    && !Auth::tieneAcceso($permisos[$modulo])) {
    Auth::json(['status' => 'error',
                'mensaje' => 'No tienes permisos para gestionar ' . str_replace('_', ' ', $modulo) . '.'], 403);
}

/**
 * Resuelve a qué reserva apunta una petición de cobro o cancelación.
 *
 * Acepta las dos formas que usan las pantallas:
 *   · `id`        -> el id de UNA fila de reserva (un puesto suelto).
 *   · `id_grupo`  -> "idPasajero:idViaje", que es como el recaudo y el
 *                    manifiesto trabajan: POR PERSONA, no por fila. Como un
 *                    puesto es una fila, cobrar o cancelar por filas obligaba
 *                    al cajero a repetir la operación por cada asiento.
 *
 * @return array{0:int, 1:array{id:int,viaje:int}|null}  [idReserva, grupo]
 */
$destinoReserva = static function (string $id, string $grupo): array {
    $id = (int)$id;
    if ($id > 0) return [$id, null];

    if (preg_match('/^(\d+):(\d+)$/', $grupo, $m) === 1) {
        $pasajero = (int)$m[1];
        $viaje    = (int)$m[2];

        // Se agarra un puesto PENDIENTE del grupo: si el pasajero ya pagó
        // entero, la búsqueda no encuentra nada y el servicio lo explica con
        // un mensaje claro en vez de devolver un id equivocado.
        $fila = Database::one(
            "SELECT id_res FROM reserva
              WHERE id_usu_res = ? AND id_via_res = ? AND estado_pago = ?
              ORDER BY id_res ASC LIMIT 1",
            [$pasajero, $viaje, Config::RES_PENDIENTE]
        );
        if (!$fila) {
            $fila = Database::one(
                "SELECT id_res FROM reserva
                  WHERE id_usu_res = ? AND id_via_res = ?
                  ORDER BY id_res DESC LIMIT 1",
                [$pasajero, $viaje]
            );
        }

        return [(int)($fila['id_res'] ?? 0), ['id' => $pasajero, 'viaje' => $viaje]];
    }

    return [0, null];
};

// ---------------------------------------------------------------- Despacho
try {
    switch ($modulo) {

        /* ============================================================== */
        case 'ruta': {
            Auth::exigir('ruta', $accion);
            switch ($accion) {
                case 'guardar':
                    Auth::requerirAdmin();
                    $r = RutaService::guardar($_POST, $_FILES['img_rut'] ?? null);
                    if (!$r['ok']) {
                        Auth::json(['status' => 'error', 'mensaje' => $r['mensaje'], 'errores' => $r['errores'] ?? []], 422);
                    }
                    Auth::json(['status' => 'ok', 'mensaje' => $r['mensaje'], 'datos' => ['id' => $r['id']],
                                'redirect' => 'rutas.php?ok=' . urlencode($r['mensaje'])]);
                    break;

                case 'eliminar':
                    Auth::requerirAdmin();
                    $r = RutaService::eliminar((int)($_POST['id'] ?? 0));
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje'], 'redirect' => 'rutas.php?ok=' . urlencode($r['mensaje'])]
                        : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 409);
                    break;

                case 'cambiarEstado':
                    Auth::requerirAdmin();
                    RutaService::cambiarEstado((int)($_POST['id'] ?? 0), (int)($_POST['estado'] ?? 1));
                    Auth::json(['status' => 'ok', 'mensaje' => 'Estado de la ruta actualizado.',
                                'redirect' => 'rutas.php?ok=Estado+de+la+ruta+actualizado']);
                    break;
            }
            break;
        }

        /* ============================================================== */
        case 'vehiculo': {
            Auth::exigir('vehiculo', $accion);
            switch ($accion) {
                case 'guardar':
                    Auth::requerirAdmin();
                    $r = VehiculoService::guardar($_POST);
                    if (!$r['ok']) {
                        Auth::json(['status' => 'error', 'mensaje' => $r['mensaje'], 'errores' => $r['errores'] ?? []], 422);
                    }
                    Auth::json(['status' => 'ok', 'mensaje' => $r['mensaje'],
                                'redirect' => 'vehiculos.php?ok=' . urlencode($r['mensaje'])]);
                    break;

                case 'alternarEstado':
                    Auth::requerirAdmin();
                    $r = VehiculoService::alternarEstado((int)($_POST['id'] ?? 0));
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje'], 'redirect' => 'vehiculos.php?ok=' . urlencode($r['mensaje'])]
                        : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 409);
                    break;

                case 'eliminar':
                    Auth::requerirAdmin();
                    $r = VehiculoService::eliminar((int)($_POST['id'] ?? 0));
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje'], 'redirect' => 'vehiculos.php?ok=' . urlencode($r['mensaje'])]
                        : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 409);
                    break;
            }
            break;
        }

        /* ============================================================== */
        case 'usuario': {
            Auth::exigir('usuario', $accion);
            switch ($accion) {
                case 'guardar':
                    Auth::requerirAdmin();
                    $r = UsuarioService::guardar($_POST);
                    if (!$r['ok']) {
                        Auth::json(['status' => 'error', 'mensaje' => $r['mensaje'], 'errores' => $r['errores'] ?? []], 422);
                    }
                    Auth::json(['status' => 'ok', 'mensaje' => $r['mensaje'],
                                'redirect' => 'usuarios.php?ok=' . urlencode($r['mensaje'])]);
                    break;

                case 'cambiarEstado':
                    Auth::requerirAdmin();
                    $r = UsuarioService::cambiarEstado(
                        (int)($_POST['id'] ?? 0),
                        (int)($_POST['estado'] ?? Config::USU_ACTIVO),
                        trim((string)($_POST['motivo'] ?? ''))
                    );
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje'], 'redirect' => 'usuarios.php?ok=' . urlencode($r['mensaje'])]
                        : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 409);
                    break;

                case 'eliminar':
                    Auth::requerirAdmin();
                    $r = UsuarioService::eliminar((int)($_POST['id'] ?? 0));
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje'], 'redirect' => 'usuarios.php?ok=' . urlencode($r['mensaje'])]
                        : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 409);
                    break;
            }
            break;
        }

        /* ============================================================== */
        case 'viaje': {
            switch ($accion) {
                /* ------------------------------------------------------------------
                 * DISPONIBILIDAD EN VIVO
                 * ------------------------------------------------------------------
                 * Alimenta los selectores del modal de viaje. Devuelve TODOS los
                 * conductores y vehículos con un indicador `disponible` calculado
                 * por `DisponibilidadService` — la MISMA función que valida al
                 * guardar. Así lo que el administrador ve y lo que el backend
                 * acepta no pueden discrepar. Solo lectura; no cambia nada.
                 */
                case 'disponibles': {
                    Auth::requerirAdmin();

                    $fec = trim((string)($_POST['fec_via'] ?? ''));
                    $hor = trim((string)($_POST['hor_sal_via'] ?? ''));
                    if ($fec === '') $fec = date('Y-m-d');
                    if ($hor === '') $hor = date('H:i:s');

                    try {
                        $fec = Fecha::fecha($fec);
                        $hor = (string)Fecha::hora($hor, 'hora de salida', true);
                    } catch (ValueError $e) {
                        Auth::json(['status' => 'error', 'mensaje' => 'Fecha u hora no válidas.'], 422);
                    }

                    $excepto = (int)($_POST['id_via'] ?? 0);

                    Auth::json(['status' => 'ok', 'datos' => [
                        'conductores' => DisponibilidadService::conductores($fec, $hor, $excepto),
                        'vehiculos'   => DisponibilidadService::vehiculos($fec, $hor, $excepto),
                        'margen_min'  => Config::MARGEN_DISPONIBILIDAD_MIN,
                    ]]);
                    break;
                }

                case 'guardar':
                    Auth::requerirAdmin();
                    $r = ViajeService::guardar($_POST);
                    if (!$r['ok']) {
                        Auth::json(['status' => 'error', 'mensaje' => $r['mensaje'], 'errores' => $r['errores'] ?? []], 422);
                    }
                    Auth::json(['status' => 'ok', 'mensaje' => $r['mensaje'],
                                'redirect' => 'viajes.php?ok=' . urlencode($r['mensaje'])]);
                    break;

                case 'finalizar': {
                    Auth::exigirViaje($accion, (int)($_POST['id'] ?? 0));
                    $r = ViajeService::finalizar((int)($_POST['id'] ?? 0));
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje'], 'redirect' => 'viajes.php?ok=' . urlencode($r['mensaje'])]
                        : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 409);
                    break;
                }

                case 'enCurso': {
                    Auth::exigirViaje($accion, (int)($_POST['id'] ?? 0));
                    $r = ViajeService::marcarEnCurso((int)($_POST['id'] ?? 0));
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje'], 'redirect' => 'viajes.php?ok=' . urlencode($r['mensaje'])]
                        : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 409);
                    break;
                }

                /** CANCELACIÓN · con anotación obligatoria si aún no salió */
                case 'cancelar': {
                    Auth::exigirViaje($accion, (int)($_POST['id'] ?? 0));
                    $r = ViajeService::cancelar(
                        (int)($_POST['id'] ?? 0),
                        (string)($_POST['motivo'] ?? ''),
                        (string)($_POST['anotacion'] ?? '')
                    );
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje'],
                           'datos' => ['notificados' => $r['notificados'] ?? 0],
                           'redirect' => 'viajes.php?ok=' . urlencode($r['mensaje'])]
                        : ['status' => 'error', 'mensaje' => $r['mensaje'],
                           'errores' => ['anotacion_cancelacion' => $r['mensaje']]], $r['ok'] ? 200 : 422);
                    break;
                }
            }
            break;
        }

        /* ============================================================== */
        case 'notificacion': {
            $idUsuario = Auth::id();

            if ($accion === 'listar') {
                Auth::json(['status' => 'ok', 'datos' => NotificacionService::bandeja($idUsuario, [
                    'tipo'         => (string)($_POST['tipo'] ?? ''),
                    'soloNoLeidas' => !empty($_POST['soloNoLeidas']),
                    'limite'       => (int)($_POST['limite'] ?? 40),
                ])]);
            }

            // Contador para el badge de la cabecera. Se llama al cargar la página
            // y luego de forma periódica: el usuario se entera de un aviso sin
            // tener que recargar a mano.
            if ($accion === 'contador') {
                Auth::json(['status' => 'ok', 'datos' => [
                    'no_leidas' => NotificacionService::noLeidas($idUsuario),
                ]]);
            }

            if ($accion === 'leer') {
                $ok = NotificacionService::marcarLeida((int)($_POST['id'] ?? 0), $idUsuario);
                Auth::json(['status' => 'ok', 'mensaje' => $ok ? 'Notificación marcada como leída.' : 'No se pudo marcar como leída.',
                            'datos' => ['no_leidas' => NotificacionService::noLeidas($idUsuario)]]);
            }

            if ($accion === 'leerTodas') {
                $n = NotificacionService::marcarTodasLeidas($idUsuario);
                Auth::json(['status' => 'ok', 'mensaje' => $n > 0
                    ? "Marcaste {$n} notificación(es) como leídas."
                    : 'No había notificaciones sin leer.',
                    'datos' => ['no_leidas' => NotificacionService::noLeidas($idUsuario)]]);
            }

            if ($accion === 'eliminar') {
                $ok = NotificacionService::eliminar((int)($_POST['id'] ?? 0), $idUsuario);
                Auth::json(['status' => 'ok', 'mensaje' => $ok ? 'Notificación eliminada.' : 'No se pudo eliminar.',
                            'datos' => ['no_leidas' => NotificacionService::noLeidas($idUsuario)]]);
            }

            if ($accion === 'vaciar') {
                $n = NotificacionService::vaciar($idUsuario);
                Auth::json(['status' => 'ok', 'mensaje' => "Buzón vacío ({$n} aviso(s) eliminado(s)).",
                            'datos' => ['no_leidas' => 0]]);
            }

            /* Recordatorio de salida: se dispara al abrir cualquier módulo y
               genera UN aviso por viaje que sale en los próximos minutos, para
               el pasajero reservado y para el conductor. La columna `firma`
               evita que se repita en cada recarga. */
            if ($accion === 'recordatorios') {
                $n = NotificacionService::recordatorios($idUsuario, (int)($_POST['minutos'] ?? 90));
                Auth::json(['status' => 'ok', 'mensaje' => $n > 0 ? "Tienes {$n} salida(s) próxima(s)." : 'Sin salidas próximas.',
                            'datos' => ['creados' => $n, 'no_leidas' => NotificacionService::noLeidas($idUsuario)]]);
            }

            /* Difusión de un comunicado: el admin avisa a uno o varios roles de
               golpe (suspensión por lluvia, cambio de tarifa,Reminder de
               mantenimiento…). Se separa de las acciones del buzón porque
               escribe en el buzón de OTROS usuarios, no en el propio. */
            if ($accion === 'difundir') {
                Auth::requerirAdmin();
                Auth::requerirAcceso('comunicados');

                $titulo = trim((string)($_POST['titulo'] ?? ''));
                $cuerpo = trim((string)($_POST['cuerpo'] ?? ''));
                $roles  = array_values(array_filter(array_map('intval', (array)($_POST['roles'] ?? []))));

                if ($titulo === '' || mb_strlen($titulo) > 150) {
                    Auth::json(['status' => 'error', 'mensaje' => 'El asunto es obligatorio (máx. 150 caracteres).',
                                'errores' => ['titulo' => 'Escribe un asunto de hasta 150 caracteres.']], 422);
                }
                if (mb_strlen($cuerpo) < 15) {
                    Auth::json(['status' => 'error', 'mensaje' => 'El mensaje es demasiado corto para ser útil (mínimo 15 caracteres).',
                                'errores' => ['cuerpo' => 'Escribe un mensaje de al menos 15 caracteres.']], 422);
                }
                if (!$roles) {
                    Auth::json(['status' => 'error', 'mensaje' => 'Selecciona al menos un destinatario.',
                                'errores' => ['rol' => 'Selecciona al menos un destinatario.']], 422);
                }

                $firmados = 0;
                $usuarios = [];
                foreach ($roles as $rol) {
                    $r = NotificacionService::difundir(['rol' => $rol], $titulo, $cuerpo);
                    $firmados += (int)$r['enviados'];
                    $usuarios[] = $rol;
                }

                $nombres = array_map(
                    static fn(int $r) => $r === Config::ROL_CONDUCTOR ? 'los conductores' : ($r === Config::ROL_PASAJERO ? 'los pasajeros' : 'los usuarios'),
                    $usuarios
                );

                Auth::json([
                    'status'  => 'ok',
                    'mensaje' => sprintf('Comunicado enviado a %d usuario(s) (%s).', $firmados, implode(' y ', $nombres)),
                    'datos'   => ['enviados' => $firmados, 'no_leidas' => NotificacionService::noLeidas($idUsuario)],
                ]);
            }

            break;
        }

        /* ============================================================== */
        case 'calificacion': {
            // Solo el pasajero califica, y solo un viaje propio. El servicio
            // vuelve a comprobarlo: la API no confía en el formulario.
            if ($accion === 'registrar') {
                if (Auth::rol() !== Config::ROL_PASAJERO) {
                    Auth::json(['status' => 'error', 'mensaje' => 'Solo los pasajeros pueden calificar un viaje.'], 403);
                }
                $r = CalificacionService::registrar(
                    Auth::id(),
                    (int)($_POST['id_via_cal'] ?? 0),
                    (int)($_POST['pun_cal'] ?? 0),
                    (string)($_POST['com_cal'] ?? '')
                );
                Auth::json($r['ok']
                    ? ['status' => 'ok', 'mensaje' => $r['mensaje']]
                    : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 422);
            }

            if ($accion === 'pendientes') {
                Auth::json(['status' => 'ok', 'datos' => ['viajes' => CalificacionService::pendientes(Auth::id())]]);
            }
            break;
        }

        /* ============================================================== */
        case 'reporte': {
            /* Reportes y quejas de pasajeros.
               ACCIONES SEPARADAS (antestodas caían bajo un `requerirAdmin()`
               que hacía inalcanzable `crear` para el pasajero):

                   reporte.crear      → PASAJERO    (permiso `crear_reporte`)
                   reporte.consultar  → ADMIN       (`gestionar_reportes_pasajeros`)
                   reporte.actualizar → ADMIN       (`gestionar_reportes_pasajeros`)
                   reporte.eliminar   → ADMIN       (`gestionar_reportes_pasajeros`)
            */
            Auth::exigir('reporte', $accion);

            if ($accion === 'crear') {
                // Solo un pasajero de verdad abre reportes. Si el administrador
                // quiere registrar uno, se hace desde la interfaz de soporte.
                if (Auth::rol() !== Config::ROL_PASAJERO) {
                    Auth::json(['status' => 'error',
                                'mensaje' => 'Solo los pasajeros pueden crear reportes desde su panel.'], 403);
                }
                $r = ReporteService::crear(
                    Auth::id(),
                    (int)($_POST['id_via'] ?? 0),
                    (string)($_POST['descripcion'] ?? '')
                );
                Auth::json($r['ok']
                    ? ['status' => 'ok', 'mensaje' => $r['mensaje'], 'datos' => ['id' => $r['id']]]
                    : ['status' => 'error', 'mensaje' => $r['mensaje'], 'errores' => ['descripcion' => $r['mensaje']]],
                    $r['ok'] ? 200 : 422);
            }

            if ($accion === 'misReportes') {
                Auth::json(['status' => 'ok', 'datos' => [
                    'reportes' => ReporteService::porPasajero(Auth::id()),
                ]]);
            }

            if ($accion === 'actualizar') {
                Auth::requerirAdmin();
                $id     = (int)($_POST['id'] ?? 0);
                $estado = (string)($_POST['estado'] ?? ReporteService::ESTADO_PENDIENTE);
                $idVia  = (int)($_POST['id_via'] ?? 0);

                if (!in_array($estado, ReporteService::ESTADOS, true)) {
                    Auth::json(['status' => 'error', 'mensaje' => 'El estado del reporte no es válido.'], 422);
                }

                $r = ReporteService::actualizar($id, $estado, $idVia);
                Auth::json($r['ok']
                    ? ['status' => 'ok', 'mensaje' => $r['mensaje']]
                    : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 404);
            }

            if ($accion === 'eliminar') {
                Auth::requerirAdmin();
                $r = ReporteService::eliminar((int)($_POST['id'] ?? 0));
                Auth::json($r['ok']
                    ? ['status' => 'ok', 'mensaje' => $r['mensaje']]
                    : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 404);
            }
            break;
        }

        /* ============================================================== */
        case 'anuncio': {
            // El módulo de anuncios es 100% del administrador.
            Auth::requerirAdmin();
            Auth::exigir('anuncio', $accion);

            switch ($accion) {
                case 'guardar':
                    $r = AnuncioService::guardar($_POST, $_FILES['imagen'] ?? null);
                    if (!$r['ok']) {
                        Auth::json(['status' => 'error', 'mensaje' => $r['mensaje'], 'errores' => $r['errores']], 422);
                    }
                    Auth::json(['status' => 'ok', 'mensaje' => $r['mensaje'],
                                'datos' => ['id' => $r['id']]]);
                    break;

                case 'alternarEstado':
                case 'alternarDestacado':
                    $r = $accion === 'alternarEstado'
                        ? AnuncioService::alternarEstado((int)($_POST['id'] ?? 0))
                        : AnuncioService::alternarDestacado((int)($_POST['id'] ?? 0));
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje']]
                        : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 404);
                    break;

                case 'eliminar':
                    $r = AnuncioService::eliminar((int)($_POST['id'] ?? 0));
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje']]
                        : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 404);
                    break;

                case 'listar':
                    Auth::json(['status' => 'ok', 'datos' => ['anuncios' => AnuncioService::todos()]]);
                    break;
            }
            break;
        }

        /* ============================================================== */
        case 'reserva': {
                /* El conductor solo llega a acciones de sus propios viajes;
                    todas vuelven a comprobar propiedad antes de leer o escribir. */
                    if (!in_array($accion, ['manifiesto', 'embarcar', 'noPresente', 'cobrarYEmbarcar', 'misEstados', 'pasajeroEnRuta'], true)) {
                Auth::exigir('reserva', $accion);
            }
            switch ($accion) {
                    case 'pasajeroEnRuta': {
                        Auth::requerirRol(Config::ROL_CONDUCTOR);
                        $idViaje = (int)($_POST['id_via'] ?? 0);
                        if (!ViajeService::puedeVerManifiesto($idViaje)) {
                            Auth::json(['status' => 'error',
                                        'mensaje' => 'Solo puedes agregar pasajeros a los viajes que conduces.'], 403);
                        }
                        $r = ReservaService::agregarTemporalEnRuta($idViaje, [
                            'nombre' => (string)($_POST['nombre'] ?? ''),
                            'documento' => (string)($_POST['documento'] ?? ''),
                            'telefono' => (string)($_POST['telefono'] ?? ''),
                            'punto_abordaje' => (string)($_POST['punto_abordaje'] ?? ''),
                            'destino_abordaje' => (string)($_POST['destino_abordaje'] ?? ''),
                            'valor_pagado' => (float)($_POST['valor_pagado'] ?? 0),
                            'metodo_pago' => (string)($_POST['metodo_pago'] ?? ''),
                        ]);
                        Auth::json($r['ok']
                            ? ['status' => 'ok', 'mensaje' => $r['mensaje'], 'datos' => ['id_res' => $r['id_res']]]
                            : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 409);
                        break;
                    }
                case 'misEstados': {
                    if (Auth::rol() !== Config::ROL_PASAJERO) {
                        Auth::json(['status' => 'error', 'mensaje' => 'Esta consulta es solo para pasajeros.'], 403);
                    }
                    $estados = Database::all(
                        'SELECT id_res, estado_pago, metodo_pago, embarco, embarque_fec
                           FROM reserva
                          WHERE id_usu_res = ?
                                                    ORDER BY id_res DESC',
                        [Auth::id()]
                    );
                    Auth::json(['status' => 'ok', 'datos' => ['reservas' => $estados]]);
                    break;
                }

                /* Pasajero que paga en mostrador sin tener cuenta: se le crea una
                   ficha mínima para que la reserva y el cobro queden trazables. */
                case 'ocasional': {
                    $alta = ReservaService::pasajeroOcasional(
                        (string)($_POST['nombre_ocasional'] ?? ''),
                        (string)($_POST['doc_ocasional'] ?? ''),
                        (string)($_POST['tel_ocasional'] ?? '')
                    );
                    if (!$alta['ok']) {
                        Auth::json(['status' => 'error', 'mensaje' => $alta['mensaje'],
                                    'errores' => ['nombre_ocasional' => $alta['mensaje']]], 422);
                    }
                    $_POST['id_usu'] = $alta['id'];
                    $accion = 'crear';   // se sigue con el alta de la reserva
                    break;
                }

                case 'crear': {
                    /* El pasajero solo puede reservar PARA SÍ MISMO: el id de
                       usuario lo pone el servidor, nunca el formulario. El
                       administrador (recaudo en terminal) sí puede registrar
                       la reserva de otra persona. */
                    $esAdmin = Auth::rol() === Config::ROL_ADMIN;
                    $idPasajero = $esAdmin
                        ? (int)($_POST['id_usu'] ?? Auth::id())
                        : Auth::id();

                    if ($idPasajero <= 0) {
                        Auth::json(['status' => 'error',
                                    'mensaje' => 'Debes iniciar sesión para reservar un puesto.'], 401);
                    }

                    $r = ReservaService::crear(
                        (int)($_POST['id_via'] ?? 0),
                        $idPasajero,
                        max(1, min(20, (int)($_POST['puestos'] ?? 1))),
                        [
                            'metodo'   => (string)($_POST['metodo_pago'] ?? 'Efectivo'),
                            'valor'    => (float)($_POST['valor_pagado'] ?? 0),
                            // Solo el administrador confirma el pago en el
                            // momento; el pasajero deja la reserva pendiente
                            // para cobrar en terminal.
                            'confirmar' => $esAdmin && !empty($_POST['confirmar']),
                        ]
                    );
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje'], 'datos' => ['ids' => $r['ids'], 'puestos' => $r['puestos']]]
                        : ['status' => 'error', 'mensaje' => $r['mensaje'], 'errores' => ['general' => $r['mensaje']]],
                        $r['ok'] ? 200 : 409);
                    break;
                }

                case 'cobrar':
                    Auth::requerirAcceso('asignaciones');
                    // El id puede venir como fila (id) o como grupo pasajero+viaje
                    // (id_grupo = "pasajero:viaje"). El grupo es lo que usa el
                    // recaudo: un puesto es una fila, así que cobrar por filas
                    // obligaba a repetir el cobro por cada asiento.
                    [$idFila, $idGrupo] = $destinoReserva((string)($_POST['id'] ?? ''), (string)($_POST['id_grupo'] ?? ''));
                    $r = ReservaService::confirmarPago(
                        $idFila,
                        (float)($_POST['valor'] ?? 0),
                        (string)($_POST['metodo'] ?? 'Efectivo'),
                        !empty($_POST['todos_puestos'])
                    );
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje'],
                           'datos' => ['cobradas' => $r['cobradas'] ?? 1]]
                        : ['status' => 'error', 'mensaje' => $r['mensaje'],
                           'errores' => ['valor' => $r['mensaje']]], $r['ok'] ? 200 : 409);
                    break;

                case 'cobrarYEmbarcar': {
                    Auth::requerirRol(Config::ROL_CONDUCTOR);
                    $idViaje = (int)($_POST['id_via'] ?? 0);
                    if (!ViajeService::puedeVerManifiesto($idViaje)) {
                        Auth::json(['status' => 'error',
                                    'mensaje' => 'Solo puedes confirmar pagos de los viajes que conduces.'], 403);
                    }
                    $r = ReservaService::cobrarYEmbarcarPasajero(
                        $idViaje,
                        (int)($_POST['id_usu'] ?? 0)
                    );
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje'],
                           'datos' => ['cobradas' => $r['cobradas'] ?? 0]]
                        : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 409);
                    break;
                }

                /* --- Manifestación: quién tiene puesto en cada viaje --- */
                case 'manifiesto': {
                    $idViaje = (int)($_POST['id'] ?? 0);
                    if (!ViajeService::puedeVerManifiesto($idViaje)) {
                        Auth::json(['status' => 'error',
                                    'mensaje' => 'Solo puedes ver los pasajeros de los viajes que conduces.'], 403);
                    }
                    Auth::json(['status' => 'ok', 'datos' => [
                        'manifiesto' => ReservaService::manifiesto($idViaje),
                    ]]);
                    break;
                }

                /* --- El conductor marca quién embarcó y quién no se presentó --- */
                case 'embarcar': {
                    $idViaje = (int)($_POST['id_via'] ?? 0);
                    if (!ViajeService::puedeVerManifiesto($idViaje)) {
                        Auth::json(['status' => 'error',
                                    'mensaje' => 'Solo puedes marcar pasajeros de los viajes que conduces.'], 403);
                    }
                    $r = ReservaService::marcarEmbarque(
                        (int)($_POST['id'] ?? 0),
                        !empty($_POST['embarco']),
                        (string)($_POST['motivo'] ?? ''),
                        $idViaje
                    );
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje']]
                        : ['status' => 'error', 'mensaje' => $r['mensaje'],
                           'errores' => ['motivo' => $r['mensaje']]], $r['ok'] ? 200 : 409);
                    break;
                }

                case 'noPresente': {
                    $idViaje = (int)($_POST['id_via'] ?? 0);
                    if (!ViajeService::puedeVerManifiesto($idViaje)) {
                        Auth::json(['status' => 'error',
                                    'mensaje' => 'Solo puedes marcar pasajeros de los viajes que conduces.'], 403);
                    }
                    $r = ReservaService::marcarNoPresente(
                        $idViaje,
                        (int)($_POST['id_usu'] ?? 0),
                        (string)($_POST['motivo'] ?? '')
                    );
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje'],
                           'datos' => ['marcadas' => $r['marcadas'] ?? 0]]
                        : ['status' => 'error', 'mensaje' => $r['mensaje'],
                           'errores' => ['motivo' => $r['mensaje']]], $r['ok'] ? 200 : 409);
                    break;
                }

                /* --- Avisar a quien se quedó en casa de que su viaje salió --- */
                case 'avisarPerdidos': {
                    Auth::requerirAcceso('asignaciones');
                    $r = ReservaService::avisarViajePerdido((int)($_POST['id'] ?? 0));
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje'],
                           'datos' => ['avisados' => $r['avisados'] ?? 0]]
                        : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 409);
                    break;
                }

                case 'cancelar': {
                    /* Dos formas, según lo que mande la pantalla:
                         · `id`                -> un puesto suelto.
                         · `id_pasajero`+`id_via` -> TODOS los puestos vivos de esa
                           persona en ese viaje, que es como trabaja el manifiesto.
                       Se decide por lo que VIENE, no por si se resolvió una fila:
                       un pasajero que ya pagó tiene fila pero su grupo sigue
                       siendo lo que hay que anular entero. */
                    $esGrupo = (string)($_POST['id_pasajero'] ?? '') !== '';
                    [$idFila, $idGrupo] = $destinoReserva((string)($_POST['id'] ?? ''), (string)($_POST['id_pasajero'] ?? '') . ':' . (string)($_POST['id_via'] ?? ''));

                    /* Un pasajero sin permiso de recaudo solo puede cancelar
                       lo SUYO. Sin esta comprobación, cancelar por fila le
                       permitía anular el puesto de cualquier otro pasajero con
                       solo conocer el id de la reserva. */
                    if (!Auth::tieneAcceso('gestionar_asignaciones')) {
                        $duenio = (int) Database::scalar(
                            'SELECT id_usu_res FROM reserva WHERE id_res = ?',
                            [$idFila]
                        );
                        if ($idFila <= 0 || $duenio !== Auth::id()) {
                            Auth::json(['status' => 'error',
                                        'mensaje' => 'Solo puedes cancelar tus propias reservas.'], 403);
                        }
                    }

                    $r = $esGrupo && $idGrupo !== null
                        ? ReservaService::cancelarPuestosDe($idGrupo['viaje'], $idGrupo['id'], (string)($_POST['motivo'] ?? 'Cancelación en terminal'))
                        : ReservaService::cancelar($idFila, (string)($_POST['motivo'] ?? ''));
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje'],
                           'datos' => ['canceladas' => $r['canceladas'] ?? 1]]
                        : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 409);
                    break;
                }

                case 'porViaje':
                    // Listado de reservas de un viaje (modal de abordaje).
                    // El admin lo ve siempre; el conductor, solo los suyos:
                    // la lista contiene documentos y teléfonos de pasajeros.
                    $idViaje = (int)($_POST['id'] ?? 0);
                    if (Auth::rol() === Config::ROL_ADMIN) {
                        Auth::requerirAcceso('asignaciones');
                    } elseif (!ViajeService::esConductorDelViaje($idViaje)) {
                        Auth::json(['status' => 'error',
                                    'mensaje' => 'Solo puedes ver los pasajeros de los viajes que conduces.'], 403);
                    }
                    Auth::json(['status' => 'ok', 'datos' => [
                        'reservas' => ReservaService::porViaje($idViaje),
                        'manifiesto' => ReservaService::manifiesto($idViaje),
                    ]]);
                    break;
            }
            break;
        }
    }

    Auth::json(['status' => 'error', 'mensaje' => "Acción «{$accion}» no reconocida para el módulo «{$modulo}»."], 400);

} catch (Throwable $e) {
    error_log('[SGET][API] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    Auth::json([
        'status'  => 'error',
        'mensaje' => 'Ocurrió un error inesperado al procesar la operación.',
        'debug'   => (defined('SGET_DEBUG') && SGET_DEBUG) ? $e->getMessage() : null,
    ], 500);
}
