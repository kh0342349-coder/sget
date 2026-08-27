<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php';

// 1. Seguridad
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

$mensaje = "";

// 2. Lógica para ELIMINAR (Devolver cupo)
if (isset($_GET['eliminar'])) {
    $id_res = intval($_GET['eliminar']);
    $conexion->begin_transaction();
    try {
        $stmtGet = $conexion->prepare("SELECT id_via_res FROM reserva WHERE id_res = ?");
        $stmtGet->bind_param("i", $id_res);
        $stmtGet->execute();
        $res = $stmtGet->get_result()->fetch_assoc();

        if ($res) {
            $id_via = $res['id_via_res'];
            $stmtDel = $conexion->prepare("DELETE FROM reserva WHERE id_res = ?");
            $stmtDel->bind_param("i", $id_res);
            $stmtDel->execute();
            
            $stmtUpd = $conexion->prepare("UPDATE viaje SET cup_dis = COALESCE(cup_dis, 0) + 1 WHERE id_via = ?");
            $stmtUpd->bind_param("i", $id_via);
            $stmtUpd->execute();
            
            $conexion->commit();
            $mensaje = "
            <div class='bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 p-4 rounded-xl shadow-lg flex items-center gap-3 backdrop-blur-md mb-6 animate-fade-in'>
                <i class='fas fa-minus-circle text-lg'></i>
                <span class='text-sm font-semibold'>Asignación removida. El cupo ha sido devuelto al viaje.</span>
            </div>";
        }
    } catch (Exception $e) {
        $conexion->rollback();
        $mensaje = "
        <div class='bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 p-4 rounded-xl shadow-lg flex items-center gap-3 backdrop-blur-md mb-6'>
            <i class='fas fa-times-circle text-lg'></i>
            <span class='text-sm font-semibold'>Error: " . htmlspecialchars($e->getMessage()) . "</span>
        </div>";
    }
}

// 3. Lógica para CREAR RESERVA Y GENERAR PDF
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['asignar'])) {
    $id_pasajero = intval($_POST['id_pasajero']);
    $id_viaje = intval($_POST['id_viaje']);
    $metodo_pago = htmlspecialchars($_POST['metodo_pago']);
    $valor_pagado = floatval($_POST['valor_pagado']);
    $estado_pago = htmlspecialchars($_POST['estado_pago']);

    $conexion->begin_transaction();
    try {
        $stmtCheck = $conexion->prepare("SELECT cup_dis, cup_tot FROM viaje WHERE id_via = ? FOR UPDATE");
        $stmtCheck->bind_param("i", $id_viaje);
        $stmtCheck->execute();
        $viaje = $stmtCheck->get_result()->fetch_assoc();

        if ($viaje) {
            // Manejo de cupos NULL si el viaje recién fue creado sin inicializar cupos
            $cupos_actuales = is_null($viaje['cup_dis']) ? (is_null($viaje['cup_tot']) ? 10 : intval($viaje['cup_tot'])) : intval($viaje['cup_dis']);

            if ($cupos_actuales > 0) {
                
                $checkColumnas = $conexion->query("SHOW COLUMNS FROM reserva LIKE 'metodo_pago'");
                
                if ($checkColumnas && $checkColumnas->num_rows > 0) {
                    $stmtIns = $conexion->prepare("INSERT INTO reserva (id_usu_res, id_via_res, metodo_pago, valor_pagado, estado_pago) VALUES (?, ?, ?, ?, ?)");
                    $stmtIns->bind_param("iisds", $id_pasajero, $id_viaje, $metodo_pago, $valor_pagado, $estado_pago);
                } else {
                    $stmtIns = $conexion->prepare("INSERT INTO reserva (id_usu_res, id_via_res) VALUES (?, ?)");
                    $stmtIns->bind_param("ii", $id_pasajero, $id_viaje);
                }
                
                $stmtIns->execute();
                $id_nueva_reserva = $conexion->insert_id;
                
                $nuevos_cupos = $cupos_actuales - 1;
                $stmtUpd = $conexion->prepare("UPDATE viaje SET cup_dis = ? WHERE id_via = ?");
                $stmtUpd->bind_param("ii", $nuevos_cupos, $id_viaje);
                $stmtUpd->execute();
                
                $conexion->commit();
                
                $script_pdf = "<script>window.open('imprimir_ticket.php?id={$id_nueva_reserva}', '_blank');</script>";

                $mensaje = "
                <div class='bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 p-4 rounded-xl shadow-lg flex items-center justify-between backdrop-blur-md mb-6 animate-fade-in'>
                    <div class='flex items-center gap-3'>
                        <i class='fas fa-check-circle text-lg'></i>
                        <span class='text-sm font-semibold'>¡Pasajero asignado con éxito! Abriendo Ticket PDF...</span>
                    </div>
                    <a href='imprimir_ticket.php?id={$id_nueva_reserva}' target='_blank' class='bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition-all flex items-center gap-2'>
                        <i class='fas fa-file-pdf'></i> Abrir Ticket PDF
                    </a>
                </div>" . $script_pdf;
            } else {
                $mensaje = "
                <div class='bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 p-4 rounded-xl shadow-lg flex items-center gap-3 backdrop-blur-md mb-6'>
                    <i class='fas fa-ban text-lg'></i>
                    <span class='text-sm font-semibold'>No hay cupos disponibles para este viaje.</span>
                </div>";
            }
        }
    } catch (Exception $e) {
        $conexion->rollback();
        $mensaje = "
        <div class='bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 p-4 rounded-xl shadow-lg flex items-center gap-3 backdrop-blur-md mb-6'>
            <i class='fas fa-bug text-lg'></i>
            <span class='text-sm font-semibold'>Error interno: " . htmlspecialchars($e->getMessage()) . "</span>
        </div>";
    }
}

