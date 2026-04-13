<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php'; 

// 1. Verificación de seguridad
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 2) {
    header("Location: ../index.php");
    exit();
}

// 2. Datos de sesión
$nombreReal = $_SESSION['nombre_usuario'] ?? "Conductor";
$documento  = $_SESSION['documento'];

// 3. Obtener ID y contar viajes
$query_user = $conexion->query("SELECT id_usu FROM usuario WHERE num_doc_usu = '$documento'");
$total_viajes = 0;

if ($query_user && $query_user->num_rows > 0) {
    $user_data = $query_user->fetch_assoc();
    $id_conductor = $user_data['id_usu'];

    // Contar solo los viajes de este conductor
    $res_viajes = $conexion->query("SELECT COUNT(*) as total FROM viaje WHERE id_usu_via = '$id_conductor'");
    if ($res_viajes) {
        $total_viajes = $res_viajes->fetch_assoc()['total'];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Conductor - SGET</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 flex min-h-screen">

    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col fixed h-full">
        <div class="p-6">
            <h2 class="text-blue-600 font-bold text-lg uppercase tracking-wider">SGET</h2>
        </div>
        <nav class="mt-6 px-4 flex-grow">
            <ul class="space-y-2 text-sm">
                <li>
                    <a href="#" class="flex items-center space-x-3 p-3 rounded-lg bg-blue-50 text-blue-600 font-bold">
                        <i class="fas fa-th-large"></i><span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="viajes_conductor.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition">
                        <i class="fas fa-route"></i><span>Mis Viajes</span>
                    </a>
                </li>
            </ul>
        </nav>
        <div class="p-4 border-t border-gray-100">
            <a href="../assets/cerrar.php" class="flex items-center space-x-3 p-3 text-red-500 hover:bg-red-50 rounded-lg transition font-bold text-sm">
                <i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span>
            </a>
        </div>
    </aside>

    <main class="flex-1 ml-64 flex flex-col">
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8">
            <div class="text-gray-500 font-medium">Panel de Control</div>
            <div class="flex items-center space-x-3">
                <span class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($nombreReal); ?></span>
                <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                    <?php echo strtoupper(substr($nombreReal, 0, 1)); ?>
                </div>
            </div>
        </header>

        <div class="p-10">
            <div class="mb-10">
                <h1 class="text-3xl font-bold text-gray-800">Hola, <?php echo explode(' ', $nombreReal)[0]; ?></h1>
                <p class="text-gray-500 italic">Resumen de su actividad en el sistema.</p>
            </div>

            <div class="max-w-xs">
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 flex flex-col items-center text-center">
                    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center text-blue-600 mb-4 text-2xl">
                        <i class="fas fa-bus"></i>
                    </div>
                    <p class="text-gray-400 text-xs font-bold uppercase tracking-widest mb-1">Total de Viajes</p>
                    <h4 class="text-5xl font-black text-gray-800"><?php echo $total_viajes; ?></h4>
                </div>
            </div>
        </div>
    </main>

</body>
</html>