<?php
date_default_timezone_set('America/Bogota');
session_start();

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 2) {
    header("Location: ../index.php");
    exit();
}

include '../assets/conexion.php'; 

$nombreReal = $_SESSION['nombre_usuario'] ?? "Conductor";
$documento  = $_SESSION['documento'];

// Lógica para finalizar viaje (sigue disponible si hay un viaje activo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalizar_viaje'])) {
    $id_viaje_fin = (int)$_POST['id_viaje'];
    
    $stmt_fin = $conexion->prepare("UPDATE viaje SET est_via = 'Finalizado' WHERE id_via = ?");
    $stmt_fin->bind_param("i", $id_viaje_fin);
    $stmt_fin->execute();
    $stmt_fin->close();

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$viajes_data = [];

// Obtener TODOS los viajes del conductor
$stmt_user = $conexion->prepare("SELECT id_usu FROM usuario WHERE num_doc_usu = ?");
$stmt_user->bind_param("s", $documento);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($result_user && $result_user->num_rows > 0) {
    $user_data = $result_user->fetch_assoc();
    $id_conductor = $user_data['id_usu'];

    // Consulta para obtener TODO el historial de viajes
    $sql_viajes = "SELECT v.*, r.des_rut, ve.pla_veh, ve.mode_veh,
                          (SELECT COUNT(*) FROM reserva WHERE id_via_res = v.id_via) AS num_pasajeros
                   FROM viaje v 
                   JOIN rutas r ON v.id_rut_via = r.id_rut 
                   LEFT JOIN vehiculo ve ON v.id_veh = ve.id_veh 
                   WHERE v.id_usu_via = ? 
                   ORDER BY v.fec_via DESC";

    $stmt_viajes = $conexion->prepare($sql_viajes);
    $stmt_viajes->bind_param("i", $id_conductor);
    $stmt_viajes->execute();
    $result_viajes = $stmt_viajes->get_result();

    while ($row = $result_viajes->fetch_assoc()) {
        $viajes_data[] = $row;
    }

    $stmt_viajes->close();
}
$stmt_user->close();
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Viajes - SGET</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'neon-azul': '#38bdf8',
                        'neon-morado': '#a855f7'
                    }
                }
            }
        }
    </script>
    <!-- SCRIPT ANTI-FLASHEO -->
    <script>
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 flex min-h-screen antialiased transition-colors duration-300">

    <!-- Carga Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Contenedor Principal (ml-64 para respetar sidebar fixed) -->
    <main class="flex-1 ml-64 flex flex-col min-h-screen">
        
       <!-- INCLUSIÓN DEL HEADER DEL CONDUCTOR -->
        <?php include 'header_conductor.php'; ?>

        <!-- Cuerpo principal -->
        <div class="p-8 space-y-6 flex-1">
            
            <div>
                <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Historial de Viajes</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Consulta el registro completo de todos tus viajes realizados y en proceso.</p>
            </div>
            
            <!-- Tabla -->
            <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/5 p-6 rounded-2xl shadow-xl max-w-6xl transition-colors duration-300">
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-white/5">
                    <table class="w-full text-sm text-left border-collapse">
                        <thead class="text-slate-500 dark:text-slate-400 uppercase text-[10px] font-black tracking-widest bg-slate-100/70 dark:bg-[#0b0f19]/50 border-b border-slate-200 dark:border-white/5">
                            <tr>
                                <th class="px-5 py-3.5">Destino / Ruta</th>
                                <th class="px-5 py-3.5">Fecha / Hora</th>
                                <th class="px-5 py-3.5">Vehículo</th>
                                <th class="px-5 py-3.5 text-center">Pasajeros</th>
                                <th class="px-5 py-3.5 text-center">Estado</th>
                                <th class="px-5 py-3.5 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-white/5 text-slate-700 dark:text-slate-200">
                            <?php if (!empty($viajes_data)): ?>
                                <?php foreach ($viajes_data as $v): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="px-5 py-4 font-bold text-slate-900 dark:text-white capitalize">
                                        <?php echo htmlspecialchars($v['des_rut'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td class="px-5 py-4 text-slate-500 dark:text-slate-400 text-xs font-mono">
                                        <?php 
                                            $fecha = !empty($v['fec_via']) ? date('d/m/Y - h:i A', strtotime($v['fec_via'])) : 'N/A';
                                            echo htmlspecialchars($fecha); 
                                        ?>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="bg-slate-100 dark:bg-white/5 text-blue-600 dark:text-neon-azul border border-slate-200 dark:border-white/10 px-2.5 py-0.5 rounded-md text-[10px] font-black tracking-wider uppercase inline-block w-fit">
                                                <?php echo htmlspecialchars($v['pla_veh'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            <span class="text-[10px] text-slate-400 dark:text-slate-500 font-medium px-0.5 capitalize">
                                                <?php echo htmlspecialchars($v['mode_veh'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-500/10 border border-emerald-500/20 rounded-md py-0.5 max-w-[50px] mx-auto text-xs">
                                            <?php echo (int)($v['num_pasajeros'] ?? 0); ?>
                                        </div>
                                    </td>

                                    <!-- ESTADO DEL VIAJE -->
                                    <td class="px-5 py-4 text-center">
                                        <?php 
                                            $estado = strtolower(trim($v['est_via'] ?? ''));
                                            if (in_array($estado, ['finalizado', 'terminado', 'completado', '0', '2'])): 
                                        ?>
                                            <span class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 font-extrabold text-[10px] uppercase px-2.5 py-1 rounded-lg">
                                                Finalizado
                                            </span>
                                        <?php elseif (in_array($estado, ['cancelado', '3'])): ?>
                                            <span class="bg-rose-500/10 text-rose-500 border border-rose-500/20 font-extrabold text-[10px] uppercase px-2.5 py-1 rounded-lg">
                                                Cancelado
                                            </span>
                                        <?php else: ?>
                                            <span class="bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 font-extrabold text-[10px] uppercase px-2.5 py-1 rounded-lg animate-pulse">
                                                En Curso / Activo
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- ACCIÓN DE FINALIZAR -->
                                    <td class="px-5 py-4 text-center">
                                        <?php if (!in_array(strtolower(trim($v['est_via'] ?? '')), ['finalizado', 'terminado', 'completado', 'cancelado', '0', '2', '3'])): ?>
                                            <form method="POST" action="" onsubmit="return confirm('¿Deseas dar por finalizado este viaje?');" class="inline-block">
                                                <input type="hidden" name="id_viaje" value="<?php echo $v['id_via']; ?>">
                                                <button type="submit" name="finalizar_viaje" class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 dark:text-rose-400 border border-rose-500/30 font-bold text-xs px-3 py-1.5 rounded-xl transition duration-150 flex items-center gap-1.5 mx-auto">
                                                    <i class="fas fa-flag-checkered text-xs"></i> Finalizar
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-slate-400 dark:text-slate-600 text-xs italic">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-slate-400 dark:text-slate-500 italic">
                                        <i class="fas fa-folder-open text-slate-400 mr-2"></i> No se encontraron registros en tu historial de viajes.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- JavaScript para Toggle de Modo Oscuro / Claro Sincronizado -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggleBtn = document.getElementById('theme-toggle');
            const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
            const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');

            function sincronizarInterfaz(esOscuro) {
                if (esOscuro) {
                    document.documentElement.classList.add('dark');
                    if (themeToggleLightIcon) themeToggleLightIcon.classList.remove('hidden');
                    if (themeToggleDarkIcon) themeToggleDarkIcon.classList.add('hidden');
                } else {
                    document.documentElement.classList.remove('dark');
                    if (themeToggleDarkIcon) themeToggleDarkIcon.classList.remove('hidden');
                    if (themeToggleLightIcon) themeToggleLightIcon.classList.add('hidden');
                }
            }

            function obtenerEstadoGuardado() {
                const v1 = localStorage.getItem('color-theme');
                const v2 = localStorage.getItem('theme');

                if (v1 === 'dark' || v2 === 'dark') return true;
                if (v1 === 'light' || v2 === 'light') return false;

                return window.matchMedia('(prefers-color-scheme: dark)').matches;
            }

            sincronizarInterfaz(obtenerEstadoGuardado());

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', function() {
                    const actualmenteOscuro = document.documentElement.classList.contains('dark');
                    const nuevoEstado = !actualmenteOscuro;

                    localStorage.setItem('color-theme', nuevoEstado ? 'dark' : 'light');
                    localStorage.setItem('theme', nuevoEstado ? 'dark' : 'light');

                    sincronizarInterfaz(nuevoEstado);
                });
            }

            window.addEventListener('storage', function(e) {
                if (e.key === 'color-theme' || e.key === 'theme') {
                    sincronizarInterfaz(e.newValue === 'dark');
                }
            });
        });
    </script>
</body>
</html>