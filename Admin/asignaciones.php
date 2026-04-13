<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php';

// 1. Verificación de Seguridad (Solo Admin = Rol 1)
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

// 2. Consultas para los SELECTS
// Traer solicitudes de pasajeros que están en 'Pendiente'
$pendientes = $conexion->query("SELECT v.id_via, u.nom_usu, r.nom_rut 
                                FROM viaje v 
                                INNER JOIN usuario u ON v.id_usu_via = u.id_usu 
                                INNER JOIN rutas r ON v.id_rut_via = r.id_rut 
                                WHERE v.est_via = 'Pendiente'");

// Traer conductores (Rol 2)
$conductores = $conexion->query("SELECT id_usu, nom_usu FROM usuario WHERE id_rol_usu = 2");

// Traer vehículos (CORREGIDO: Solo id y placa para evitar error 'Unknown column')
$vehiculos = $conexion->query("SELECT id_veh, pla_veh FROM vehiculo");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SGET - Crear Asignación</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

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
    <main class="flex-1 ml-64 p-8">
        <div class="max-w-2xl mx-auto">
            <div class="mb-10 text-center">
                <h1 class="text-3xl font-black text-gray-800 tracking-tight uppercase">Nueva Asignación</h1>
                <p class="text-gray-500 italic">Una al pasajero con un conductor y vehículo para iniciar el viaje, ala.</p>
            </div>

            <div class="bg-white p-10 rounded-[2.5rem] shadow-xl shadow-gray-200/50 border border-gray-100">
                <form action="procesar_asignacion.php" method="POST" class="space-y-6">
                    
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-1">Solicitudes Pendientes</label>
                        <select name="id_viaje" class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:ring-2 focus:ring-green-500 outline-none text-sm font-bold text-gray-700" required>
                            <option value="">-- ¿Quién está esperando viaje? --</option>
                            <?php if($pendientes->num_rows > 0): ?>
                                <?php while($p = $pendientes->fetch_assoc()): ?>
                                    <option value="<?php echo $p['id_via']; ?>">
                                        <?php echo htmlspecialchars($p['nom_usu']) . " → " . htmlspecialchars($p['nom_rut']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <option disabled>No hay pasajeros pendientes hoy, parce.</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-1">Conductor Asignado</label>
                            <select name="id_conductor" class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl outline-none text-sm font-bold" required>
                                <option value="">-- Seleccione --</option>
                                <?php while($c = $conductores->fetch_assoc()): ?>
                                    <option value="<?php echo $c['id_usu']; ?>"><?php echo htmlspecialchars($c['nom_usu']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-1">Vehículo (Placa)</label>
                            <select name="id_vehiculo" class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl outline-none text-sm font-bold" required>
                                <option value="">-- Seleccione placa --</option>
                                <?php while($v = $vehiculos->fetch_assoc()): ?>
                                    <option value="<?php echo $v['id_veh']; ?>"><?php echo htmlspecialchars($v['pla_veh']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-gray-900 text-white font-black py-5 rounded-[1.5rem] hover:bg-green-600 transition-all shadow-lg shadow-gray-200 uppercase tracking-widest text-xs mt-4">
                        <i class="fas fa-check-circle mr-2"></i> Confirmar y Empezar Viaje
                    </button>
                </form>
            </div>
        </div>
    </main>

</body>
</html>