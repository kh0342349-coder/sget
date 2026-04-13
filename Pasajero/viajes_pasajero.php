<?php
session_start();
include '../assets/conexion.php'; 

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 3) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = $_SESSION['nombre_usuario'] ?? "Pasajero";
$documento = $_SESSION['documento'];

$query_user = $conexion->query("SELECT id_usu FROM usuario WHERE num_doc_usu = '$documento'");
$id_pasajero = ($query_user && $query_user->num_rows > 0) ? $query_user->fetch_assoc()['id_usu'] : 0;

$sql = "SELECT v.id_via, r.nom_rut, v.fec_via, v.val_via, v.est_via 
        FROM viaje v
        INNER JOIN rutas r ON v.id_rut_via = r.id_rut
        WHERE v.id_usu_via = '$id_pasajero' 
        ORDER BY v.fec_via DESC";
$res = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SGET - Mis Viajes</title>
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
                    <a href="pasajero.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition">
                        <i class="fas fa-th-large"></i><span>Inicio</span>
                    </a>
                </li>
                <li>
                    <a href="rutas_pasajero.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition">
                        <i class="fas fa-map-marked-alt"></i><span>Ver Rutas</span>
                    </a>
                </li>
                <li>
                    <a href="viajes_pasajero.php" class="flex items-center space-x-3 p-3 rounded-lg bg-green-50 text-green-600 shadow-sm transition">
                        <i class="fas fa-history"></i><span>Mis Viajes</span>
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
            <div class="text-gray-400 font-bold text-sm italic">Historial de Servicios</div>
            <div class="flex items-center space-x-4">
                <p class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($nombreReal); ?></p>
                <div class="w-10 h-10 bg-green-600 rounded-full flex items-center justify-center text-white font-bold">
                    <?php echo strtoupper(substr($nombreReal, 0, 1)); ?>
                </div>
            </div>
        </header>

        <div class="p-8">
            <div class="mb-8">
                <h1 class="text-2xl font-black text-gray-800 tracking-tight">Mi Historial de Viajes</h1>
                <p class="text-gray-500 text-sm italic">Aquí puede ver el registro de todos sus desplazamientos realizados.</p>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-[10px] font-black text-gray-400 uppercase tracking-widest">
                            <th class="p-5">Ruta Tomada</th>
                            <th class="p-5">Fecha y Hora</th>
                            <th class="p-5">Valor Pagado</th>
                            <th class="p-5 text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if ($res && $res->num_rows > 0): ?>
                            <?php while($viaje = $res->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="p-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 bg-green-50 text-green-600 rounded-xl flex items-center justify-center">
                                                <i class="fas fa-map-pin text-xs"></i>
                                            </div>
                                            <span class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($viaje['nom_rut']); ?></span>
                                        </div>
                                    </td>
                                    <td class="p-5 text-xs text-gray-500 font-medium">
                                        <?php echo date("d/m/Y - h:i A", strtotime($viaje['fec_via'])); ?>
                                    </td>
                                    <td class="p-5">
                                        <span class="text-sm font-black text-gray-800">$<?php echo number_format($viaje['val_via']); ?></span>
                                    </td>
                                    <td class="p-5 text-center">
                                        <?php 
                                        $estado = $viaje['est_via'];
                                        if($estado == 'Finalizado'): ?>
                                            <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-[10px] font-black uppercase border border-blue-200">
                                                <i class="fas fa-check-circle mr-1"></i> Completado
                                            </span>
                                        <?php elseif($estado == 'En curso'): ?>
                                            <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-[10px] font-black uppercase border border-yellow-200">
                                                <i class="fas fa-clock mr-1"></i> En Curso
                                            </span>
                                        <?php elseif($estado == 'Pendiente'): ?>
                                            <span class="px-3 py-1 bg-gray-100 text-gray-500 rounded-full text-[10px] font-black uppercase border border-gray-200 shadow-sm">
                                                <i class="fas fa-hourglass-half mr-1"></i> Pendiente
                                            </span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-[10px] font-black uppercase border border-red-200">
                                                <?php echo $estado; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="p-20 text-center">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-folder-open text-4xl text-gray-200 mb-4"></i>
                                        <p class="text-gray-400 italic">No tiene viajes registrados todavía, sumercé.</p>
                                        <a href="rutas_pasajero.php" class="mt-4 text-green-600 font-bold text-sm hover:underline">¡Pida su primer viaje aquí!</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>