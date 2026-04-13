<?php
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

// Obtener el ID del usuario usando num_doc_usu
$query_user = $conexion->query("SELECT id_usu FROM usuario WHERE num_doc_usu = '$documento'");

if ($query_user && $query_user->num_rows > 0) {
    $user_data = $query_user->fetch_assoc();
    $id_conductor = $user_data['id_usu'];
} else {
    $id_conductor = $_SESSION['id_usuario'] ?? 0;
}

// 3. Consulta de Viajes
$sql = "SELECT v.id_via, r.nom_rut, v.fec_via, v.val_via, v.est_via 
        FROM viaje v
        INNER JOIN rutas r ON v.id_rut_via = r.id_rut
        WHERE v.id_usu_via = '$id_conductor' 
        ORDER BY v.fec_via DESC";
$res = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Viajes - SGET</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 flex min-h-screen">

    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col fixed h-full shadow-sm">
        <div class="p-6">
            <h2 class="text-blue-600 font-bold text-lg uppercase tracking-wider">SGET <br> Conductor</h2>
        </div>
        <nav class="mt-6 px-4 flex-grow">
            <ul class="space-y-2 text-sm font-medium">
                <li>
                    <a href="conductor.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition">
                        <i class="fas fa-th-large"></i><span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="viajes_conductor.php" class="flex items-center space-x-3 p-3 rounded-lg bg-blue-50 text-blue-600 shadow-sm transition">
                        <i class="fas fa-route"></i><span>Mis Viajes</span>
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
            <div class="text-gray-400 font-bold italic text-sm italic">Panel de Rutas</div>
            <div class="flex items-center space-x-4">
                <div class="text-right">
                    <p class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($nombreReal); ?></p>
                    <p class="text-[10px] text-blue-500 font-bold uppercase italic">Conductor Activo</p>
                </div>
                <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white border-2 border-white shadow-sm font-bold">
                    <?php echo strtoupper(substr($nombreReal, 0, 1)); ?>
                </div>
            </div>
        </header>

        <div class="p-8">
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Mis Servicios Asignados</h1>
                <p class="text-gray-500 text-sm italic">Gestione sus rutas diarias y finalice los servicios al llegar a su destino.</p>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">
                            <th class="p-4">Ruta</th>
                            <th class="p-4">Fecha</th>
                            <th class="p-4">Valor</th>
                            <th class="p-4 text-center">Estado</th>
                            <th class="p-4 text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if ($res && $res->num_rows > 0): ?>
                            <?php while($row = $res->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center text-blue-600">
                                            <i class="fas fa-map-marker-alt text-[10px]"></i>
                                        </div>
                                        <span class="text-sm font-bold text-gray-700"><?php echo $row['nom_rut']; ?></span>
                                    </div>
                                </td>
                                <td class="p-4 text-xs text-gray-500 font-medium"><?php echo $row['fec_via']; ?></td>
                                <td class="p-4"><span class="text-sm font-bold text-green-600">$<?php echo number_format($row['val_via']); ?></span></td>
                                
                                <td class="p-4 text-center">
                                    <?php if($row['est_via'] == 'En curso'): ?>
                                        <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-[10px] font-bold border border-yellow-200 uppercase tracking-tighter">En Curso</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-[10px] font-bold border border-blue-200 uppercase tracking-tighter">Finalizado</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="p-4 text-center">
                                    <?php if($row['est_via'] == 'En curso'): ?>
                                        <a href="finalizar_viaje.php?id=<?php echo $row['id_via']; ?>" 
                                           class="inline-flex items-center gap-2 bg-red-500 text-white px-4 py-2 rounded-xl text-[10px] font-bold hover:bg-red-600 transition shadow-lg shadow-red-50"
                                           onclick="return confirm('¿Está seguro de que desea finalizar este viaje, parce?')">
                                           <i class="fas fa-flag-checkered"></i> FINALIZAR
                                        </a>
                                    <?php else: ?>
                                        <div class="text-green-500 text-[10px] font-bold uppercase tracking-widest">
                                            <i class="fas fa-check-circle mr-1"></i> Completado
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="p-12 text-center text-gray-400 italic">No hay registros de viajes.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>