<?php
/**
 * api/obtener_permisos.php
 * -----------------------------------------------------------------------------
 * Devuelve el catálogo de permisos y los que tiene asignados un usuario.
 * -----------------------------------------------------------------------------
 * ANTES
 *   · `$_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? 1` — el mismo «usuario por
 *     defecto» que hacía peligroso al endpoint de guardado.
 *   · Sin ninguna comprobación de permiso: CUALQUIER usuario autenticado podía
 *     pedir el catálogo de permisos de cualquier otro, lo que ya le revealba qué
 *     módulos tienen concedidos.
 *
 * AHORA
 *   Exige el permiso `gestionar_permisos` y devuelve el estado efectivo
 *   (`efectivo`), es decir, si el usuario lo tendrá granted aunque no tenga una
 *   fila propia en `usuario_permisos`, porque su rol lo concede. La interfaz
 *   puede así marcar como «concedido por rol» lo que el administrador no puede
 *   quitar sin quitarlo también a los demás.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$responder = static function (bool $ok, string $mensaje, array $extra = []): void {
    http_response_code($ok ? 200 : 403);
    echo json_encode(
        array_merge(['status' => $ok ? 'ok' : 'error', 'mensaje' => $mensaje], $extra),
        JSON_UNESCAPED_UNICODE
    );
    exit;
};

Auth::requerirSesion();

if (!Auth::tieneAcceso('gestionar_permisos')) {
    $responder(false, 'No tienes permiso para consultar los permisos del sistema.');
}

$idUsuarioObjetivo = (int)($_GET['id_usu'] ?? $_GET['id'] ?? $_GET['user_id'] ?? 0);
if ($idUsuarioObjetivo <= 0) {
    $responder(false, 'No se indicó qué usuario consultar.');
}

$fila = Database::one(
    'SELECT id_usu, nom_usu, id_rol_usu FROM usuario WHERE id_usu = ?',
    [$idUsuarioObjetivo]
);
if (!$fila) {
    $responder(false, 'Ese usuario no existe.');
}

$rolObjetivo = (int) $fila['id_rol_usu'];

/* -------------------------------------------------------------------------
 * Catálogo completo + lo que el usuario tiene por fila propia + lo que le
 * concede su rol.
 * ---------------------------------------------------------------------- */
$filas = Database::all(
    'SELECT p.id_permiso, p.nombre_permiso, p.modulo, p.descripcion,
            COALESCE(up.permitido, 0) AS permitido,
            rp.id_rol IS NOT NULL AS por_rol
       FROM permisos p
       LEFT JOIN usuario_permisos up ON up.id_permiso = p.id_permiso AND up.id_usu = ?
       LEFT JOIN rol_permiso rp       ON rp.id_permiso = p.id_permiso AND rp.id_rol = ?
      ORDER BY p.modulo ASC, p.id_permiso ASC',
    [$idUsuarioObjetivo, $rolObjetivo]
);

$permisos = [];
foreach ($filas as $f) {
    $porFila   = (int) $f['permitido'] === 1;
    $porRol    = (int) $f['por_rol'] === 1;

    $permisos[] = [
        'id_permiso'    => (int) $f['id_permiso'],
        'nombre_permiso' => (string) $f['nombre_permiso'],
        'modulo'        => (string) $f['modulo'],
        'descripcion'   => (string) ($f['descripcion'] ?? ''),
        // Marcado en la casilla: lo que el administrador ha decidido para ESTE
        // usuario (una denegación explícita no sale marcada aunque su rol lo
        // conceda).
        'permitido'     => $porFila,
        // Aviso para la interfaz: si está concedido solo por el rol, quitar la
        // casilla no lo va a quitar de verdad.
        'por_rol'       => $porRol,
        // Resultado real de `Auth::tieneAcceso()` para este usuario.
        'efectivo'      => $porFila || (!$porFila && $porRol),
    ];
}

$responder(true, 'Permisos del usuario.', [
    'usuario'   => (string) $fila['nom_usu'],
    'id_usu'    => (int) $fila['id_usu'],
    'permisos'  => $permisos,
    'data'      => $permisos,
    'status'    => 'ok',
]);