<?php
/**
 * api/inactividad.php
 * -----------------------------------------------------------------------------
 * API DEL BLOQUEO POR INACTIVIDAD
 * -----------------------------------------------------------------------------
 * ACCIONES (POST + _token CSRF):
 *   estado      -> devuelve el estado del temporizador y bloquea la pantalla
 *   desbloquear -> valida la contraseña y libera la sesión
 *   reiniciar   -> reinicia el temporizador (el usuario sigue trabajando)
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    Auth::json(['status' => 'error', 'mensaje' => 'Método no permitido.'], 405);
}

if (Auth::id() <= 0) {
    Auth::json(['status' => 'error', 'mensaje' => 'Sesión no válida.'], 401);
}

$accion = (string)($_POST['accion'] ?? 'estado');

switch ($accion) {

    /* ------------------------------------------------------------------ */
    case 'estado': {
        $bloqueadaEn = (int)($_SESSION['inactividad_bloqueada_en'] ?? time());
        // Ventana de gracia para que el usuario pueda seguir escribiendo
        // la contraseña antes de que expire la sesión.
        $temporizador = $bloqueadaEn + Config::SEGUNDOS_GRACIA_INACTIVIDAD;
        $cierra       = $temporizador + Config::SEGUNDOS_GRACIA_INACTIVIDAD;

        $_SESSION['sesion_bloqueada']                     = true;
        $_SESSION['inactividad_bloqueada_en']             = $bloqueadaEn;
        $_SESSION['inactividad_temporizador_inicia_en']   = $temporizador;
        $_SESSION['inactividad_cierra_en']                = $cierra;

        Auth::json([
            'status'                => 'ok',
            'bloqueada'             => true,
            'bloqueadaEn'           => $bloqueadaEn,
            'temporizadorIniciaEn'  => $temporizador,
            'cierraEn'              => $cierra,
            'minutosInactividad'    => Config::MINUTOS_INACTIVIDAD,
        ]);
        break;
    }

    /* ------------------------------------------------------------------ */
    case 'desbloquear': {
        [$ok, $mensaje] = Auth::desbloquear((string)($_POST['password'] ?? ''));

        if (!$ok) {
            Auth::json(['status' => 'error', 'mensaje' => $mensaje], 401);
        }

        $destino = (string)($_SESSION['url_redirect'] ?? '');
        unset($_SESSION['url_redirect']);

        Auth::json([
            'status'   => 'ok',
            'mensaje'  => $mensaje,
            'redirect' => $destino !== '' ? $destino : Config::basePath() . '/index.php',
        ]);
        break;
    }

    /* ------------------------------------------------------------------ */
    case 'reiniciar': {
        $_SESSION['ultimo_acceso'] = time();
        unset($_SESSION['sesion_bloqueada'], $_SESSION['inactividad_bloqueada_en'],
              $_SESSION['inactividad_temporizador_inicia_en'], $_SESSION['inactividad_cierra_en']);

        Auth::json(['status' => 'ok', 'mensaje' => 'Temporizador reiniciado.']);
        break;
    }
}

Auth::json(['status' => 'error', 'mensaje' => "Acción «{$accion}» no reconocida."], 400);
