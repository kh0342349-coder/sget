<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php'; 

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = $_SESSION['nombre_usuario'] ?? "Administrador";

$query = "SELECT 
            v.id_via, 
            IFNULL(r.nom_rut, 'Ruta no asignada') AS nom_rut, 
            IFNULL(u.nom_usu, 'Sin conductor') AS nom_usu, 
            v.id_usu_via,
            v.val_via, 
            v.fec_via,
            v.hor_sal_via,
            v.hor_lleg_via,
            v.est_via
          FROM viaje v 
          LEFT JOIN rutas r ON v.id_rut_via = r.id_rut 
          LEFT JOIN usuario u ON v.id_usu_via = u.id_usu 
          ORDER BY v.id_via DESC";

$resultado = $conexion->query($query);

if (!$resultado) {
    die("Error en la base de datos: " . $conexion->error);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viajes Programados - Sistema de Transporte</title>
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
                <li><a href="rutas.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-map-signs"></i><span>Rutas</span></a></li>
                <li><a href="viajes.php" class="flex items-center space-x-3 p-3 rounded-lg bg-blue-50 text-blue-600 transition shadow-sm"><i class="fas fa-bus"></i><span>Viajes</span></a></li>
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
            <div class="text-gray-400 italic text-sm font-medium">Gestión de Asignaciones</div>
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
                    <h1 class="text-2xl font-bold text-gray-800">Viajes Programados</h1>
                    <p class="text-gray-500 text-sm mt-1">Monitoreo de salidas, llegadas y estados de conductores.</p>
                </div>
                <a href="nuevo_viaje.php" class="bg-green-600 text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-green-700 transition shadow-lg flex items-center gap-2">
                    <i class="fas fa-plus"></i> ASIGNAR NUEVO VIAJE
                </a>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">ID</th>
                            <th class="px-6 py-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">Ruta / Conductor</th>
                            <th class="px-6 py-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest text-center">Salida</th>
                            <th class="px-6 py-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest text-center">Llegada</th>
                            <th class="px-6 py-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest text-center">Estado</th>
                            <th class="px-6 py-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if($resultado && $resultado->num_rows > 0): ?>
                            <?php while($v = $resultado->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 text-xs font-mono text-gray-400">#<?php echo $v['id_via']; ?></td>
                                <td class="px-6 py-4">
                                    <span class="font-bold text-gray-700 block"><?php echo htmlspecialchars($v['nom_rut']); ?></span>
                                    <span class="text-xs text-gray-500 italic"><i class="fas fa-user-tie mr-1"></i><?php echo htmlspecialchars($v['nom_usu']); ?></span>
                                </td>
                                
                                <td class="px-6 py-4 text-center">
                                    <div class="flex flex-col items-center">
                                        <?php if($v['hor_sal_via']): ?>
                                            <span class="text-[10px] font-bold text-gray-500 uppercase">
                                                <?php echo date("d/m/Y", strtotime($v['hor_sal_via'])); ?>
                                            </span>
                                            <span class="text-xs font-mono text-blue-600 bg-blue-50 px-2 py-0.5 rounded mt-1 shadow-sm">
                                                <i class="far fa-clock mr-1"></i><?php echo date("H:i", strtotime($v['hor_sal_via'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-300">---</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-center">
                                    <div class="flex flex-col items-center">
                                        <?php if($v['hor_lleg_via']): ?>
                                            <span class="text-[10px] font-bold text-gray-500 uppercase">
                                                <?php echo date("d/m/Y", strtotime($v['hor_lleg_via'])); ?>
                                            </span>
                                            <span class="text-xs font-mono text-green-600 bg-green-50 px-2 py-0.5 rounded mt-1 shadow-sm">
                                                <i class="far fa-clock mr-1"></i><?php echo date("H:i", strtotime($v['hor_lleg_via'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-[10px] text-orange-500 italic animate-pulse font-bold uppercase tracking-tighter">
                                                <i class="fas fa-truck-moving mr-1"></i> En ruta...
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-center">
                                    <?php if($v['est_via'] == 'Activo'): ?>
                                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-[10px] font-bold uppercase">Activo</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 bg-gray-100 text-gray-500 rounded-full text-[10px] font-bold uppercase">Terminado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if($v['est_via'] == 'Activo'): ?>
                                        <a href="terminar_viaje.php?id_via=<?php echo $v['id_via']; ?>&id_usu=<?php echo $v['id_usu_via']; ?>" 
                                           onclick="return confirm('¿Confirmar finalización del viaje? El conductor quedará disponible.')"
                                           class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 transition font-bold shadow-md">
                                             <i class="fas fa-check-circle mr-1"></i> Finalizar
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-300 text-xs font-medium"><i class="fas fa-lock mr-1"></i> Cerrado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>