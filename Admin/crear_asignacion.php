<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

$pendientes = $conexion->query("SELECT v.id_via, u.nom_usu, r.nom_rut 
                                FROM viaje v 
                                INNER JOIN usuario u ON v.id_usu_via = u.id_usu 
                                INNER JOIN rutas r ON v.id_rut_via = r.id_rut 
                                WHERE v.est_via = 'Pendiente'");

$conductores = $conexion->query("SELECT id_usu, nom_usu FROM usuario WHERE id_rol_usu = 2");
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
<body class="bg-gray-50">

    <?php include 'sidebar.php'; ?>
    
    <div class="flex">

        <main class="flex-1 ml-64 p-8 flex items-center justify-center min-h-screen">
            <div class="w-full max-w-2xl">
                <a href="asignaciones.php" class="inline-flex items-center text-gray-400 hover:text-blue-600 mb-6 transition">
                    <i class="fas fa-arrow-left mr-2"></i> Volver al historial
                </a>

                <div class="mb-8 text-center">
                    <h1 class="text-3xl font-black text-gray-800 tracking-tight uppercase">Nueva Asignación</h1>
                    <p class="text-gray-500 italic">Asigna un conductor y vehículo al viaje seleccionado.</p>
                </div>

                <div class="bg-white p-10 rounded-[2.5rem] shadow-xl shadow-gray-200/50 border border-gray-100">
                    <form action="procesar_asignacion.php" method="POST" class="space-y-6">
                        
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-1">Solicitudes Pendientes</label>
                            <select name="id_viaje" class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none text-sm font-bold text-gray-700" required>
                                <option value="">Seleccionar Pasajero/Ruta</option>
                                <?php while($p = $pendientes->fetch_assoc()): ?>
                                    <option value="<?php echo $p['id_via']; ?>">
                                        <?php echo htmlspecialchars($p['nom_usu']) . " → " . htmlspecialchars($p['nom_rut']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-1">Conductor</label>
                                <select name="id_conductor" class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl outline-none text-sm font-bold" required>
                                    <option value="">Seleccionar Conductor</option>
                                    <?php while($c = $conductores->fetch_assoc()): ?>
                                        <option value="<?php echo $c['id_usu']; ?>"><?php echo htmlspecialchars($c['nom_usu']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-1">Vehículo (Placa)</label>
                                <select name="id_vehiculo" class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl outline-none text-sm font-bold" required>
                                    <option value="">Seleccionar Vehículo</option>
                                    <?php while($v = $vehiculos->fetch_assoc()): ?>
                                        <option value="<?php echo $v['id_veh']; ?>"><?php echo htmlspecialchars($v['pla_veh']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-blue-600 text-white font-black py-5 rounded-[1.5rem] hover:bg-blue-700 transition-all shadow-lg shadow-blue-200 uppercase tracking-widest text-xs mt-4">
                            <i class="fas fa-save mr-2"></i> Guardar Asignación
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>