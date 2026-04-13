<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php';

// 1. Verificación de Seguridad (Admin = Rol 1)
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

// 2. Control de Pestañas (Tabs)
$tab = $_GET['tab'] ?? 'general'; 
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SGET - Reportes Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 flex min-h-screen">

    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col fixed h-full shadow-sm">
        <div class="p-6">
            <h2 class="text-green-600 font-bold text-lg uppercase tracking-wider leading-tight">SGET <br> ADMIN</h2>
        </div>
        <nav class="mt-6 px-4 flex-grow">
            <ul class="space-y-2 text-sm font-medium">
                <li><a href="admin.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-chart-pie"></i><span>Dashboard</span></a></li>
                <li><a href="usuarios.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-users-cog"></i><span>Usuarios</span></a></li>
                <li><a href="asignaciones.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-clipboard-check"></i><span>Asignaciones</span></a></li>
                <li><a href="rutas.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-map-signs"></i><span>Rutas</span></a></li>
                <li><a href="viajes.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-bus"></i><span>Viajes</span></a></li>
                <li><a href="reportes.php" class="flex items-center space-x-3 p-3 rounded-lg bg-green-50 text-green-600 shadow-sm transition"><i class="fas fa-file-invoice-dollar"></i><span>Reportes</span></a></li>
            </ul>
        </nav>
        <div class="p-4 border-t border-gray-100">
            <a href="../assets/cerrar.php" class="w-full flex items-center space-x-3 p-3 text-red-500 hover:bg-red-50 rounded-lg transition font-bold text-sm">
                <i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span>
            </a>
        </div>
    </aside>

    <main class="flex-1 ml-64 p-8">
        <div class="mb-10">
            <h1 class="text-3xl font-black text-gray-800 tracking-tight">Panel de Reportes</h1>
            <p class="text-gray-500 italic text-sm">Monitoreo exclusivo de actividad y solicitudes de pasajeros.</p>
        </div>

        <div class="flex space-x-2 mb-8 bg-white p-2 rounded-2xl shadow-sm border border-gray-100 w-fit">
            <a href="reportes.php?tab=general" 
               class="px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest transition-all <?php echo $tab == 'general' ? 'bg-green-600 text-white shadow-lg shadow-green-200' : 'text-gray-400 hover:bg-gray-50'; ?>">
                <i class="fas fa-chart-pie mr-2"></i> General
            </a>
            <a href="reportes.php?tab=pasajeros" 
               class="px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest transition-all <?php echo $tab == 'pasajeros' ? 'bg-green-600 text-white shadow-lg shadow-green-200' : 'text-gray-400 hover:bg-gray-50'; ?>">
                <i class="fas fa-user-friends mr-2"></i> Rutas de Pasajeros
            </a>
        </div>

        <?php if ($tab == 'general'): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm">
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Resumen General</h3>
                    <p class="text-gray-400 text-sm italic">Gestión de indicadores globales del sistema de transporte.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($tab == 'pasajeros'): ?>
            <div class="bg-white rounded-[2.5rem] shadow-xl shadow-gray-200/50 border border-gray-100 overflow-hidden">
                <div class="p-8 border-b border-gray-50 flex justify-between items-center bg-gray-50/30">
                    <div>
                        <h2 class="text-xl font-black text-gray-800 uppercase tracking-tighter">Pasajeros Activos</h2>
                        <p class="text-xs text-gray-400 font-bold uppercase mt-1">Solo se muestran usuarios con rol Pasajero</p>
                    </div>
                </div>
                
                <table class="w-full text-left">
                    <thead class="bg-gray-50/80 text-[10px] font-black text-gray-400 uppercase tracking-widest">
                        <tr>
                            <th class="p-6">Nombre Pasajero</th>
                            <th class="p-6">Ruta Elegida</th>
                            <th class="p-6">Fecha Reporte</th>
                            <th class="p-6 text-center">Valor</th>
                            <th class="p-6 text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php
                        // Modificación clave: Filtramos por u.id_rol_usu = 3
                        $sql = "SELECT v.id_via, u.nom_usu, r.nom_rut, v.fec_via, v.val_via, v.est_via 
                                FROM viaje v
                                INNER JOIN usuario u ON v.id_usu_via = u.id_usu
                                INNER JOIN rutas r ON v.id_rut_via = r.id_rut
                                WHERE u.id_rol_usu = 3
                                ORDER BY v.fec_via DESC";
                        $res = $conexion->query($sql);

                        if ($res && $res->num_rows > 0):
                            while($row = $res->fetch_assoc()): ?>
                                <tr class="hover:bg-green-50/20 transition-colors group">
                                    <td class="p-6">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 bg-green-100 text-green-700 rounded-2xl flex items-center justify-center font-black">
                                                <?php echo strtoupper(substr($row['nom_usu'], 0, 1)); ?>
                                            </div>
                                            <span class="text-sm font-black text-gray-800"><?php echo htmlspecialchars($row['nom_usu']); ?></span>
                                        </div>
                                    </td>
                                    <td class="p-6">
                                        <span class="inline-flex items-center px-3 py-1 bg-green-50 text-green-700 rounded-lg text-xs font-bold italic">
                                            <i class="fas fa-map-marker-alt mr-2"></i> <?php echo $row['nom_rut']; ?>
                                        </span>
                                    </td>
                                    <td class="p-6 text-xs text-gray-500 font-medium">
                                        <?php echo date("d/m/Y - h:i A", strtotime($row['fec_via'])); ?>
                                    </td>
                                    <td class="p-6 text-center">
                                        <span class="text-sm font-black text-gray-800">$<?php echo number_format($row['val_via']); ?></span>
                                    </td>
                                    <td class="p-6 text-center">
                                        <?php 
                                        $est = $row['est_via'];
                                        $color = ($est == 'Pendiente') ? 'bg-yellow-100 text-yellow-700 border-yellow-200' : 'bg-blue-100 text-blue-700 border-blue-200';
                                        ?>
                                        <span class="px-4 py-1.5 <?php echo $color; ?> rounded-full text-[10px] font-black uppercase border shadow-sm">
                                            <?php echo $est; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; 
                        else: ?>
                            <tr>
                                <td colspan="5" class="p-20 text-center text-gray-400 italic">
                                    <i class="fas fa-user-slash text-4xl mb-3 block"></i>
                                    No hay solicitudes de pasajeros registradas.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>