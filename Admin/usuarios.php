<?php
date_default_timezone_set('America/Bogota');   
session_start();
include '../assets/conexion.php'; 

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = isset($_SESSION['nombre_usuario']) ? $_SESSION['nombre_usuario'] : "Administrador";
$documento  = $_SESSION['documento'];

// Consulta mejorada
$query = "SELECT num_doc_usu, tip_doc_usu, nom_usu, corre_usu, id_rol_usu, estado FROM usuario";
$resultado = $conexion->query($query);

// Arrays para organizar la vista
$admins = []; $conductores = []; $pasajeros = []; $desactivados = []; 

while ($row = $resultado->fetch_assoc()) {
    if ($row['estado'] == 0) {
        $desactivados[] = $row;
    } else {
        if ($row['id_rol_usu'] == 1) $admins[] = $row;
        elseif ($row['id_rol_usu'] == 2) $conductores[] = $row;
        elseif ($row['id_rol_usu'] == 3) $pasajeros[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - SGET</title>
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
                <li><a href="usuarios.php" class="flex items-center space-x-3 p-3 rounded-lg bg-blue-50 text-blue-600 transition shadow-sm"><i class="fas fa-users-cog"></i><span>Usuarios</span></a></li>
                <li><a href="asignaciones.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-clipboard-check"></i><span>Asignaciones</span></a></li>
                <li><a href="rutas.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-map-signs"></i><span>Rutas</span></a></li>
                <li><a href="viajes.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-500 hover:bg-gray-100 transition"><i class="fas fa-bus"></i><span>Viajes</span></a></li>
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

    <main class="flex-1 ml-64 flex flex-col">
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8 sticky top-0 z-10">
            <div class="text-gray-400 italic text-sm font-medium">Gestión de Personal del Sistema</div>
            <div class="flex items-center space-x-4">
                <div class="text-right">
                    <p class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($nombreReal); ?></p>
                    <span class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-bold uppercase tracking-tighter">Administrador</span>
                </div>
                <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white border-2 border-white shadow-sm font-bold">
                    <?php echo strtoupper(substr($nombreReal, 0, 1)); ?>
                </div>
            </div>
        </header>

        <div class="p-8">
            <div class="mb-10 flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Administración de Usuarios</h1>
                    <p class="text-gray-500 text-sm mt-1">Filtre, agregue y gestione los roles activos en la plataforma.</p>
                </div>
                <?php if(isset($_GET['msg']) && $_GET['msg'] == 'actualizado'): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-3 rounded shadow-sm flex items-center gap-3 animate-bounce">
                    <i class="fas fa-check-circle"></i>
                    <span class="text-xs font-bold uppercase">¡Usuario actualizado con éxito!</span>
                </div>
                <?php endif; ?>
            </div>

            <?php 
            $secciones = [
                ['data' => $admins, 'titulo' => 'Administradores', 'label' => 'Admin', 'color' => 'red', 'id_rol' => 1, 'icon' => 'fa-user-shield'],
                ['data' => $conductores, 'titulo' => 'Conductores', 'label' => 'Conductor', 'color' => 'green', 'id_rol' => 2, 'icon' => 'fa-id-card'],
                ['data' => $pasajeros, 'titulo' => 'Pasajeros', 'label' => 'Pasajero', 'color' => 'blue', 'id_rol' => 3, 'icon' => 'fa-walking']
            ];

            foreach ($secciones as $s): 
                $c = $s['color'];
            ?>
            <section class="mb-12">
                <div class="flex justify-between items-end mb-4 px-2">
                    <h3 class="text-lg font-bold text-gray-700 flex items-center gap-2">
                        <span class="p-2 bg-white rounded-lg shadow-sm text-<?php echo $c; ?>-500"><i class="fas <?php echo $s['icon']; ?>"></i></span>
                        <?php echo $s['titulo']; ?>
                    </h3>
                    <a href="registro_usuario.php?rol=<?php echo $s['id_rol']; ?>" 
                       class="text-xs font-bold bg-<?php echo $c; ?>-600 hover:bg-<?php echo $c; ?>-700 text-white px-4 py-2 rounded-lg transition shadow-md flex items-center gap-2">
                        <i class="fas fa-plus"></i> AGREGAR <?php echo strtoupper($s['label']); ?>
                    </a>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50/50 border-b border-gray-100">
                                <th class="px-6 py-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">Documento</th>
                                <th class="px-6 py-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">Nombre Completo</th>
                                <th class="px-6 py-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">Correo Electrónico</th>
                                <th class="px-6 py-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if(empty($s['data'])): ?>
                                <tr><td colspan="4" class="px-6 py-10 text-center text-gray-400 italic text-sm">No hay registros activos...</td></tr>
                            <?php else: ?>
                                <?php foreach ($s['data'] as $u): ?>
                                <tr class="hover:bg-gray-50/80 transition group">
                                    <td class="px-6 py-4">
                                        <span class="text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded mr-1"><?php echo $u['tip_doc_usu']; ?></span>
                                        <span class="text-sm font-mono text-gray-600 font-semibold"><?php echo $u['num_doc_usu']; ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-7 h-7 bg-<?php echo $c; ?>-50 rounded-full flex items-center justify-center text-<?php echo $c; ?>-500 text-[10px] font-bold border border-<?php echo $c; ?>-100">
                                                <?php echo strtoupper(substr($u['nom_usu'], 0, 1)); ?>
                                            </div>
                                            <span class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($u['nom_usu']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 italic"><?php echo $u['corre_usu']; ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center gap-4">
                                            <button onclick="cambiarEstado('<?php echo $u['num_doc_usu']; ?>', 1)" 
                                                    class="flex items-center gap-2 px-3 py-1 rounded-full text-green-500 bg-green-50 hover:bg-green-100 transition shadow-sm" title="Desactivar">
                                                <i class="fas fa-toggle-on text-lg"></i>
                                                <span class="text-[10px] font-bold uppercase">Activo</span>
                                            </button>
                                            
                                            <div class="flex gap-2 border-l pl-4 border-gray-100">
                                                <a href="editar_usuario.php?doc=<?php echo $u['num_doc_usu']; ?>" 
                                                   class="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition" title="Editar datos">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endforeach; ?>

            <section class="mt-20 mb-12">
                <div class="flex items-end mb-4 px-2 border-b border-gray-200 pb-2">
                    <h3 class="text-lg font-bold text-gray-400 flex items-center gap-2">
                        <span class="p-2 bg-gray-100 rounded-lg text-gray-400"><i class="fas fa-user-slash"></i></span>
                        Cuentas Desactivadas
                    </h3>
                </div>
                <div class="bg-gray-100/50 rounded-2xl shadow-inner border border-dashed border-gray-200 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <tbody class="divide-y divide-gray-200">
                            <?php if(empty($desactivados)): ?>
                                <tr><td class="px-6 py-8 text-center text-gray-400 italic text-sm">No hay usuarios restringidos.</td></tr>
                            <?php else: ?>
                                <?php foreach ($desactivados as $u): ?>
                                <tr class="grayscale opacity-70 hover:grayscale-0 hover:opacity-100 transition group bg-white/50">
                                    <td class="px-6 py-4 w-1/4"><span class="text-sm font-mono text-gray-400 font-semibold"><?php echo $u['num_doc_usu']; ?></span></td>
                                    <td class="px-6 py-4 w-1/3">
                                        <span class="text-sm font-bold text-gray-500"><?php echo htmlspecialchars($u['nom_usu']); ?></span>
                                        <span class="block text-[9px] uppercase font-bold text-gray-400">Rol ID: <?php echo $u['id_rol_usu']; ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-400 italic"><?php echo $u['corre_usu']; ?></td>
                                    <td class="px-6 py-4 text-right">
                                        <button onclick="cambiarEstado('<?php echo $u['num_doc_usu']; ?>', 0)" 
                                                class="flex items-center gap-2 px-3 py-1 rounded-full transition-all bg-white border border-gray-200 text-gray-400 hover:text-blue-500 hover:border-blue-200 inline-flex">
                                            <i class="fas fa-toggle-off text-lg"></i>
                                            <span class="text-[10px] font-bold uppercase">Reactivar</span>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <script>
    function cambiarEstado(documento, estadoActual) {
        const nuevoEstado = estadoActual === 1 ? 0 : 1;
        const accion = nuevoEstado === 1 ? "REACTIVAR" : "DESACTIVAR";
        if (confirm(`¿Está seguro de que desea ${accion} al usuario con documento ${documento}?`)) {
            window.location.href = `actualizar_estado.php?doc=${documento}&nuevo_estado=${nuevoEstado}`;
        }
    }
    </script>
</body>
</html>