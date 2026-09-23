<?php
// login.php - Procesamiento de inicio de sesión seguro en SGET

// Asegurar que la sesión nativa use cookies seguras antes de iniciarla
session_start([
    'cookie_lifetime' => 86400, // Duración de 1 día (en segundos)
    'cookie_secure'   => true,  // Exclusivo HTTPS (Tridente defensivo)
    'cookie_httponly' => true,  // Inaccesible desde JavaScript (Protección XSS)
    'cookie_samesite' => 'Lax'  // Restricción de peticiones cruzadas (Protección CSRF)
]);

// Ejemplo de validación de credenciales enviadas por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';

    // Consulta e inspección de credenciales en DB (usando password_verify)
    // $user = obtenerUsuarioDB($usuario);
    
    if ($usuario_valido) {
        // Regenerar ID de sesión para prevenir Session Fixation
        session_regenerate_id(true);

        // Guardar datos de sesión requeridos
        $_SESSION['id_usuario'] = $user['id_usu'];
        $_SESSION['nombre']    = $user['nom_usu'];
        $_SESSION['rol']       = $user['rol_usu'];

        // OPCIONAL: Si además generas un token propio de sesión persistente mediante setcookie:
        $token_sesion = bin2hex(random_bytes(32));
        
        setcookie('sget_session_token', $token_sesion, [
            'expires'  => time() + (86400 * 7), // 7 días
            'path'     => '/',
            'domain'   => '', // Tu dominio/localhost
            'secure'   => true,    // Solo transita sobre HTTPS
            'httponly' => true,    // Bloquea document.cookie
            'samesite' => 'Lax'    // Previene CSRF
        ]);

        header('Location: dashboard.php');
        exit();
    } else {
        $error = "Credenciales incorrectas.";
    }
}
?>