// 4. CONSULTAS GENERALES
$pasajerosArr = [];
$resPasajeros = $conexion->query("SELECT id_usu, nom_usu FROM usuario WHERE id_rol_usu = 3 AND estado = 1 ORDER BY nom_usu ASC");
while($p = $resPasajeros->fetch_assoc()) { $pasajerosArr[] = $p; }

// CONSULTA SQL ESPECÍFICA: Solo trae viajes con est_via = 'Activo' y reemplaza NULLs en cupos/precios
$sqlViajes = "SELECT v.id_via, 
                     COALESCE(r.nom_rut, CONCAT('Ruta #', v.id_rut_via)) as nom_rut, 
                     v.hor_sal_via, 
                     COALESCE(v.cup_dis, v.cup_tot, 10) as cup_dis, 
                     COALESCE(v.val_via, r.val_rut, 0) as precio_ruta 
              FROM viaje v 
              LEFT JOIN rutas r ON v.id_rut_via = r.id_rut 
              WHERE v.est_via = 'Activo'
              ORDER BY v.id_via DESC";

$viajes = $conexion->query($sqlViajes);

$checkColumnas = $conexion->query("SHOW COLUMNS FROM reserva LIKE 'metodo_pago'");
if ($checkColumnas && $checkColumnas->num_rows > 0) {
    $sqlListado = "SELECT r.id_res, u.nom_usu, COALESCE(rt.nom_rut, 'Ruta General') as nom_rut, v.hor_sal_via, r.valor_pagado, r.estado_pago FROM reserva r
                   JOIN usuario u ON r.id_usu_res = u.id_usu
                   JOIN viaje v ON r.id_via_res = v.id_via
                   LEFT JOIN rutas rt ON v.id_rut_via = rt.id_rut
                   ORDER BY r.id_res DESC";
} else {
    $sqlListado = "SELECT r.id_res, u.nom_usu, COALESCE(rt.nom_rut, 'Ruta General') as nom_rut, v.hor_sal_via, 0 as valor_pagado, 'Pagado' as estado_pago FROM reserva r
                   JOIN usuario u ON r.id_usu_res = u.id_usu
                   JOIN viaje v ON r.id_via_res = v.id_via
                   LEFT JOIN rutas rt ON v.id_rut_via = rt.id_rut
                   ORDER BY r.id_res DESC";
}
$queryListado = $conexion->query($sqlListado);
?>

<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Asignaciones</title>
    
    <script>
        if (localStorage.getItem('theme') === 'light') {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }

        .custom-scrollbar::-webkit-scrollbar {
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.3);
            border-radius: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(56, 189, 248, 0.5);
        }
    </style>
