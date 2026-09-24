<?php
// desbloquear_sesion.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$idUsuarioSesion = $_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? 0;

$esAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
          || strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;

if (empty($idUsuarioSesion)) {
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'La sesión ha expirado. Inicia sesión nuevamente.']);
        exit();
    }
    header("Location: index.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $passwordIngresada = '';
    
    if (isset($_POST['password'])) {
        $passwordIngresada = (string)$_POST['password'];
    } else {
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);
        if (isset($input['password'])) {
            $passwordIngresada = (string)$input['password'];
        }
    }

    if (trim($passwordIngresada) === '') {
        $error = 'Por favor ingresa tu contraseña.';
    } else {
        $conexionPath = __DIR__ . '/assets/conexion.php';
        if (file_exists($conexionPath)) {
            include_once $conexionPath;
        }

        if (!isset($conexion) || !$conexion) {
            $error = 'Error de conexión a la base de datos.';
        } else {
            $esValido = false;

            // Consultar hash de la contraseña de la BD
            $stmt = $conexion->prepare("SELECT pass_usu FROM sget_usuario WHERE id_usu = ? LIMIT 1");
            if (!$stmt) {
                $stmt = $conexion->prepare("SELECT pass_usu FROM usuario WHERE id_usu = ? LIMIT 1");
            }

            if ($stmt) {
                $stmt->bind_param("i", $idUsuarioSesion);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($row = $res->fetch_assoc()) {
                    $hashBD = isset($row['pass_usu']) ? (string)$row['pass_usu'] : '';

                    if ($hashBD !== '') {
                        if (password_verify($passwordIngresada, $hashBD)) {
                            $esValido = true;
                        } elseif (md5($passwordIngresada) === $hashBD) {
                            $esValido = true;
                        } elseif ($passwordIngresada === $hashBD) {
                            $esValido = true;
                        }
                    }
                }
                $stmt->close();
            }

            if ($esValido) {
                unset($_SESSION['sesion_bloqueada']);
                $_SESSION['ultimo_acceso'] = time();

                $redirect = $_SESSION['url_redirect'] ?? 'index.php';
                unset($_SESSION['url_redirect']);

                if ($esAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'redirect' => $redirect]);
                    exit();
                }

                header("Location: " . $redirect);
                exit();
            } else {
                $error = 'Contraseña incorrecta. Inténtalo de nuevo.';
            }
        }
    }

    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $error]);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesión Bloqueada - SGET</title>
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .lock-card { background: #1e293b; padding: 2.5rem; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.5); width: 100%; max-width: 400px; border: 1px solid rgba(255,255,255,0.1); text-align: center; }
        .lock-card h2 { margin-top: 0; color: #38bdf8; font-size: 1.5rem; }
        .lock-card p { color: #94a3b8; font-size: 0.9rem; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1.25rem; text-align: left; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-size: 0.85rem; color: #cbd5e1; }
        .form-control { width: 100%; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #334155; background: #0f172a; color: #fff; box-sizing: border-box; }
        .btn-submit { width: 100%; padding: 0.75rem; border: none; border-radius: 0.5rem; background: #0284c7; color: #fff; font-weight: 600; cursor: pointer; }
        .btn-submit:hover { background: #0369a1; }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #fca5a5; padding: 0.75rem; border-radius: 0.5rem; font-size: 0.85rem; margin-bottom: 1rem; }
        .logout-link { display: inline-block; margin-top: 1.25rem; color: #64748b; text-decoration: none; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="lock-card">
        <h2>Sesión Bloqueada</h2>
        <p>Tu sesión se ha bloqueado debido a la inactividad. Ingresa tu contraseña para continuar.</p>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="desbloquear_sesion.php" method="POST">
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required autofocus>
            </div>
            <button type="submit" class="btn-submit">Desbloquear</button>
        </form>

        <a href="assets/cerrar.php" class="logout-link">Cerrar sesión e ingresar con otra cuenta</a>
    </div>
</body>
</html>