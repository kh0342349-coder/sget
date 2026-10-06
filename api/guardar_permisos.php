<?php
/**
 * api/guardar_permisos.php
 * -----------------------------------------------------------------------------
 * Guarda los permisos de un usuario (pantalla «Gestión de Permisos»).
 * -----------------------------------------------------------------------------
 * QUÉ ARREGLABA
 *
 *   1. `$_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? 1` — si no había sesión,
 *      el usuarioCached por defecto era **1**, el primer administrador. Bastaba
 *      con que la sesión caducara para escribir como admin.
 *
 *   2. La autorización tenía un atajo: `if (!Auth::tieneAcceso(...))` y, si
 *      fallaba, se comprobaba solo `id_rol_usu === 1`. Es decir, el permiso
 *      `gestionar_permisos` no era obligatorio: cualquier administrador pasaba.
 *      Con la lógica de mínimo privilegio eso ya no es aceptable, porque el
 *      permiso existe precisamente para poder revocar la gestión de permisos a
 *      un administrador concreto.
 *
 *   3. Sin token anti-CSRF: un formulario externo podía reescribir los permisos
 *      de cualquier usuario con la sesión del administrador abierta.
 *
 *   4. `mysqli_begin_transaction` + `echo` de la excepción cruda al cliente.
 *
 * AHORA: bootstrap único, permiso exigido sin atajos, CSRF, PDO con sentencias
 * preparadas y mensajes legibles. La escritura completa (borrar + insertar) se
 * mantiene dentro de UNA transacción, igual que antes.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

/** Responde y termina. */
$responder = static function (bool $ok, string $mensaje): void {
    echo json_encode(
        ['status' => $ok ? 'ok' : 'error', 'mensaje' => $mensaje],
        JSON_UNESCAPED_UNICODE
    );
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $responder(false, 'Método no permitido.');
}

if (!Auth::validarToken((string)($_POST['_token'] ?? ''))) {
    http_response_code(403);
    $responder(false, 'La sesión caducó. Recarga la página e inténtalo de nuevo.');
}

// Sin sesión, `Auth::id()` devuelve 0: no hay ningún usuario «por defecto».
Auth::requerirSesion();

// Permiso obligatorio, sin alternativa por rol.
if (!Auth::tieneAcceso('gestionar_permisos')) {
    http_response_code(403);
    $responder(false, 'No tienes permiso para gestionar los permisos del sistema.');
}

/* -------------------------------------------------------------------------
 * Entrada: JSON o formulario clásico
 * ---------------------------------------------------------------------- */
$entrada = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($entrada)) {
    $entrada = $_POST;
}

$idUsuarioObjetivo = (int)($entrada['id_usu'] ?? $entrada['id'] ?? 0);
if ($idUsuarioObjetivo <= 0) {
    $responder(false, 'No se indicó qué usuario modificar.');
}

$seleccionados = $entrada['permisos'] ?? [];
if (!is_array($seleccionados)) {
    $seleccionados = [];
}

// Solo ids válidos y, además, que existan de verdad en el catálogo.
$ids = array_values(array_unique(array_filter(array_map('intval', $seleccionados))));
if ($ids) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $ids = array_map('intval', array_column(
        Database::all("SELECT id_permiso FROM permisos WHERE id_permiso IN ($ph)", $ids),
        'id_permiso'
    ));
}

/* -------------------------------------------------------------------------
 * AUTO-BLOQUEO DEL ADMINISTRADOR
 * ----------------------------------------------------------------------
 * El bug que se corrige: la condición anterior era
 *
 *     if ($idUsuarioObjetivo === Auth::id() && !Auth::tieneAcceso('gestionar_permisos'))
 *
 * y, como en este mismo archivo YA se exigía `gestionar_permisos` cuatro
 * líneas más arriba, la segunda mitad del «&&» NUNCA podía ser falsa. Es decir:
 * la protección era código muerto. Un administrador podía guardarse la lista
 * vacía, quedarse sin `gestionar_permisos` y salir del sistema sin poder
 * recuperar el acceso (el módulo que lo deja volver es este mismo).
 *
 * Ahora se compara el conjunto NUEVO contra los permisos CRÍTICOS y, si el
 * propio usuario se quedaría sin ellos, se rechaza en el servidor.
 * ---------------------------------------------------------------------- */
if ($idUsuarioObjetivo === Auth::id()) {
    $criticosPerdidos = Auth::permisosCriticosInaccesibles($idUsuarioObjetivo, $ids);
    if ($criticosPerdidos !== []) {
        http_response_code(403);
        $responder(false, sprintf(
            'No puedes quitarte a ti mismo estos permisos críticos: %s. Si necesitas revisarlos, pídeselo a otro administrador.',
            implode(', ', array_map(static fn($p) => str_replace('_', ' ', $p), $criticosPerdidos))
        ));
    }
}

/* -------------------------------------------------------------------------
 * Escritura
 * ---------------------------------------------------------------------- */
try {
    Database::begin();

    // 1) Se sustituye el conjunto COMPLETO de permisos del usuario. Por eso la
    //    tabla no necesita una columna «denegado»: lo que no está marcado, está
    //    denegado. Es la razón de que `Auth::tieneAcceso()` devuelva `false` por
    //    defecto cuando no encuentra la fila.
    Database::query('DELETE FROM usuario_permisos WHERE id_usu = ?', [$idUsuarioObjetivo]);

    foreach ($ids as $idPermiso) {
        Database::query(
            'INSERT INTO usuario_permisos (id_usu, id_permiso, permitido) VALUES (?, ?, 1)',
            [$idUsuarioObjetivo, $idPermiso]
        );
    }

    Database::commit();
} catch (Throwable $e) {
    Database::rollback();
    error_log('[SGET][guardar_permisos] ' . $e->getMessage());
    $responder(false, 'No se pudieron guardar los permisos. Inténtalo de nuevo.');
}

Logger::registrar(Database::pdo(), 'EDITAR_PERMISOS', sprintf(
    'Permisos del usuario #%d actualizados (%d concedido/s) por %s.',
    $idUsuarioObjetivo, count($ids), Auth::nombre()
));

$responder(true, $ids
    ? sprintf('Permisos guardados: %d concedidos a este usuario.', count($ids))
    : 'Permisos guardados: este usuario se queda sin permisos asignados y dependerá de su rol.');