</head>
<body class="bg-slate-100 dark:bg-[#0b0f19] flex min-h-screen antialiased text-slate-800 dark:text-slate-100 transition-colors duration-300">

    <!-- Sidebar lateral -->
    <?php include 'sidebar.php'; ?>

    <main class="flex-1 ml-64 flex flex-col min-h-screen min-w-0">
    
        <!-- Header superior -->
        <?php include 'header.php'; ?>
            
        <div class="p-8 max-w-[1600px] w-full mx-auto space-y-8 flex-grow min-w-0 overflow-x-hidden">
            
            <!-- CABECERA CON BOTÓN Y MODAL DESPLEGABLE DE AYUDA INTEGRADO -->
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">Asignación de Viajes y Recaudo</h1>
                    
                    <!-- BOTÓN CON TARJETA DE AYUDA FLOTANTE INTEGRADA -->
                    <div class="relative group">
                        <button type="button" class="w-6 h-6 rounded-full bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center text-xs font-bold shadow-xs cursor-pointer">
                            <i class="fas fa-question text-[10px]"></i>
                        </button>

                        <!-- MODAL DE AYUDA DEL MÓDULO -->
                        <div class="absolute left-0 top-full mt-2 w-80 bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-slate-700/80 rounded-2xl shadow-2xl p-4 text-xs opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-200 z-50">
                            <p class="font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-700/60 pb-2">
                                <i class="fas fa-info-circle text-blue-500"></i> ¿Qué hace este módulo?
                            </p>
                            <ul class="space-y-2 text-slate-600 dark:text-slate-300 leading-relaxed">
                                <li class="flex items-start gap-1.5">
                                    <i class="fas fa-bus text-emerald-500 mt-0.5 shrink-0"></i>
                                    <span><b>Procesar Abordaje:</b> Muestra los viajes que están en estado 'Activo' para asignar reservas.</span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <i class="fas fa-cash-register text-sky-500 mt-0.5 shrink-0"></i>
                                    <span><b>Recaudo & Caja:</b> Registra la forma de pago (Efectivo, Nequi, Daviplata, etc.).</span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <i class="fas fa-file-pdf text-red-500 mt-0.5 shrink-0"></i>
                                    <span><b>Ticket PDF:</b> Imprime de forma automática el boleto del pasajero.</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Registre la reserva, seleccione el método de pago e imprima el comprobante/ticket en PDF.</p>
            </div>

            <?php if (!empty($mensaje)) echo $mensaje; ?>

            <!-- SECCIÓN 1: SELECCIÓN DE VIAJE -->
            <section class="space-y-4">
                <h3 class="text-sm font-bold uppercase text-slate-500 dark:text-slate-400 flex items-center gap-2">
                    <i class="fas fa-bus text-blue-500"></i> 1. Seleccione un Viaje para Asignar Cupo
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <?php if(!$viajes || $viajes->num_rows == 0): ?>
                        <div class="col-span-full bg-white dark:bg-[#1e293b]/40 p-8 rounded-2xl border border-dashed border-slate-300 dark:border-white/10 text-center text-slate-500 dark:text-slate-400">No hay viajes en estado 'Activo' disponibles.</div>
                    <?php else: while($v = $viajes->fetch_assoc()): ?>
                        <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/5 rounded-2xl p-5 shadow-sm flex flex-col justify-between group transition-colors duration-300">
                            <div class="space-y-3">
                                <div class="flex justify-between items-start">
                                    <span class="text-[10px] bg-slate-100 dark:bg-white/5 px-2 py-1 rounded text-slate-500 dark:text-slate-400">ID: #<?= $v['id_via'] ?></span>
                                    <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"><?= $v['cup_dis'] ?> Cupos</span>
                                </div>
                                <div>
                                    <h4 class="font-bold text-slate-900 dark:text-white text-sm"><?= htmlspecialchars($v['nom_rut']) ?></h4>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1"><i class="far fa-calendar-alt mr-1"></i> <?= $v['hor_sal_via'] ?></p>
                                    <p class="text-xs font-bold text-blue-600 dark:text-blue-400 mt-0.5">Tarifa Oficial: $<?= number_format($v['precio_ruta'], 0) ?></p>
                                </div>
                            </div>
                            <button onclick="abrirModalAsignar(<?= $v['id_via'] ?>, '<?= htmlspecialchars($v['nom_rut']) ?>', <?= $v['precio_ruta'] ?>)" class="w-full mt-5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold py-2.5 rounded-xl transition-all uppercase tracking-wider">Procesar Abordaje</button>
                        </div>
                    <?php endwhile; endif; ?>
                </div>
            </section>

            <!-- SECCIÓN 2: HISTORIAL Y RECAUDOS CON SCROLLBAR HORIZONTAL -->
            <section class="space-y-4">
                <h3 class="text-sm font-bold uppercase text-slate-500 dark:text-slate-400 flex items-center gap-2">
                    <i class="fas fa-list-ul text-purple-500"></i> 2. Registro de Reservas Guardadas
                </h3>
                <div class="bg-white dark:bg-[#1e293b] rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm overflow-hidden transition-colors duration-300">
                    <div class="overflow-x-auto custom-scrollbar w-full">
                        <table class="w-full text-left border-collapse whitespace-nowrap min-w-[700px]">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-white/[0.02] border-b border-slate-200 dark:border-white/5">
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase">Pasajero</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase">Destino</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase">Monto Colectado</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase">Estado Pago</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-white/5 text-sm">
                                <?php if(!$queryListado || $queryListado->num_rows == 0): ?>
                                    <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">Ningún pasaje registrado en el sistema.</td></tr>
                                <?php else: while($row = $queryListado->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($row['nom_usu']) ?></td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300"><?= htmlspecialchars($row['nom_rut']) ?></td>
                                    <td class="px-6 py-4 font-mono font-bold text-sky-600 dark:text-sky-400">$<?= number_format($row['valor_pagado'], 2) ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                            <?= $row['estado_pago'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center flex items-center justify-center gap-3">
                                        <a href="imprimir_ticket.php?id=<?= $row['id_res'] ?>" target="_blank" class="text-blue-500 hover:text-blue-400 p-2 hover:bg-blue-500/10 rounded-lg transition-all" title="Descargar Ticket PDF">
                                            <i class="fas fa-file-pdf text-base"></i>
                                        </a>

                                        <a href="?eliminar=<?= $row['id_res'] ?>" onclick="return confirm('¿Anular esta reserva y devolver el cupo?')" class="text-red-500 hover:text-red-400 p-2 hover:bg-red-500/10 rounded-lg transition-all" title="Anular Reserva">
                                            <i class="fas fa-trash-alt text-base"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <footer class="p-6 text-center text-slate-400 text-xs font-semibold border-t border-slate-200 dark:border-white/10">
            &copy; <?php echo date('Y'); ?> Sistema de Gestión de Transporte SGET.
        </footer>
    </main>

    <!-- MODAL DE RECAUDO -->
    <div id="modalAsignar" class="fixed inset-0 bg-black/70 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
        <div class="bg-white dark:bg-[#1e293b] w-full max-w-md rounded-2xl border border-slate-200 dark:border-white/10 shadow-2xl transform scale-95 opacity-0 transition-all duration-300" id="modalCaja">
            <div class="p-6 border-b border-slate-200 dark:border-white/5 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-500 dark:text-blue-400 flex items-center justify-center"><i class="fas fa-cash-register text-xs"></i></span>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white text-sm">Caja & Asignación</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400" id="modalSubtitulo"></p>
                    </div>
                </div>
                <button onclick="cerrarModalAsignar()" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-xl font-bold">&times;</button>
            </div>

            <form action="asignaciones.php" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="id_viaje" id="modalIdViaje">

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Pasajero Titular</label>
                    <select name="id_pasajero" required class="w-full p-3 bg-slate-50 dark:bg-[#0b0f19] border border-slate-200 dark:border-white/10 rounded-xl outline-none text-sm text-slate-800 dark:text-white">
                        <option value="">-- Seleccionar --</option>
                        <?php foreach($pasajerosArr as $pas): ?>
                            <option value="<?= $pas['id_usu'] ?>"><?= htmlspecialchars($pas['nom_usu']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Valor del Pasaje ($ COP)</label>
                    <input type="number" name="valor_pagado" id="inputPrecio" readonly required class="w-full p-3 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl outline-none font-mono font-bold text-slate-700 dark:text-slate-300 text-sm cursor-not-allowed">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Método de Recaudo</label>
                    <select name="metodo_pago" required class="w-full p-3 bg-slate-50 dark:bg-[#0b0f19] border border-slate-200 dark:border-white/10 rounded-xl outline-none text-sm text-slate-800 dark:text-white">
                        <option value="Efectivo">Efectivo (Caja Principal)</option>
                        <option value="Nequi">Nequi</option>
                        <option value="Daviplata">Daviplata</option>
                        <option value="Transferencia Bancaria">Transferencia Bancaria</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Verificación de Pago</label>
                    <select name="estado_pago" required class="w-full p-3 bg-slate-50 dark:bg-[#0b0f19] border border-slate-200 dark:border-white/10 rounded-xl outline-none font-bold text-sm text-emerald-600 dark:text-emerald-400">
                        <option value="Pagado" class="text-emerald-600 dark:text-emerald-400">✓ Pago Confirmado e Ingresado</option>
                        <option value="Pendiente" class="text-red-500 dark:text-red-400">✗ Guardar sin Recibir Dinero</option>
                    </select>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-white/5">
                    <button type="button" onclick="cerrarModalAsignar()" class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-white/5">Cerrar</button>
                    <button type="submit" name="asignar" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-blue-600 hover:bg-blue-500 text-white shadow-lg uppercase tracking-wider flex items-center gap-2">
                        <i class="fas fa-file-pdf"></i> Generar Ticket PDF
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalAsignar(idViaje, destinoRuta, precioReal) {
            const modal = document.getElementById('modalAsignar');
            const caja = document.getElementById('modalCaja');
            
            document.getElementById('modalIdViaje').value = idViaje;
            document.getElementById('modalSubtitulo').innerText = `Configuración de Cobro para: ${destinoRuta}`;
            document.getElementById('inputPrecio').value = precioReal;
            
            modal.classList.remove('hidden');
            setTimeout(() => caja.classList.replace('opacity-0', 'opacity-100'), 50);
        }

        function cerrarModalAsignar() {
            document.getElementById('modalAsignar').classList.add('hidden');
        }
    </script>
</body>
</html>