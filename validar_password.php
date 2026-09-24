<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Conexión a la base de datos
include_once __DIR__ . '/assets/conexion.php';

$idUsuario = $_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? 0;

// Soporte para JSON enviado por fetch() o FormData ($_POST)
$inputJSON = json_decode(file_get_contents('php://input'), true);
$passwordIngresada = trim($inputJSON['password'] ?? $_POST['password'] ?? '');

if (!$idUsuario || empty($passwordIngresada)) {
    echo json_encode(['success' => false, 'message' => 'Ingresa tu contraseña para continuar.']);
    exit;
}

// Inicializar contador de intentos si no existe
if (!isset($_SESSION['intentos_password'])) {
    $_SESSION['intentos_password'] = 0;
}

// Consulta a la tabla usuario (columna pass_usu)
$stmt = $conexion->prepare("SELECT pass_usu FROM usuario WHERE id_usu = ? LIMIT 1");

if ($stmt) {
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    $stmt->close();

    $hashBD = $usuario['pass_usu'] ?? '';

    // Verificación por password_verify o texto plano de respaldo
    $esValida = password_verify($passwordIngresada, $hashBD) || ($passwordIngresada === $hashBD);

    if ($esValida) {
        $_SESSION['intentos_password'] = 0; // Reiniciar contador al acertar
        echo json_encode(['success' => true, 'message' => 'Sesión desbloqueada con éxito.']);
        exit;
    }
}

// Contraseña incorrecta: Incrementar contador de intentos
$_SESSION['intentos_password']++;
$intentosRestantes = 5 - $_SESSION['intentos_password'];

if ($_SESSION['intentos_password'] >= 5) {
    $_SESSION['intentos_password'] = 0;
    echo json_encode([
        'success' => false, 
        'cerrar_sesion' => true, 
        'message' => 'Has superado los 5 intentos permitidos. Cerrando sesión...'
    ]);
    exit;
}

echo json_encode([
    'success' => false, 
    'cerrar_sesion' => false,
    'message' => "Contraseña incorrecta. Te quedan {$intentosRestantes} intento(s) de 5."
]);
exit;