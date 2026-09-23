<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

// Capturar contraseña ya sea por POST tradicional o por JSON crudo
$passwordIngresada = '';
if (isset($_POST['password'])) {
    $passwordIngresada = trim($_POST['password']);
} else {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);
    if (isset($input['password'])) {
        $passwordIngresada = trim($input['password']);
    }
}

$idUsuarioSesión = $_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? 0;

if (empty($passwordIngresada)) {
    echo json_encode(['success' => false, 'message' => 'Por favor ingresa tu contraseña.']);
    exit;
}

if (empty($idUsuarioSesión)) {
    echo json_encode(['success' => false, 'message' => 'La sesión ha expirado. Por favor inicia sesión nuevamente.']);
    exit;
}

// Cargar conexión de manera segura desde la raíz
$conexionPath = __DIR__ . '/assets/conexion.php';
if (file_exists($conexionPath)) {
    include_once $conexionPath;
}

if (!isset($conexion) || !$conexion) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos.']);
    exit;
}

$esValido = false;

// Consultar el hash real del usuario directamente en la base de datos
$stmt = $conexion->prepare("SELECT pass_usu FROM usuario WHERE id_usu = ? LIMIT 1");
if (!$stmt) {
    $stmt = $conexion->prepare("SELECT pass_usu FROM sget_usuario WHERE id_usu = ? LIMIT 1");
}

if ($stmt) {
    $stmt->bind_param("i", $idUsuarioSesión);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $hashBD = $row['pass_usu'];
        if (password_verify($passwordIngresada, $hashBD) || md5($passwordIngresada) === $hashBD || $passwordIngresada === $hashBD) {
            $esValido = true;
        }
    }
    $stmt->close();
}

if ($esValido) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta. Inténtalo de nuevo.']);
}
exit;