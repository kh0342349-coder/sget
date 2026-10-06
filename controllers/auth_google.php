<?php
/**
 * controllers/auth_google.php
 * -----------------------------------------------------------------------------
 * Inicio y registro de sesión con Google (Google Identity Services).
 * -----------------------------------------------------------------------------
 * QUÉ COMPROBABA ANTES
 *   Solo `isset($payload['email'])`. Eso no es autenticación: es suposición.
 *   Un `tokeninfo` devuelve 200 para cualquier id_token bien formado, y el
 *   endpoint se quedaba ahí. Faltaba todo lo que hace que el token sea DE
 *   ESTA aplicación y no de otra:
 *
 *     · `aud` / `azp`  — el token se emitió para otro cliente distinto.
 *     · `iss`          — no lo emitió accounts.google.com.
 *     · `exp` / `iat`  — token caducado o emitido en el futuro.
 *     · `email_verified` — una cuenta sin correo verificado no demuestra nada.
 *     · `sub`          — la identidad estable; sin él no hay `google_id` fiable.
 *
 *   Además:
 *     · No comprobaba que la cuenta estuviera ACTIVA: una cuenta suspendida
 *       seguía entrando por Google.
 *     · No regeneraba el ID de sesión (session fixation).
 *     · No exigía CSRF, de modo que unthird-party podía forzar el enlace de
 *       una cuenta Google con el navegador de la víctima.
 *     · Construía el SQL concatenando cadenas escapadas a mano.
 *     · Al crear la cuenta dejaba `pass_usu` vacío, lo que dejaba una cuenta
 *       sin credencial exploitable si alguien adivinaba el documento generado.
 *
 * AHORA
 *   Valida el token por completo, usa PDO conSentencias preparadas, aplica el
 *   MISMO `Auth::establecerSesion()` que el login normal (mismos roles, mismos
 *   permisos, mismo control de inactividad) y, si crea la cuenta, le asigna una
 *   contraseña aleatoria inutilizable en lugar de una vacía.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

/** Responde y termina. */
$responder = static function (bool $ok, string $mensaje, array $extra = []): void {
    http_response_code($ok ? 200 : 401);
    echo json_encode(
        array_merge(['success' => $ok, 'message' => $mensaje], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
};

/* -------------------------------------------------------------------------- */
/* 1. Solo POST, con cuerpo JSON                                                */
/* -------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $responder(false, 'Método no permitido.');
}

$entrada = json_decode((string) file_get_contents('php://input'), true);
$token   = trim((string) ($entrada['token'] ?? ''));

if ($token === '') {
    $responder(false, 'No se recibió el token de Google.');
}

/* -------------------------------------------------------------------------- */
/* 2. CSRF                                                                     */
/* -------------------------------------------------------------------------- */
/*
 * El token se entrega al montar el botón (ver assets/js/sget-google.js, que lo
 * lee de `window.SGET_CSRF`). Sin esta comprobación, cualquier página podría
 * POSTear aquí con el token de un tercero y vincular su Google a la cuenta SGET
 * de la víctima (login CSRF).
 */
if (!Auth::validarToken((string) ($entrada['csrf'] ?? ''))) {
    $responder(false, 'La sesión caducó. Recarga la página e inténtalo de nuevo.');
}

/* -------------------------------------------------------------------------- */
/* 3. Verificación del id_token                                                */
/* -------------------------------------------------------------------------- */
const GOOGLE_CLIENT_ID = '916674198156-4uh6adhaklk2bpsvli6hnmrgg0bgktlp.apps.googleusercontent.com';
const GOOGLE_EMISORES  = ['https://accounts.google.com', 'accounts.google.com'];

$respuesta = @file_get_contents(
    'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($token),
    false,
    stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]])
);

if ($respuesta === false) {
    $responder(false, 'No se pudo contactar con Google. Revisa tu conexión e inténtalo de nuevo.');
}

$claims = json_decode((string) $respuesta, true);
if (!is_array($claims) || $claims === []) {
    $responder(false, 'La respuesta de Google no fue válida.');
}

// 3.1 Emisor
if (!in_array((string) ($claims['iss'] ?? ''), GOOGLE_EMISORES, true)) {
    $responder(false, 'El token no fue emitido por Google.');
}

// 3.2 Audiencia: debe ser NUESTRA aplicación web.
$aud = (string) ($claims['aud'] ?? '');
if ($aud !== GOOGLE_CLIENT_ID) {
    error_log('[SGET][Google] id_token con aud inesperada: ' . $aud);
    $responder(false, 'Ese token de Google no es válido para SGET.');
}

// 3.3 Parte autorizada (solo cuando hay varios clientes en la misma nube).
$azp = (string) ($claims['azp'] ?? '');
if ($azp !== '' && $azp !== GOOGLE_CLIENT_ID) {
    $responder(false, 'Ese token de Google no está autorizado para SGET.');
}

// 3.4 Caducidad. `tokeninfo` ya la aplica, pero se comprueba por si acaso.
$exp = (int) ($claims['exp'] ?? 0);
if ($exp > 0 && $exp < time() - 60) {
    $responder(false, 'La sesión de Google caducó. Vuelve a iniciar sesión.');
}

