<?php
Date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php'; 

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 3) {
    header("Location: ../index.php");
    exit();
}
$documento = $_SESSION['documento'];
$nombreReal = $_SESSION['nombre_usuario'] ?? "Pasajero";

$query_user = $conexion->query("SELECT id_usu FROM usuario WHERE num_doc_usu = '$documento'");
$id_pasajero = 0;
if ($query_user && $query_user->num_rows > 0) {
    $user_data = $query_user->fetch_assoc();
    $id_pasajero = $user_data['id_usu'];
}
$res_viajes = $conexion->query("SELECT COUNT(*) as total FROM viaje WHERE id_usu_via = '$id_pasajero' AND est_via = 'Finalizado'"); 
$total_viajes = ($res_viajes) ? $res_viajes->fetch_assoc()['total'] : 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SGET - Pasajero</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 flex min-h-screen">

    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col fixed h-full shadow-sm z-20">
        <div class="p-6">
            <h2 class="text-green-600 font-bold text-lg uppercase tracking-wider">SGET <br> PASAJERO</h2>
        </div>
        <nav class="mt-6 px-4 flex-grow">
            <ul class="space-y-2 text-sm font-medium">
                <li>
                    <a href="pasajero.php" class="flex items-center space-x-3 p-3 rounded-lg bg-green-50 text-green-600 shadow-sm transition">
                        <i class="fas fa-th-large text-lg"></i><span>Inicio</span>
                    </a>
                </li>
                <li>
                    <a href="rutas_pasajero.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition">
                        <i class="fas fa-map-marked-alt text-lg"></i><span>Ver Rutas</span>
                    </a>
                </li>
                <li>
                    <a href="viajes_pasajero.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition">
                        <i class="fas fa-history text-lg"></i><span>Mis Viajes</span>
                    </a>
                </li>
            </ul>
        </nav>
        <div class="p-4 border-t border-gray-100">
            <a href="../assets/cerrar.php" class="flex items-center space-x-3 p-3 text-red-500 hover:bg-red-50 rounded-lg transition font-bold">
                <i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span>
            </a>
        </div>
    </aside>

    <main class="flex-1 ml-64 flex flex-col">
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8 sticky top-0 z-10">
            <div class="text-gray-400 font-bold italic text-sm">Panel de Control</div>
            <div class="flex items-center space-x-4">
                <div class="text-right">
                    <p class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($nombreReal); ?></p>
                    <p class="text-[10px] text-green-500 font-bold uppercase italic tracking-widest">Pasajero</p>
                </div>
                <div class="w-10 h-10 bg-green-600 rounded-full flex items-center justify-center text-white border-2 border-white shadow-sm font-bold">
                    <?php echo strtoupper(substr($nombreReal, 0, 1)); ?>
                </div>
            </div>
        </header>

        <div class="p-8">
            <div class="mb-10">
                <h1 class="text-3xl font-black text-gray-800 tracking-tight">¡Hola, <?php echo explode(' ', $nombreReal)[0]; ?>!</h1>
                <p class="text-gray-500 text-sm italic mt-1">Bienvenido al sistema de gestión de transporte.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
                <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-xl shadow-gray-100/50 flex items-center gap-6">
                    <div class="w-14 h-14 bg-green-50 text-green-600 rounded-2xl flex items-center justify-center text-2xl">
                        <i class="fas fa-route"></i>
                    </div>
                    <div>
                        <h3 class="text-gray-400 text-[10px] font-black uppercase tracking-widest mb-1">Viajes Realizados</h3>
                        <p class="text-3xl font-black text-gray-800"><?php echo $total_viajes; ?></p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-xl shadow-gray-100/50 flex items-center gap-6">
                    <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-2xl">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div>
                        <h3 class="text-gray-400 text-[10px] font-black uppercase tracking-widest mb-1">Saldo / Puntos</h3>
                        <p class="text-3xl font-black text-gray-800">0</p>
                    </div>
                </div>
            </div>

            <div class="relative overflow-hidden bg-gradient-to-br from-green-600 to-green-700 rounded-[2rem] p-10 text-white shadow-2xl shadow-green-200">
                <div class="relative z-10 flex flex-col items-center text-center">
                    <h2 class="text-2xl font-bold mb-2">¿A dónde quieres ir hoy?</h2>
                    <p class="text-green-100 mb-8 max-w-md italic">Explora las rutas disponibles y viaja seguro con SGET en Fusagasugá.</p>
                    <a href="rutas_pasajero.php" class="bg-white text-green-700 px-10 py-4 rounded-2xl font-black uppercase text-sm hover:scale-105 transition-transform flex items-center gap-3 shadow-lg">
                        <i class="fas fa-search"></i> Buscar Rutas Disponibles
                    </a>
                </div>
                <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
                <div class="absolute -left-10 -top-10 w-48 h-48 bg-black/10 rounded-full blur-2xl"></div>
            </div>
        </div>
    </main>
</body>
</html>