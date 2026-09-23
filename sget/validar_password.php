<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

include_once __DIR__ . '/assets/conexion.php';

$idUsuario = $_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? 0;
$passwordIngresada = $_POST['password'] ?? '';

if (!$idUsuario || empty($passwordIngresada)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos o sesión no encontrada.']);
    exit;
}

// Inicializar el contador de intentos en la sesión si no existe
if (!isset($_SESSION['intentos_password'])) {
    $_SESSION['intentos_password'] = 0;
}

// Consultar la contraseña guardada en la BD para este usuario
$stmt = $conexion->prepare("SELECT contrasena, password_usu FROM usuarios WHERE id_usu = ? OR id_usuario = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("ii", $idUsuario, $idUsuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    $stmt->close();

    $hashBD = $usuario['contrasena'] ?? $usuario['password_usu'] ?? '';

    // Verificar usando password_verify o comparación directa (según cómo la almacenes en tu sistema)
    $esValida = password_verify($passwordIngresada, $hashBD) || ($passwordIngresada === $hashBD);

    if ($esValida) {
        $_SESSION['intentos_password'] = 0; // Reiniciar contador al acertar
        echo json_encode(['success' => true, 'message' => 'Sesión desbloqueada.']);
        exit;
    }
}

// Si la contraseña es incorrecta, incrementar los intentos
$_SESSION['intentos_password']++;
$intentosRestantes = 5 - $_SESSION['intentos_password'];

if ($_SESSION['intentos_password'] >= 5) {
    $_SESSION['intentos_password'] = 0;
    echo json_encode(['success' => false, 'cerrar_sesion' => true, 'message' => 'Has superado los 5 intentos permitidos. Cerrando sesión...']);
    exit;
}

echo json_encode([
    'success' => false, 
    'cerrar_sesion' => false,
    'message' => "Contraseña incorrecta. Te quedan {$intentosRestantes} intentos de 5."
]);
exit;