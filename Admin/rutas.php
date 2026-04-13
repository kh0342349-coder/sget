<?php
date_default_timezone_set('America/Bogota');    
session_start();
include '../assets/conexion.php'; 

// Verificación de seguridad (Solo Admin)
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = $_SESSION['nombre_usuario'] ?? "Administrador";

// Consultar las rutas vinculando el nombre del conductor desde la tabla de viajes
$query = "SELECT r.id_rut, r.nom_rut, r.ori_rut, r.des_rut, r.dis_rut, u.nom_usu as conductor 
          FROM rutas r 
          LEFT JOIN viaje v ON r.id_rut = v.id_rut_via
          LEFT JOIN usuario u ON v.id_usu_via = u.num_doc_usu";

$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Rutas - Sistema de Transporte</title>
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
                <li><a href="usuarios.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-users-cog"></i><span>Usuarios</span></a></li>
                <li><a href="asignaciones.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-clipboard-check"></i><span>Asignaciones</span></a></li>
                <li><a href="rutas.php" class="flex items-center space-x-3 p-3 rounded-lg bg-blue-50 text-blue-600 transition shadow-sm"><i class="fas fa-map-signs"></i><span>Rutas</span></a></li>
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
            <div class="text-gray-400 italic text-sm font-medium">Panel de Control de Trayectos</div>
            <div class="flex items-center space-x-4">
                <div class="text-right">
                    <p class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($nombreReal); ?></p>
                    <span class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-bold uppercase">Admin</span>
                </div>
                <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold border-2 border-white shadow-sm">
                    <?php echo strtoupper(substr($nombreReal, 0, 1)); ?>
                </div>
            </div>
        </header>

        <div class="p-8">
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Control de Rutas</h1>
                    <p class="text-gray-500 text-sm mt-1">Administre los trayectos fijos del sistema.</p>
                </div>
                <a href="nueva_ruta.php" class="bg-blue-600 text-white px-6 py-2.5 rounded-xl font-bold text-sm hover:bg-blue-700 transition shadow-lg flex items-center gap-2">
                    <i class="fas fa-plus"></i> NUEVA RUTA
                </a>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">Ruta</th>
                            <th class="px-6 py-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">Trayecto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php while($r = $resultado->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <span class="block font-bold text-gray-700"><?php echo htmlspecialchars($r['nom_rut']); ?></span>
                                <span class="text-[10px] text-blue-500 font-mono bg-blue-50 px-1.5 py-0.5 rounded">ID: #<?php echo $r['id_rut']; ?></span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <div class="flex items-center gap-2 mb-1">
                                    <i class="fas fa-map-marker-alt text-red-400 text-xs"></i> 
                                    <strong>Origen:</strong> <?php echo htmlspecialchars($r['ori_rut']); ?>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-flag-checkered text-green-500 text-xs"></i> 
                                    <strong>Destino:</strong> <?php echo htmlspecialchars($r['des_rut']); ?>
                                </div>
                                <span class="text-[10px] text-gray-400 mt-1 block">Distancia: <?php echo $r['dis_rut']; ?> KM</span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>