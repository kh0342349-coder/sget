<?php
/**
 * validar.php
 * -----------------------------------------------------------------------------
 * Inicio de sesión con documento y contraseña.
 * -----------------------------------------------------------------------------
 * ANTES (problemas que este archivo corrige)
 *   · `session_set_cookie_params(['secure' => true])` FIJO, sin mirar el
 *     protocolo: en la red local (http://localhost/...) el navegador descarta
 *     la cookie, así que el login "funcionaba" y a los 2 segundos el usuario
 *     volvía a la portada. La cookie ahora la fija `Auth::iniciar()` según la
 *     conexión real.
 *   · Sin token anti-CSRF en el formulario: un sitio externo podía reenviar el
 *     POST y forzar el inicio de sesión de una cuenta conocida (login CSRF).
 *   · Comparaba contraseñas a mano (`password_verify` + texto plano) y
 *     aceptaba cuentas en claro: ahora toda comprobación pasa por
 *     `core/Password.php`, que además migra la cuenta heredada al vuelo.
 *   · Sin freno a la fuerza bruta: cada intento fallido se registra y a partir
 *     del quinto la cuenta se bloquea temporalmente para esa IP.
 *   · Mensajes técnicos y mensajes que permitían enumerar usuarios.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

/** Vuelve a la portada abriendo el modal de login con el mensaje dado. */
$volverAlLogin = static function (string $mensaje): void {
    Flash::error($mensaje);
    $_SESSION['abrir_login'] = true;
    sget_redirigir(Config::basePath() . '/index.php');
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sget_redirigir(Config::basePath() . '/index.php');
}

/* -------------------------------------------------------------------------- */
/* 1. Token anti-CSRF                                                          */
/* -------------------------------------------------------------------------- */
if (!Auth::validarToken((string) ($_POST['_token'] ?? ''))) {
    http_response_code(403);
    $volverAlLogin('La sesión del formulario caducó. Vuelve a intentarlo.');
}

/* -------------------------------------------------------------------------- */
/* 2. reCAPTCHA (CONFIGURABLE · ver core/Recaptcha.php)                        */
/* -------------------------------------------------------------------------- */
/*
 * Antes el código era:
 *
 *     if ($respuestaRecaptcha !== '') { …verificar… }
 *
 * es decir, si el POST no traía token se entraba igual. Y la clave pública era
 * la de PRUEBA de Google, que valida cualquier cosa. El captcha estaba en
 * pantalla pero no era obligatorio: falsa sensación de seguridad.
 *
 * Ahora `Recaptcha::verificar()` decide según `SGET_RECAPTCHA_ENABLED`:
 *   · apagado    → no se pide token (desarrollo)
 *   · encendido  → token obligatorio; si falta, se rechaza
 *   · encendido sin secreto configurado → se rechaza en cerrado
 */
$recaptcha = Recaptcha::verificar((string)($_POST['g-recaptcha-response'] ?? ''));
if (!$recaptcha['ok']) {
    http_response_code(400);
    $volverAlLogin($recaptcha['mensaje']);
}

/* -------------------------------------------------------------------------- */
/* 3. Datos del formulario                                                     */
/* -------------------------------------------------------------------------- */
$documento = trim((string) ($_POST['documento'] ?? ''));
$clave     = (string) ($_POST['clave'] ?? '');          // sin trim: la clave es exacta

if ($documento === '' || $clave === '') {
    $volverAlLogin('Escribe tu número de documento y tu contraseña.');
}

/* -------------------------------------------------------------------------- */
/* 4. Freno a la fuerza bruta (persistido en servidor, no en la sesión)       */
/* -------------------------------------------------------------------------- */
$espera = Auth::intentosBloqueados($documento);
if ($espera > 0) {
    http_response_code(429);
    Logger::registrar(Database::pdo(), 'LOGIN_BLOQUEADO', sprintf(
        'Cuenta bloqueada por intentos fallidos (documento %s).',
        substr($documento, 0, 4) . '***'
    ));
    $volverAlLogin(sprintf(
        'Demasiados intentos fallidos. Vuelve a intentar en %d minuto(s).',
        (int) ceil($espera / 60)
    ));
}

/* -------------------------------------------------------------------------- */
/* 5. Buscar la cuenta                                                        */
/* -------------------------------------------------------------------------- */
$fila = Database::one(
    "SELECT id_usu, tip_doc_usu, num_doc_usu, nom_usu, corre_usu,
            pass_usu, id_rol_usu, estado
       FROM usuario
      WHERE num_doc_usu = ?",
    [$documento]
);

/**
 * Mensaje ÚNICO para "no existe" y "contraseña incorrecta".
 *
 * Distinguirlos convertía el login en un oráculo: cualquiera podía
 * comprobar si un documento estaba registrado escribiendo cualquier contraseña.
 */
if (!$fila || !Password::verify($clave, (string) $fila['pass_usu'])) {
    Auth::registrarIntentoFallido($documento);
    error_log('[SGET][login] Intento fallido para el documento ' . substr($documento, 0, 4) . '***');
    http_response_code(401);
    $volverAlLogin('El documento o la contraseña no son correctos.');
}

Auth::limpiarIntentos($documento);

/* -------------------------------------------------------------------------- */
/* 6. Migración silenciosa de contraseñas heredadas (MD5 / texto plano)        */
/* -------------------------------------------------------------------------- */
if (Password::necesitaMigracion((string) $fila['pass_usu'])) {
    Database::query(
        'UPDATE usuario SET pass_usu = ? WHERE id_usu = ?',
        [Password::hash($clave), (int) $fila['id_usu']]
    );
    error_log('[SGET][login] Contraseña migrada al formato actual del usuario #' . (int) $fila['id_usu']);
}

/* -------------------------------------------------------------------------- */
/* 7. Cuenta activa                                                            */
/* -------------------------------------------------------------------------- */
if ((int) $fila['estado'] !== Config::USU_ACTIVO) {
    http_response_code(403);
    $volverAlLogin('Tu cuenta está desactivada. Contacta al administrador de SGET.');
}

/* -------------------------------------------------------------------------- */
/* 8. Rol válido y sesión                                                      */
/* -------------------------------------------------------------------------- */
$rolesValidos = Database::all('SELECT id_rol FROM rol WHERE id_rol = ?', [(int) $fila['id_rol_usu']]);
if ($rolesValidos === []) {
    $volverAlLogin('Tu cuenta no tiene un rol asignado. Contacta al administrador.');
}

Auth::establecerSesion($fila);

Logger::registrar(Database::pdo(), 'INICIAR_SESION', sprintf(
    'Inicio de sesión del usuario #%d (%s, rol %d).',
    (int) $fila['id_usu'],
    (string) $fila['nom_usu'],
    (int) $fila['id_rol_usu']
));

sget_redirigir(Auth::inicioPorRol());