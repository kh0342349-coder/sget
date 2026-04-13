<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php'; 

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = $_SESSION['nombre_usuario'] ?? "Administrador";

// Traemos las rutas incluyendo el campo de valor (val_rut)
$rutas = $conexion->query("SELECT id_rut, nom_rut, val_rut FROM rutas");

$conductores = $conexion->query("SELECT id_usu, nom_usu FROM usuario WHERE id_rol_usu = 2 AND est_con_usu = 0"); 
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programar Viaje - Sistema de Transporte</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-50 flex min-h-screen">

    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col fixed h-full shadow-sm">
        <div class="p-6">
            <h2 class="text-blue-600 font-bold text-lg uppercase tracking-wider leading-tight">Sistema de <br> Transporte</h2>
        </div>
        <nav class="mt-6 px-4 flex-grow">
            <ul class="space-y-2 text-sm font-medium">
                <li><a href="admin.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-chart-pie"></i><span>Dashboard</span></a></li>
                <li><a href="usuarios.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-users-cog"></i><span>Gestión Usuarios</span></a></li>
                <li><a href="asignaciones.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-clipboard-check"></i><span>Asignaciones</span></a></li>
                <li><a href="rutas.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-map-signs"></i><span>Rutas</span></a></li>
                <li><a href="viajes.php" class="flex items-center space-x-3 p-3 rounded-lg bg-blue-50 text-blue-600 transition shadow-sm"><i class="fas fa-bus"></i><span>Viajes</span></a></li>
                <li><a href="vehiculos.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-car"></i><span>Vehículos</span></a></li>
                <li><a href="reportes.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-file-invoice-dollar"></i><span>Reportes</span></a></li>
            </ul>
        </nav>
    </aside>

    <main class="flex-1 ml-64 flex flex-col">
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8 sticky top-0 z-10">
            <div class="text-gray-400 italic text-sm font-medium">Asignación de Servicios</div>
            <div class="flex items-center space-x-4">
                <p class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($nombreReal); ?></p>
                <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold border-2 border-white shadow-sm">
                    <?php echo strtoupper(substr($nombreReal, 0, 1)); ?>
                </div>
            </div>
        </header>

        <div class="p-8 flex justify-center">
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 w-full max-w-lg">
                <div class="mb-8 text-center">
                    <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-clock text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800">Programar Nuevo Viaje</h2>
                    <p class="text-gray-500 text-sm">Defina los detalles del servicio y la hora de salida.</p>
                </div>

                <form action="guardar_viaje.php" method="POST" class="space-y-5">
                    
                    <div>
                        <div class="flex justify-between items-end mb-1">
                            <label class="block text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">Seleccionar Ruta</label>
                            <a href="rutas.php" class="text-[10px] text-blue-600 font-bold hover:underline"><i class="fas fa-edit mr-1"></i>MODIFICAR RUTA</a>
                        </div>
                        <div class="relative">
                            <select name="id_rut_via" id="select_ruta" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition appearance-none">
                                <option value="" disabled selected>Escoja una ruta disponible...</option>
                                <?php while($r = $rutas->fetch_assoc()): ?>
                                    <option value="<?php echo $r['id_rut']; ?>" data-precio="<?php echo $r['val_rut']; ?>">
                                        <?php echo htmlspecialchars($r['nom_rut']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-400">
                                <i class="fas fa-map-marker-alt text-xs"></i>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-extrabold text-gray-400 uppercase mb-1 tracking-widest">Asignar Conductor</label>
                        <div class="relative">
                            <select name="id_usu_via" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition appearance-none">
                                <option value="" disabled selected>Seleccione un conductor disponible...</option>
                                <?php while($c = $conductores->fetch_assoc()): ?>
                                    <option value="<?php echo $c['id_usu']; ?>"><?php echo htmlspecialchars($c['nom_usu']); ?></option>
                                <?php endwhile; ?>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-400">
                                <i class="fas fa-user-tie text-xs"></i>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-extrabold text-gray-400 uppercase mb-1 tracking-widest">Fecha y Hora de Salida</label>
                        <div class="relative">
                            <input type="datetime-local" name="hor_sal_via" required 
                                   value="<?php echo date('Y-m-d\TH:i'); ?>"
                                   class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition text-gray-600">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-extrabold text-gray-400 uppercase mb-1 tracking-widest">Precio del Viaje (COP)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 font-bold">$</span>
                            <input type="number" name="val_via" id="input_precio" step="1" required placeholder="0" 
                                   class="w-full pl-8 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition font-mono font-bold text-blue-600">
                        </div>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <a href="viajes.php" class="flex-1 text-center py-3 bg-gray-100 text-gray-600 rounded-xl font-bold hover:bg-gray-200 transition">Cancelar</a>
                        <button type="submit" class="flex-1 py-3 bg-green-600 text-white rounded-xl font-bold hover:bg-green-700 transition shadow-lg shadow-green-100">
                            Confirmar Viaje
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        $(document).ready(function() {
            $('#select_ruta').on('change', function() {
                // Obtenemos el precio guardado en el atributo data-precio de la opción seleccionada
                var precio = $(this).find(':selected').data('precio');
                
                // Si existe un precio, lo ponemos en el input
                if (precio) {
                    $('#input_precio').val(precio);
                    // Pequeño efecto visual para avisar que cambió
                    $('#input_precio').addClass('bg-blue-50').delay(200).queue(function(next){
                        $(this).removeClass('bg-blue-50');
                        next();
                    });
                }
            });
        });
    </script>
</body>
</html>