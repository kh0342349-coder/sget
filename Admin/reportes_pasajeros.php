<?php
session_start();
include '../assets/conexion.php';

// Seguridad
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

// CONSULTA
$sql = "SELECT r.*, u.nom_usu, v.nom_via 
        FROM reportes_pasajeros r
        LEFT JOIN usuario u ON r.id_usu_rep = u.id_usu
        LEFT JOIN viaje v ON r.id_via_rep = v.id_via
        ORDER BY r.fecha DESC";

$res = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes de Pasajeros</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-100 flex min-h-screen">

<!-- SIDEBAR -->
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
            <li><a href="reportes.php" class="flex items-center space-x-3 p-3 rounded-lg bg-green-50 text-green-600 shadow-sm transition"><i class="fas fa-file-invoice-dollar"></i><span>Reportes</span></a></li>
        </ul>
    </nav>
     <div class="p-4 border-t border-gray-100">
            <a href="../assets/cerrar.php" class="w-full flex items-center space-x-3 p-3 text-red-500 hover:bg-red-50 rounded-lg transition font-bold text-sm">
                <i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span>
            </a>
        </div>
</aside>

<!-- CONTENIDO -->
<main class="flex-1 ml-64 p-8">

    <h1 class="text-3xl font-black text-gray-800 mb-6">Reportes de Pasajeros</h1>

    <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden">

        <table class="w-full text-left">
            <thead class="bg-gray-50 text-xs uppercase text-gray-400">
                <tr>
                    <th class="p-5">Pasajero</th>
                    <th class="p-5">Descripción</th>
                    <th class="p-5">Estado</th>
                    <th class="p-5">Viaje</th>
                    <th class="p-5 text-center">Acción</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($res && $res->num_rows > 0): ?>
                    <?php while($row = $res->fetch_assoc()): ?>
                        <tr class="border-b hover:bg-gray-50">

                            <td class="p-5 font-bold">
                                <?php echo $row['nom_usu']; ?>
                            </td>

                            <td class="p-5">
                                <?php echo $row['descripcion']; ?>
                            </td>

                            <td class="p-5">
                                <?php
                                $estado = $row['estado'];

                                if ($estado == 'pendiente') {
                                    echo "<span class='bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-bold'>Pendiente</span>";
                                } elseif ($estado == 'asignado') {
                                    echo "<span class='bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-bold'>Asignado</span>";
                                } else {
                                    echo "<span class='bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold'>Completado</span>";
                                }
                                ?>
                            </td>

                            <td class="p-5">
                                <?php echo $row['nom_via'] ?? 'Sin asignar'; ?>
                            </td>

                            <td class="p-5 text-center">
                                <form method="POST" action="actualizar_reporte.php" class="flex flex-col gap-2">

                                    <input type="hidden" name="id" value="<?php echo $row['id_rep']; ?>">

                                    <select name="estado" class="border p-1 rounded text-xs">
                                        <option value="pendiente">Pendiente</option>
                                        <option value="asignado">Asignado</option>
                                        <option value="completado">Completado</option>
                                    </select>

                                    <select name="id_via" class="border p-1 rounded text-xs">
                                        <?php
                                        $viajes = $conexion->query("SELECT * FROM viaje");
                                        while($v = $viajes->fetch_assoc()){
                                            echo "<option value='".$v['id_via']."'>".$v['nom_via']."</option>";
                                        }
                                        ?>
                                    </select>

                                    <button class="bg-green-600 text-white text-xs py-1 rounded hover:bg-green-700">
                                        Guardar
                                    </button>

                                </form>
                            </td>

                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center p-10 text-gray-400">
                            No hay reportes registrados
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div>

</main>

</body>
</html>