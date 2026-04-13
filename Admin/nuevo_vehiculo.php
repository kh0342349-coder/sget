<?php
date_default_timezone_set('America/Bogota');
session_start();

include '../assets/conexion.php'; 

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = $_SESSION['nombre_usuario'] ?? "Administrador";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Vehículo - Sistema de Transporte</title>
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
                <li><a href="usuarios.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-users-cog"></i><span>Gestión Usuarios</span></a></li>
                <li><a href="asignaciones.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-clipboard-check"></i><span>Asignaciones</span></a></li>
                <li><a href="rutas.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-map-signs"></i><span>Rutas</span></a></li>
                <li><a href="vehiculos.php" class="flex items-center space-x-3 p-3 rounded-lg bg-blue-50 text-blue-600 transition shadow-sm"><i class="fas fa-car"></i><span>Vehículos</span></a></li>
                <li><a href="viajes.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-bus"></i><span>Viajes</span></a></li>
            </ul>
        </nav>
    </aside>

    <main class="flex-1 ml-64 flex flex-col">
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8 sticky top-0 z-10">
            <div class="text-gray-400 italic text-sm font-medium">Control de Flota</div>
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
                    <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-inner">
                        <i class="fas fa-car-side text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800">Registrar Nuevo Vehículo</h2>
                    <p class="text-gray-500 text-sm">Ingrese los datos técnicos para dar de alta la unidad.</p>
                </div>

                <form action="guardar_vehiculo.php" method="POST" class="space-y-6">
                    
                    <div class="border-b border-gray-100 pb-4">
                        <label class="block text-[10px] font-extrabold text-gray-400 uppercase mb-2 tracking-widest flex items-center gap-1.5"><i class="fas fa-id-card text-blue-500/70"></i>Identificación Única</label>
                        <div class="relative">
                            <input type="text" name="pla_veh" required placeholder="Ej: FUSA-123" maxlength="7"
                                   class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition uppercase font-bold text-lg text-gray-800 tracking-wider shadow-inner">
                            <div class="absolute inset-y-0 left-0 flex items-center px-4 pointer-events-none text-gray-400 border-r border-gray-200 mr-2 bg-gray-100 rounded-l-xl">
                                <i class="fas fa-hashtag text-xs"></i>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="col-span-1 md:col-span-2">
                            <label class="block text-[10px] font-extrabold text-gray-400 uppercase mb-1 tracking-widest flex items-center gap-1.5"><i class="fas fa-tag text-blue-500/70"></i>Modelo / Línea / Descripción</label>
                            <div class="relative">
                                <input type="text" name="mode_veh" required placeholder="Ej: Chevrolet Sail 2023"
                                       class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition text-gray-700">
                                <div class="absolute inset-y-0 left-0 flex items-center px-4 pointer-events-none text-gray-400">
                                    <i class="fas fa-car text-xs"></i>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-extrabold text-gray-400 uppercase mb-1 tracking-widest flex items-center gap-1.5"><i class="fas fa-users text-blue-500/70"></i>Capacidad Total</label>
                            <div class="relative">
                                <input type="number" name="cap_veh" required placeholder="0" min="1"
                                       class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition font-mono font-bold text-blue-600">
                                <div class="absolute inset-y-0 left-0 flex items-center px-4 pointer-events-none text-gray-400">
                                    <i class="fas fa-hashtag text-xs"></i>
                                </div>
                                <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-400 text-[10px] font-bold uppercase">pasajeros</div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-extrabold text-gray-400 uppercase mb-1 tracking-widest flex items-center gap-1.5"><i class="fas fa-info-circle text-blue-500/70"></i>Estado Inicial</label>
                            <div class="w-full px-4 py-3 bg-green-50 border border-green-100 rounded-xl font-bold text-green-700 text-sm flex items-center gap-2">
                                <i class="fas fa-check-circle"></i> ACTIVO (Por defecto)
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-4 pt-4 border-t border-gray-100">
                        <a href="vehiculos.php" class="flex-1 text-center py-3.5 bg-gray-100 text-gray-600 rounded-xl font-bold hover:bg-gray-200 transition">Cancelar</a>
                        <button type="submit" class="flex-1 py-3.5 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-100">
                            Guardar Vehículo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>