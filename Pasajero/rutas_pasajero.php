<?php
date_default_timezone_set('America/Bogota');   
session_start();
include '../assets/conexion.php'; 

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 3) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = $_SESSION['nombre_usuario'] ?? "Pasajero";

$sql = "SELECT * FROM rutas ORDER BY nom_rut ASC";
$res = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SGET - Elegir Ruta</title>
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
                        <i class="fas fa-th-large text-lg"></i><span>Inicio</span>
                    </a>
                </li>
                <li>
                    <a href="rutas_pasajero.php" class="flex items-center space-x-3 p-3 rounded-lg bg-green-50 text-green-600 shadow-sm transition">
                        <i class="fas fa-map-marked-alt text-lg"></i><span>Ver Rutas</span>
                    </a>
                </li>
                <li>
                    <a href="viajes_pasajero.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition">
                        <i class="fas fa-history text-lg"></i><span>Mis Viajes</span>
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
            <div class="text-gray-400 font-bold text-sm">Selección de Viaje</div>
            <div class="flex items-center space-x-4">
                <div class="text-right">
                    <p class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($nombreReal); ?></p>
                    <p class="text-[10px] text-green-500 font-bold uppercase italic tracking-widest">Pasajero</p>
                </div>
                <div class="w-10 h-10 bg-green-600 rounded-full flex items-center justify-center text-white border-2 border-white shadow-sm font-bold">
                    <?php echo strtoupper(substr($nombreReal, 0, 1)); ?>
                </div>
            </div>
        </header>

        <div class="p-8">
            <div class="mb-8">
                <h1 class="text-2xl font-black text-gray-800 tracking-tight">Rutas Disponibles</h1>
                <p class="text-gray-500 text-sm italic">Seleccione la ruta que desea tomar para ver los conductores asignados.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if ($res && $res->num_rows > 0): ?>
                    <?php while($ruta = $res->fetch_assoc()): ?>
                        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl transition-all p-6 group">
                            <div class="w-12 h-12 bg-green-50 text-green-600 rounded-2xl flex items-center justify-center text-xl mb-4 group-hover:bg-green-600 group-hover:text-white transition-colors">
                                <i class="fas fa-bus"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-800 mb-1"><?php echo $ruta['nom_rut']; ?></h3>
                            <p class="text-gray-400 text-[10px] mb-4 uppercase tracking-widest font-bold">Fusagasugá - Activo</p>
                            
                            <div class="flex items-center justify-between pt-4 border-t border-gray-50">
                                <span class="text-green-600 font-black text-lg">$<?php echo number_format($ruta['val_rut'] ?? 2500); ?></span>
                                <a href="confirmar_viaje.php?id_rut=<?php echo $ruta['id_rut']; ?>" 
                                   class="bg-gray-900 text-white px-4 py-2 rounded-xl text-[10px] font-black hover:bg-green-600 transition shadow-lg uppercase tracking-wider">
                                    ELEGIR <i class="fas fa-chevron-right ml-1"></i>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full bg-white p-12 rounded-3xl text-center border-2 border-dashed border-gray-200">
                        <i class="fas fa-route text-4xl text-gray-200 mb-4"></i>
                        <p class="text-gray-400 italic">No hay rutas registradas en el sistema todavía, ala.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>