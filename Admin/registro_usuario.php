<?php
session_start();
include '../assets/conexion.php';

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

$rol_id = isset($_GET['rol']) ? intval($_GET['rol']) : 3;
$roles_nombres = [1 => 'Administrador', 2 => 'Conductor', 3 => 'Pasajero'];
$nombre_rol = isset($roles_nombres[$rol_id]) ? $roles_nombres[$rol_id] : 'Usuario';

$mensaje = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $doc = $_POST['num_doc'];
    $tipo = $_POST['tip_doc'];
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $pass = password_hash($_POST['pass'], PASSWORD_BCRYPT);
    $rol_db = $_POST['rol_id'];

    /* SOLUCIÓN TEMPORAL: 
       Cambié 'contra_usu' por 'pass_usu'. 
       Si tu columna se llama diferente (ej: 'password'), cámbiala aquí abajo:
    */
    $nombre_columna_password = "pass_usu"; 

    $sql = "INSERT INTO usuario (num_doc_usu, tip_doc_usu, nom_usu, corre_usu, $nombre_columna_password, id_rol_usu) 
            VALUES ('$doc', '$tipo', '$nombre', '$correo', '$pass', '$rol_db')";

    if ($conexion->query($sql) === TRUE) {
        $mensaje = "success";
    } else {
        // Esto nos mostrará el error exacto en pantalla si vuelve a fallar
        $mensaje = "error_db: " . $conexion->error;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar <?php echo $nombre_rol; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #2563eb; --bg: #f3f6f9; --white: #ffffff; --border: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .form-card { background: var(--white); padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 400px; border: 1px solid var(--border); }
        h2 { margin-top: 0; color: #1e293b; font-size: 1.4rem; text-align: center; }
        .subtitle { text-align: center; color: #64748b; font-size: 0.9rem; margin-bottom: 25px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 5px; }
        input, select { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; box-sizing: border-box; }
        .btn-save { width: 100%; background: var(--primary); color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 10px; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.85rem; text-align: center; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="form-card">
        <h2>Nuevo <?php echo $nombre_rol; ?></h2>
        
        <?php if ($mensaje == "success"): ?>
            <div class="alert alert-success">¡Registrado con éxito! Redirigiendo...</div>
            <script>setTimeout(() => { window.location.href = 'usuarios.php'; }, 2000);</script>
        <?php elseif (strpos($mensaje, "error_db") !== false): ?>
            <div class="alert alert-error">Error técnico: <?php echo $mensaje; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="rol_id" value="<?php echo $rol_id; ?>">
            <div class="form-group">
                <label>Tipo Doc.</label>
                <select name="tip_doc"><option value="CC">CC</option><option value="TI">TI</option></select>
            </div>
            <div class="form-group">
                <label>Documento</label>
                <input type="number" name="num_doc" required>
            </div>
            <div class="form-group">
                <label>Nombre</label>
                <input type="text" name="nombre" required>
            </div>
            <div class="form-group">
                <label>Correo</label>
                <input type="email" name="correo" required>
            </div>
            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="pass" required>
            </div>
            <button type="submit" class="btn-save">Guardar Usuario</button>
            <a href="usuarios.php" style="display:block; text-align:center; margin-top:10px; color: #64748b; text-decoration:none; font-size:0.8rem;">Volver</a>
        </form>
    </div>
</body>
</html>