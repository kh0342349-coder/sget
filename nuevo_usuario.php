<?php
/**
 * nuevo_usuario.php
 * -----------------------------------------------------------------------------
 * Alta de cuenta (Pasajero) desde el modal de registro de la landing.
 * -----------------------------------------------------------------------------
 * ANTES
 *   · Sin token anti-CSRF: cualquiera podía crear cuentas a nombre de terceros.
 *   · Validación artesanal con mensajes genéricos y sin límite de longitud,
 *     lo que permitía colar correos de 300 caracteres o contraseñas de 1 carácter.
 *   · `password_hash($clave, PASSWORD_DEFAULT)` sin comprobación previa.
 *   · Responía "El número de documento o correo ya se encuentra registrado"
 *     para cualquiera, revelando qué correos ya estaban dados de alta.
 *   · No usaba el bootstrap: cada página repetía su propio `session_start()`.
 *
 * AHORA
 *   Valida con `core/Validator.php`, protege con CSRF, usa `core/Password.php`
 *   y delega el mensaje a `Flash` para que el modal lo pinte.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';

$volverAlRegistro = static function (string $mensaje): void {
    Flash::error($mensaje);
    $_SESSION['abrir_registro'] = true;
    sget_redirigir(Config::basePath() . '/index.php');
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sget_redirigir(Config::basePath() . '/index.php');
}

/* -------------------------------------------------------------------------- */
/* 1. CSRF                                                                     */
/* -------------------------------------------------------------------------- */
if (!Auth::validarToken((string) ($_POST['_token'] ?? ''))) {
    http_response_code(403);
    $volverAlRegistro('La sesión del formulario caducó. Vuelve a intentarlo.');
}

/* -------------------------------------------------------------------------- */
/* 2. Validación declarativa                                                  */
/* -------------------------------------------------------------------------- */
$tipoDoc   = strtoupper(trim((string) ($_POST['tipo_doc'] ?? '')));
$documento = trim((string) ($_POST['documento'] ?? ''));
$nombre    = trim((string) ($_POST['nom_usu'] ?? ''));
$correo    = trim((string) ($_POST['corre_usu'] ?? ''));
$clave     = (string) ($_POST['clave_usu'] ?? '');
$confirma  = (string) ($_POST['confirmar_clave'] ?? '');
$acepta    = !empty($_POST['acepta_politica']);

$v = Validator::de($_POST)
    ->requerido('tipo_doc', 'el tipo de documento')
    ->requerido('documento', 'el número de documento')
    ->requerido('nom_usu', 'el nombre completo')
    ->requerido('corre_usu', 'el correo electrónico')
    ->requerido('clave_usu', 'la contraseña')
    ->requerido('confirmar_clave', 'la confirmación de la contraseña');

$v->enLista('tipo_doc', 'el tipo de documento', ['CC', 'TI', 'CE', 'PP']);
$v->texto('documento', 'El número de documento', 5, 20);
$v->texto('nom_usu', 'El nombre completo', 3, 100);
$v->email('corre_usu', 'El correo electrónico');
$v->agregaSi(mb_strlen($correo) > 100, 'corre_usu', 'El correo no puede superar los 100 caracteres.');
$v->distinto('clave_usu', 'confirmar_clave', 'Las contraseñas no coinciden.');
$v->agregaSi(!$acepta, 'acepta_politica', 'Debes aceptar la política de tratamiento de datos para registrarte.');

if ($errorClave = Password::validar($clave, 'La contraseña')) {
    $v->agrega('clave_usu', $errorClave);
}

if ($v->falla()) {
    http_response_code(422);
    $volverAlRegistro($v->primerError() ?? 'Revisa los datos del formulario.');
}

/* -------------------------------------------------------------------------- */
/* 3. Unicidad (documento, correo)                                            */
/* -------------------------------------------------------------------------- */
/*
 * La restricción real la pone la base de datos (UNIQUE en `usuario.num_doc_usu`
 * y `usuario.corre_usu`, añadida en la migración 009). Aquí solo se da el
 * mensaje legible; si otra petición se adelanta, la excepción del motor se
 * traduce más abajo en un mensaje igual de claro.
 */
$existente = Database::one(
    'SELECT num_doc_usu, corre_usu FROM usuario
      WHERE num_doc_usu = ? OR corre_usu = ? LIMIT 1',
    [$documento, $correo]
);

if ($existente) {
    http_response_code(409);
    $volverAlRegistro(
        (string) $existente['num_doc_usu'] === $documento
            ? 'Ese número de documento ya tiene una cuenta en SGET.'
            : 'Ese correo electrónico ya está registrado.'
    );
}

/* -------------------------------------------------------------------------- */
/* 4. Alta                                                                     */
/* -------------------------------------------------------------------------- */
try {
    $idUsuario = Database::insert(
        'INSERT INTO usuario
            (tip_doc_usu, num_doc_usu, nom_usu, corre_usu, pass_usu,
             id_rol_usu, estado, est_con_usu, acepta_politica, fecha_acepta_politica)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())',
        [
            $tipoDoc,
            $documento,
            $nombre,
            $correo,
            Password::hash($clave),
            Config::ROL_PASAJERO,
            Config::USU_ACTIVO,
            null,   // est_con_usu solo aplica a conductores
        ]
    );
} catch (Throwable $e) {
    error_log('[SGET][registro] ' . $e->getMessage());

    // 1062 = clave duplicada: otra petición se adelantó entre la comprobación
    // y el INSERT. Se responde con el mismo mensaje legible, no con el error.
    if (Database::errorEs($e, [1062, 1586])) {
        http_response_code(409);
        $volverAlRegistro('Ese documento o ese correo ya están registrados.');
    }

    http_response_code(500);
    $volverAlRegistro('No pudimos crear tu cuenta en este momento. Inténtalo de nuevo en un rato.');
}

Logger::registrar(Database::pdo(), 'REGISTRAR_USUARIO', sprintf(
    'Cuenta creada: #%d · %s · rol Pasajero.',
    $idUsuario,
    $documento
));

Flash::exito('¡Cuenta creada! Ya puedes iniciar sesión con tu número de documento.');
$_SESSION['abrir_login'] = true;
sget_redirigir(Config::basePath() . '/index.php');