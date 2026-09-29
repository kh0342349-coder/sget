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
if (empty($_POST['_token']) || !hash_equals($_SESSION['sget_csrf'] ?? '', (string)$_POST['_token'])) {
    Auth::json(['status' => 'error', 'mensaje' => 'La sesión expiró o el formulario no es válido. Recarga la página.'], 419);
}

$modulo = (string)($_POST['modulo'] ?? $_GET['modulo'] ?? '');
$accion = (string)($_POST['accion'] ?? $_GET['accion'] ?? '');

/** Permiso requerido por módulo. */
$permisos = [
    'ruta'         => 'rutas',
    'vehiculo'     => 'vehiculos',
    'usuario'      => 'usuarios',
    'viaje'        => 'viajes',
    'notificacion' => null,   // el buzón es de todos los roles
    'anuncio'      => 'anuncios',
    'reserva'      => 'asignaciones',
    'calificacion' => 'calificar',
    'reporte'      => 'reportes_pasajeros',
];
if (!empty($permisos[$modulo]) && !Auth::tieneAcceso($permisos[$modulo])) {
    Auth::json(['status' => 'error', 'mensaje' => 'No tienes permisos para gestionar ' . $modulo . '.'], 403);
}

if ($modulo === '' || $accion === '') {
    Auth::json(['status' => 'error', 'mensaje' => 'Falta el módulo o la acción solicitada.'], 400);
}

// ---------------------------------------------------------------- Despacho
try {
    switch ($modulo) {

        /* ============================================================== */
        case 'ruta': {
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
                case 'guardar':
                    /* El conductor también puede programar SU propio viaje: es el
                       botón «Iniciar Despacho», que antes enviaba a
                       `guardar_viaje.php` (archivo inexistente) y por tanto no
                       funcionaba. Solo puede Crear para sí mismo. */
                    if (Auth::rol() === Config::ROL_CONDUCTOR) {
                        if ((int)($_POST['id_usu_via'] ?? 0) !== Auth::id()) {
                            Auth::json(['status' => 'error',
                                        'mensaje' => 'Solo puedes programar viajes a tu propio nombre.'], 403);
                        }
                        if (!Auth::tieneAcceso('crear_viaje')) {
                            Auth::json(['status' => 'error', 'mensaje' => 'No tienes permiso para crear viajes.'], 403);
                        }
                        // El formulario heredado manda `id_veh_via`; el servicio
                        // espera `id_veh`. Se normaliza en el servidor, no en el
                        // formulario: el backend nunca confía en lo que envía el
                        // cliente sin adaptarlo a la regla del dominio.
                        if (empty($_POST['id_veh']) && !empty($_POST['id_veh_via'])) {
                            $_POST['id_veh'] = (int)$_POST['id_veh_via'];
                        }
                    } else {
                        Auth::requerirAdmin();
                    }

                    $r = ViajeService::guardar($_POST);
                    if (!$r['ok']) {
                        Auth::json(['status' => 'error', 'mensaje' => $r['mensaje'], 'errores' => $r['errores'] ?? []], 422);
                    }
                    $destino = Auth::rol() === Config::ROL_CONDUCTOR ? '../Conductor/viaje_asignado.php' : 'viajes.php';
                    Auth::json(['status' => 'ok', 'mensaje' => $r['mensaje'],
                                'redirect' => $destino . '?ok=' . urlencode($r['mensaje'])]);
                    break;

                case 'finalizar':
                    $r = ViajeService::finalizar((int)($_POST['id'] ?? 0));
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje'], 'redirect' => 'viajes.php?ok=' . urlencode($r['mensaje'])]
                        : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 409);
                    break;

                case 'enCurso':
                    $r = ViajeService::marcarEnCurso((int)($_POST['id'] ?? 0));
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje'], 'redirect' => 'viajes.php?ok=' . urlencode($r['mensaje'])]
                        : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 409);
                    break;

                /** CANCELACIÓN · con anotación obligatoria si aún no salió */
                case 'cancelar': {
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
            // Reportes/quejas de los pasajeros sobre un viaje.
            Auth::requerirAdmin();

            if ($accion === 'actualizar') {
                $id     = (int)($_POST['id'] ?? 0);
                $estado = (string)($_POST['estado'] ?? 'pendiente');
                $idVia  = (int)($_POST['id_via'] ?? 0);

                if (!in_array($estado, ReporteService::ESTADOS, true)) {
                    Auth::json(['status' => 'error', 'mensaje' => 'El estado del reporte no es válido.'], 422);
                }

                $r = ReporteService::actualizar($id, $estado, $idVia);
                Auth::json($r['ok']
                    ? ['status' => 'ok', 'mensaje' => $r['mensaje']]
                    : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 404);
            }

            if ($accion === 'crear') {
                // Lo crea el propio pasajero desde su módulo de reportes.
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

            if ($accion === 'eliminar') {
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
            switch ($accion) {
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

                case 'crear':
                    Auth::requerirAcceso('asignaciones');
                    $r = ReservaService::crear(
                        (int)($_POST['id_via'] ?? 0),
                        (int)($_POST['id_usu'] ?? 0),
                        max(1, (int)($_POST['puestos'] ?? 1)),
                        [
                            'metodo'   => (string)($_POST['metodo_pago'] ?? 'Efectivo'),
                            'valor'    => (float)($_POST['valor_pagado'] ?? 0),
                            'confirmar' => !empty($_POST['confirmar']),
                        ]
                    );
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje'], 'datos' => ['ids' => $r['ids'], 'puestos' => $r['puestos']]]
                        : ['status' => 'error', 'mensaje' => $r['mensaje'], 'errores' => ['general' => $r['mensaje']]],
                        $r['ok'] ? 200 : 409);
                    break;

                case 'cobrar':
                    Auth::requerirAcceso('asignaciones');
                    $r = ReservaService::confirmarPago(
                        (int)($_POST['id'] ?? 0),
                        (float)($_POST['valor'] ?? 0),
                        (string)($_POST['metodo'] ?? 'Efectivo')
                    );
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje']]
                        : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 409);
                    break;

                case 'cancelar':
                    Auth::requerirAcceso('asignaciones');
                    $r = ReservaService::cancelar((int)($_POST['id'] ?? 0), (string)($_POST['motivo'] ?? ''));
                    Auth::json($r['ok']
                        ? ['status' => 'ok', 'mensaje' => $r['mensaje']]
                        : ['status' => 'error', 'mensaje' => $r['mensaje']], $r['ok'] ? 200 : 409);
                    break;

                case 'porViaje':
                    // Listado de reservas de un viaje (modal de abordaje).
                    Auth::requerirAcceso('asignaciones');
                    Auth::json(['status' => 'ok', 'datos' => [
                        'reservas' => ReservaService::porViaje((int)($_POST['id'] ?? 0)),
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
