<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php'; 

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = isset($_SESSION['nombre_usuario']) ? $_SESSION['nombre_usuario'] : "Administrador";
$documento  = $_SESSION['documento'];

$total_u = $conexion->query("SELECT COUNT(*) as t FROM usuario")->fetch_assoc()['t'];
$total_p = $conexion->query("SELECT COUNT(*) as t FROM usuario WHERE id_rol_usu = 3")->fetch_assoc()['t'];
$total_c = $conexion->query("SELECT COUNT(*) as t FROM usuario WHERE id_rol_usu = 2")->fetch_assoc()['t'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Sistema de Transporte</title>
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
                <li>
                    <a href="admin.php" class="flex items-center space-x-3 p-3 rounded-lg bg-blue-50 text-blue-600 transition shadow-sm">
                        <i class="fas fa-chart-pie"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="usuarios.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition">
                        <i class="fas fa-users-cog"></i>
                        <span>Usuarios</span>
                    </a>
                </li>
                <li>
                    <a href="asignaciones.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition">
                        <i class="fas fa-clipboard-check"></i>
                        <span>Asignaciones</span>
                    </a>
                </li>
                <li>
                    <a href="rutas.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition">
                        <i class="fas fa-map-signs"></i>
                        <span>Rutas</span>
                    </a>
                </li>
                <li>
                    <a href="viajes.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition">
                        <i class="fas fa-bus"></i>
                        <span>Viajes</span>
                    </a>
                </li>
                <li>
                    <a href="vehiculos.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition">
                        <i class="fas fa-car"></i>
                        <span>Vehículos</span>
                    </a>
                </li>
                <li>
                    <a href="reportes.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Reportes</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="p-4 border-t border-gray-100">
            <a href="../assets/cerrar.php" class="w-full flex items-center space-x-3 p-3 text-red-500 hover:bg-red-50 rounded-lg transition font-semibold text-sm">
                <i class="fas fa-sign-out-alt"></i>
                <span>Cerrar Sesión</span>
            </a>
        </div>
    </aside>

    <main class="flex-1 ml-64 flex flex-col">
        
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8 sticky top-0 z-10">
            <div class="text-gray-400 font-rolo italic text-sm">Panel Administrativo</div>
            
            <div class="flex items-center space-x-4">
                <div class="text-right">
                    <p class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($nombreReal); ?></p>
                    <p class="text-[10px] text-green-500 font-bold uppercase">Online</p>
                </div>
                <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white border-2 border-white shadow-sm font-bold">
                    <?php echo strtoupper(substr($nombreReal, 0, 1)); ?>
                </div>
            </div>
        </header>

        <div class="p-8">
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-800">Dashboard General</h1>
                <p class="text-gray-500 text-sm mt-1">Resumen del estado actual del sistema de transporte, ala.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-3 bg-blue-100 text-blue-600 rounded-xl"><i class="fas fa-users text-xl"></i></div>
                        <span class="text-xs font-bold text-gray-400">Total</span>
                    </div>
                    <p class="text-gray-400 text-xs font-bold uppercase tracking-widest">Usuarios Sistema</p>
                    <h4 class="text-4xl font-extrabold text-gray-800"><?php echo $total_u; ?></h4>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition border-l-4 border-l-green-500">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-3 bg-green-100 text-green-600 rounded-xl"><i class="fas fa-id-card text-xl"></i></div>
                        <span class="text-xs font-bold text-green-500 bg-green-50 px-2 py-1 rounded">Activos</span>
                    </div>
                    <p class="text-gray-400 text-xs font-bold uppercase tracking-widest">Conductores</p>
                    <h4 class="text-4xl font-extrabold text-gray-800"><?php echo $total_c; ?></h4>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition border-l-4 border-l-orange-500">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-3 bg-orange-100 text-orange-600 rounded-xl"><i class="fas fa-walking text-xl"></i></div>
                        <span class="text-xs font-bold text-orange-500 bg-orange-50 px-2 py-1 rounded">Registrados</span>
                    </div>
                    <p class="text-gray-400 text-xs font-bold uppercase tracking-widest">Pasajeros</p>
                    <h4 class="text-4xl font-extrabold text-gray-800"><?php echo $total_p; ?></h4>
                </div>
            </div>
        </div>
    </main>

</body>
</html>