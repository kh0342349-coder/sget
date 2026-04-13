<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php'; 

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = $_SESSION['nombre_usuario'] ?? "Administrador";
// Consultamos los vehículos registrados
$query = "SELECT * FROM vehiculo";
$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Vehículos - Sistema de Transporte</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                <li><a href="viajes.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-bus"></i><span>Viajes</span></a></li>
                <li><a href="vehiculos.php" class="flex items-center space-x-3 p-3 rounded-lg bg-blue-50 text-blue-600 transition shadow-sm"><i class="fas fa-car"></i><span>Vehículos</span></a></li>
                <li><a href="reportes.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-file-invoice-dollar"></i><span>Reportes</span></a></li>
            </ul>
        </nav>
    </aside>

    <main class="flex-1 ml-64 flex flex-col">
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8 sticky top-0 z-10">
            <div class="text-gray-400 italic text-sm font-medium">Control de Flota</div>
            <div class="flex items-center space-x-4">
                <p class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($nombreReal); ?></p>
            </div>
        </header>

        <div class="p-8">
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Vehículos Registrados</h2>
                    <p class="text-gray-500 text-sm">Administre los carros y motos disponibles en el sistema.</p>
                </div>
                <a href="nuevo_vehiculo.php" class="bg-blue-600 text-white px-5 py-2.5 rounded-xl font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-100 flex items-center gap-2">
                    <i class="fas fa-plus text-xs"></i> AGREGAR VEHÍCULO
                </a>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">ID</th>
                            <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">Placa</th>
                            <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">Modelo / Línea</th>
                            <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest text-center">Capacidad</th>
                            <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">Estado</th>
                            <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php while($v = $resultado->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="p-4 text-xs text-gray-400 font-mono">#<?php echo $v['id_veh']; ?></td>
                            <td class="p-4"><span class="bg-gray-100 px-2 py-1 rounded text-sm font-bold text-gray-700 border border-gray-200"><?php echo $v['pla_veh']; ?></span></td>
                            <td class="p-4 text-sm text-gray-600 font-medium"><?php echo $v['mode_veh']; ?></td>
                            <td class="p-4 text-sm text-center text-gray-500"><?php echo $v['cap_veh']; ?> pers.</td>
                            <td class="p-4">
                                <?php if($v['est_veh'] == 1): ?>
                                    <span class="px-3 py-1 bg-green-100 text-green-600 text-[10px] font-bold rounded-full">ACTIVO</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 bg-red-100 text-red-600 text-[10px] font-bold rounded-full">INACTIVO</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <div class="flex justify-center gap-2">
                                    <a href="editar_vehiculo.php?id=<?php echo $v['id_veh']; ?>" class="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition" title="Editar"><i class="fas fa-edit"></i></a>
                                    <a href="cambiar_estado_veh.php?id=<?php echo $v['id_veh']; ?>&estado=<?php echo $v['est_veh'] == 1 ? 0 : 1; ?>" 
                                       class="p-2 <?php echo $v['est_veh'] == 1 ? 'text-red-400 hover:text-red-600' : 'text-green-400 hover:text-green-600'; ?> transition" 
                                       title="<?php echo $v['est_veh'] == 1 ? 'Desactivar' : 'Activar'; ?>">
                                        <i class="fas <?php echo $v['est_veh'] == 1 ? 'fa-toggle-on' : 'fa-toggle-off'; ?> text-lg"></i>
                                    </a>
                                </div>
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