// 3.5 Identidad y correo verificado: sin esto no hay cuenta que crear.
$sub            = trim((string) ($claims['sub'] ?? ''));
$correo         = strtolower(trim((string) ($claims['email'] ?? '')));
$nombreGoogle   = trim((string) ($claims['name'] ?? ''));
$verificado     = ($claims['email_verified'] ?? null);

if ($sub === '') {
    $responder(false, 'Google no devolvió una identidad de usuario válida.');
}
if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $responder(false, 'Google no devolvió un correo válido.');
}
// `email_verified` llega como booleano o como cadena "true".
$esVerificado = ($verificado === true || $verificado === 1 || $verificado === 'true');
if (!$esVerificado) {
    $responder(false, 'El correo de esa cuenta de Google no está verificado.');
}

$nombre = $nombreGoogle !== '' ? $nombreGoogle : explode('@', $correo)[0];

/* -------------------------------------------------------------------------- */
/* 4. Vincular / crear la cuenta                                               */
/* -------------------------------------------------------------------------- */
/*
 * Se busca primero por `google_id` (identidad estable) y DESPUÉS por correo:
 * si el correo coincide, la cuenta ya existía en SGET y lo correcto es
 * ENLAZAR la identidad de Google, no crear un duplicado.
 */
try {
    Database::begin();

    $usuario = Database::one(
        'SELECT u.* FROM usuario u
          WHERE u.google_id = ?
          LIMIT 1',
        [$sub]
    );

    if (!$usuario) {
        $usuario = Database::one(
            'SELECT u.* FROM usuario u
              WHERE LOWER(u.corre_usu) = ?
              LIMIT 1',
            [$correo]
        );
    }

    if ($usuario) {
        $idUsuario = (int) $usuario['id_usu'];

        // El mismo correo no puede estar enlazado a dos identidades de Google.
        if (($usuario['google_id'] ?? '') !== '' && (string) $usuario['google_id'] !== $sub) {
            Database::rollback();
            $responder(false, 'Esa cuenta ya está vinculada a otra cuenta de Google.');
        }

        if (empty($usuario['google_id'])) {
            Database::query(
                'UPDATE usuario SET google_id = ? WHERE id_usu = ?',
                [$sub, $idUsuario]
            );
        }
    } else {
        // Alta automática como Pasajero, con las mismas reglas que el registro
        // público: documento sintético único y contraseña aleatoria
        // INUTILIZABLE (nunca vacía, nunca en texto plano). Así la cuenta no
        // se puede entrar por documento hasta que el usuario se registre.
        $documento = 'G-' . strtoupper(substr(preg_replace('/\D+/', '', $sub) ?: $sub, -8));

        $idUsuario = Database::insert(
            'INSERT INTO usuario
                (tip_doc_usu, num_doc_usu, nom_usu, corre_usu, pass_usu,
                 id_rol_usu, estado, est_con_usu, google_id, acepta_politica)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
            [
                'PP',
                $documento,
                mb_substr($nombre, 0, 100),
                $correo,
                Password::hash(Password::aleatoria()),   // aleatoria e inútil para entrar
                Config::ROL_PASAJERO,
                Config::USU_ACTIVO,
                null,
                $sub,
            ]
        );

        $fila = Database::one('SELECT * FROM usuario WHERE id_usu = ?', [$idUsuario]);
    }

    Database::commit();
} catch (Throwable $e) {
    Database::rollback();
    error_log('[SGET][Google] ' . $e->getMessage());

    if (Database::errorEs($e, [1062, 1586])) {
        $responder(false, 'Ya existe una cuenta con esos datos. Inicia sesión con tu documento.');
    }
    $responder(false, 'No se pudo completar el acceso con Google.');
}

/* -------------------------------------------------------------------------- */
/* 5. Cuenta activa + sesión                                                   */
/* -------------------------------------------------------------------------- */
$fila = Database::one(
    'SELECT id_usu, tip_doc_usu, num_doc_usu, nom_usu, corre_usu, id_rol_usu, estado
       FROM usuario WHERE id_usu = ?',
    [$idUsuario]
);

if (!$fila) {
    $responder(false, 'No encontramos la cuenta de SGET asociada.');
}

if ((int) $fila['estado'] !== Config::USU_ACTIVO) {
    $responder(false, 'Tu cuenta está desactivada. Contacta al administrador de SGET.');
}

// Mismo establecimiento de sesión que el login con documento y contraseña:
// mismo ID de sesión regenerado, mismos roles, mismos permisos.
Auth::establecerSesion($fila);

Logger::registrar(Database::pdo(), 'LOGIN_GOOGLE', sprintf(
    'Acceso con Google del usuario #%d (%s, rol %d).',
    (int) $fila['id_usu'],
    (string) $fila['nom_usu'],
    (int) $fila['id_rol_usu']
));

$responder(true, 'Sesión iniciada correctamente.', [
    'redirect' => Auth::inicioPorRol(),
]);