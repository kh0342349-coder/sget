<?php
date_default_timezone_set('America/Bogota');
include '../assets/conexion.php';
session_start();

// Verificación de sesión y rol
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['doc'])) {
    header("Location: usuarios.php");
    exit();
}

$nombreReal = isset($_SESSION['nombre_usuario']) ? $_SESSION['nombre_usuario'] : "Administrador";
$doc = $_GET['doc'];

$query = "SELECT * FROM usuario WHERE num_doc_usu = '$doc'";
$res = $conexion->query($query);
$u = $res->fetch_assoc();

if (!$u) {
    die("Error: El usuario con documento $doc no existe.");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Editar Usuario</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 flex min-h-screen">

    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col fixed h-full shadow-sm">
        <div class="p-6">
            <h2 class="text-blue-600 font-bold text-lg uppercase tracking-wider leading-tight">Sistema de <br> Transporte</h2>
        </div>

        <nav class="mt-6 px-4 flex-grow">
            <ul class="space-y-2 text-sm font-medium">
                <li><a href="admin.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-chart-pie"></i><span>Dashboard</span></a></li>
                <li><a href="usuarios.php" class="flex items-center space-x-3 p-3 rounded-lg bg-blue-50 text-blue-600 transition shadow-sm"><i class="fas fa-users-cog"></i><span>Usuarios</span></a></li>
                <li><a href="asignaciones.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-clipboard-check"></i><span>Asignaciones</span></a></li>
                <li><a href="rutas.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-map-signs"></i><span>Rutas</span></a></li>
                <li><a href="viajes.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-bus"></i><span>Viajes</span></a></li>
                <li><a href="vehiculos.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-car"></i><span>Vehículos</span></a></li>
                <li><a href="reportes.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-file-invoice-dollar"></i><span>Reportes</span></a></li>
            </ul>
        </nav>

        <div class="p-4 border-t border-gray-100">
            <a href="../assets/cerrar.php" class="w-full flex items-center space-x-3 p-3 text-red-500 hover:bg-red-50 rounded-lg transition font-semibold text-sm">
                <i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span>
            </a>
        </div>
    </aside>

    <main class="flex-1 ml-64 flex flex-col">
        
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8 sticky top-0 z-10">
            <div class="text-gray-400 italic text-sm font-medium">Modo Edición de Usuario</div>
            <div class="flex items-center space-x-4">
                <div class="text-right">
                    <p class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($nombreReal); ?></p>
                    <span class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-bold uppercase tracking-tighter">Administrador</span>
                </div>
                <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white border-2 border-white shadow-sm font-bold">
                    <?php echo strtoupper(substr($nombreReal, 0, 1)); ?>
                </div>
            </div>
        </header>

        <div class="flex-1 flex items-center justify-center p-8">
            <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl border border-gray-100 overflow-hidden">
                <div class="bg-blue-600 p-8 text-white">
                    <h2 class="text-2xl font-bold flex items-center gap-3">
                        <i class="fas fa-user-edit"></i> Editar Datos
                    </h2>
                    <p class="text-blue-100 text-xs mt-2 font-mono uppercase tracking-widest">
                        CC: <?php echo $u['num_doc_usu']; ?>
                    </p>
                </div>

                <form action="actualizar_usuario.php" method="POST" class="p-8 space-y-6">
                    <input type="hidden" name="doc_original" value="<?php echo $u['num_doc_usu']; ?>">

                    <div>
                        <label class="block text-[10px] font-extrabold text-gray-400 uppercase tracking-widest mb-2">Nombre Completo</label>
                        <input type="text" name="nombre" value="<?php echo htmlspecialchars($u['nom_usu']); ?>" required
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:bg-white outline-none transition-all">
                    </div>

                    <div>
                        <label class="block text-[10px] font-extrabold text-gray-400 uppercase tracking-widest mb-2">Correo Electrónico</label>
                        <input type="email" name="correo" value="<?php echo $u['corre_usu']; ?>" required
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:bg-white outline-none transition-all">
                    </div>

                    <div>
                        <label class="block text-[10px] font-extrabold text-gray-400 uppercase tracking-widest mb-2">Rol en el Sistema</label>
                        <select name="rol" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:bg-white outline-none transition-all">
                            <option value="1" <?php if($u['id_rol_usu'] == 1) echo 'selected'; ?>>Administrador</option>
                            <option value="2" <?php if($u['id_rol_usu'] == 2) echo 'selected'; ?>>Conductor</option>
                            <option value="3" <?php if($u['id_rol_usu'] == 3) echo 'selected'; ?>>Pasajero</option>
                        </select>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <a href="usuarios.php" class="flex-1 text-center py-3 border border-gray-200 text-gray-400 font-bold rounded-xl hover:bg-gray-50 transition">
                            Cancelar
                        </a>
                        <button type="submit" class="flex-1 bg-blue-600 text-white py-3 rounded-xl font-bold hover:bg-blue-700 shadow-lg shadow-blue-200 transition">
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

</body>
</html>