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
    'notificacion' => 'pasajero',
];
if (isset($permisos[$modulo]) && !Auth::tieneAcceso($permisos[$modulo])) {
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
            if ($accion === 'listar') {
                Auth::json(['status' => 'ok', 'datos' => NotificacionService::bandeja(Auth::id())]);
            }
            if ($accion === 'leer') {
                NotificacionService::marcarLeida((int)($_POST['id'] ?? 0), Auth::id());
                Auth::json(['status' => 'ok', 'mensaje' => 'Notificación marcada como leida.']);
            }
            if ($accion === 'leerTodas') {
                $n = NotificacionService::marcarTodasLeidas(Auth::id());
                Auth::json(['status' => 'ok', 'mensaje' => "Marcaste {$n} notificación(es) como leídas."]);
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
