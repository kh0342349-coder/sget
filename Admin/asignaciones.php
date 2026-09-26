<?php
// Archivo: Admin/asignaciones.php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php';
require_once '../helpers/AuthHelper.php';

// 1. Seguridad y Rol[cite: 3]
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

// BLOQUEO DE SEGURIDAD POR RESTRICCIONES[cite: 3]
$idUsuarioActual = $_SESSION['id_usu'] ?? 0;
AuthHelper::requerirAcceso($conexion, $idUsuarioActual, 'asignaciones');

$mensaje = "";

// 2. Lógica para CREAR RESERVA Y ASIGNACIÓN (Soporta pasajeros registrados y sin registro)[cite: 3]
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['asignar'])) {
    $id_viaje = intval($_POST['id_viaje']);
    $tipo_pasajero = $_POST['tipo_pasajero'] ?? 'registrado';
    $metodo_pago = htmlspecialchars($_POST['metodo_pago'] ?? 'Efectivo');
    $valor_pagado = floatval($_POST['valor_pagado'] ?? 0);
    $cantidad_puestos = intval($_POST['cantidad_puestos'] ?? 1);
    $estado_pago = 'Completado';

    if ($cantidad_puestos < 1) $cantidad_puestos = 1;

    $conexion->begin_transaction();
    try {
        // Si es un pasajero sin registro (ocasional), creamos un usuario temporal rápido con rol 3 (Pasajero)
        if ($tipo_pasajero === 'ocasional') {
            $nombre_ocasional = trim($_POST['nombre_ocasional'] ?? '');
            if (empty($nombre_ocasional)) {
                throw new Exception("El nombre del pasajero sin registro es obligatorio.");
            }
            $doc_ocasional = 'OCAS-' . time() . '-' . rand(100, 999);
            $email_ocasional = strtolower(str_replace(' ', '_', $nombre_ocasional)) . '_' . time() . '@sget.local';
            
            // Consulta corregida sin 'contra_usu'
            $stmtUser = $conexion->prepare("INSERT INTO usuario (num_doc_usu, tip_doc_usu, nom_usu, corre_usu, id_rol_usu, estado) VALUES (?, 'CC', ?, ?, 3, 1)");
            $stmtUser->bind_param("sss", $doc_ocasional, $nombre_ocasional, $email_ocasional);
            $stmtUser->execute();
            $id_pasajero = $conexion->insert_id;
        } else {
            $id_pasajero = intval($_POST['id_pasajero']);
            if ($id_pasajero <= 0) {
                throw new Exception("Debe seleccionar un pasajero válido.");
            }
        }

        // Verificar cupos del viaje
        $stmtCheck = $conexion->prepare("SELECT v.cup_dis, v.cup_tot, veh.cap_veh FROM viaje v LEFT JOIN vehiculo veh ON v.id_veh = veh.id_veh WHERE v.id_via = ? FOR UPDATE");
        $stmtCheck->bind_param("i", $id_viaje);
        $stmtCheck->execute();
        $viaje = $stmtCheck->get_result()->fetch_assoc();

        if ($viaje) {
            $cupos_actuales = is_null($viaje['cup_dis']) ? (is_null($viaje['cup_tot']) ? (is_null($viaje['cap_veh']) ? 10 : intval($viaje['cap_veh'])) : intval($viaje['cup_tot'])) : intval($viaje['cup_dis']);

            if ($cupos_actuales >= $cantidad_puestos) {
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
                
                $nuevos_cupos = $cupos_actuales - $cantidad_puestos;
                $stmtUpd = $conexion->prepare("UPDATE viaje SET cup_dis = ? WHERE id_via = ?");
                $stmtUpd->bind_param("ii", $nuevos_cupos, $id_viaje);
                $stmtUpd->execute();
                
                $conexion->commit();
                
                $script_pdf = "<script>window.open('imprimir_ticket.php?id={$id_nueva_reserva}', '_blank');</script>";

                $mensaje = "
                <div class='bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-2xl shadow-lg flex items-center justify-between mb-6'>
                    <div class='flex items-center gap-3'>
                        <i class='fas fa-check-circle text-lg'></i>
                        <span class='text-sm font-semibold'>¡Asignación de {$cantidad_puestos} puesto(s) registrada con éxito! Abriendo ticket...</span>
                    </div>
                    <a href='imprimir_ticket.php?id={$id_nueva_reserva}' target='_blank' class='bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition-all flex items-center gap-2'>
                        <i class='fas fa-file-pdf'></i> Ver Ticket
                    </a>
                </div>" . $script_pdf;
            } else {
                $conexion->rollback();
                $mensaje = "
                <div class='bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-2xl shadow-lg flex items-center gap-3 mb-6'>
                    <i class='fas fa-ban text-lg'></i>
                    <span class='text-sm font-semibold'>No hay suficientes cupos disponibles. Solo quedan {$cupos_actuales} puestos.</span>
                </div>";
            }
        }
    } catch (Exception $e) {
        $conexion->rollback();
        $mensaje = "
        <div class='bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-2xl shadow-lg flex items-center gap-3 mb-6'>
            <i class='fas fa-bug text-lg'></i>
            <span class='text-sm font-semibold'>Error interno: " . htmlspecialchars($e->getMessage()) . "</span>
        </div>";
    }
}

// 3. CONSULTAS GENERALES[cite: 3]
$pasajerosArr = [];
$resPasajeros = $conexion->query("SELECT id_usu, nom_usu FROM usuario WHERE id_rol_usu = 3 AND estado = 1 ORDER BY nom_usu ASC");
while($p = $resPasajeros->fetch_assoc()) { $pasajerosArr[] = $p; }

// Consulta de Viajes Disponibles incluyendo la imagen de la ruta (img_rut)
$sqlViajesDisponibles = "SELECT v.id_via, 
                                COALESCE(r.nom_rut, CONCAT('Ruta #', v.id_rut_via)) as nom_rut, 
                                r.img_rut,
                                v.fec_via,
                                v.hor_sal_via, 
                                COALESCE(v.cup_dis, v.cup_tot, veh.cap_veh, 10) as cup_dis, 
                                COALESCE(v.val_via, r.val_rut, 0) as precio_ruta,
                                u.nom_usu as nom_conductor,
                                veh.pla_veh
                         FROM viaje v 
                         LEFT JOIN rutas r ON v.id_rut_via = r.id_rut 
                         LEFT JOIN usuario u ON v.id_usu_via = u.id_usu 
                         LEFT JOIN vehiculo veh ON v.id_veh = veh.id_veh
                         WHERE v.est_via IN ('Programado', 'En curso')
                         ORDER BY v.id_via DESC";
$viajesDisponibles = $conexion->query($sqlViajesDisponibles);
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Asignaciones y Recaudo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- CSS MODULAR DEL PANEL (antes: style_admin.css, que no existia en esta carpeta) -->
    <link rel="stylesheet" href="../assets/css/01-base.css">
    <link rel="stylesheet" href="../assets/css/02-layout.css">
    <link rel="stylesheet" href="../assets/css/03-componentes.css">
    <link rel="stylesheet" href="../assets/css/04-modales.css">
    <link rel="stylesheet" href="../assets/css/05-tablas.css">
    <link rel="stylesheet" href="../assets/css/06-responsive.css">
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
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 min-h-screen antialiased">
        <?php include '../includes/sidebar.php'; ?>
    
    <div id="main-content-wrapper" class="ml-72 flex flex-col min-h-screen flex-1 transition-all duration-300 min-w-0">
        <?php include '../includes/header.php'; ?>
        
        <main class="space-y-8 flex-grow pb-12 relative z-10 p-8 max-w-[1600px] w-auto mx-auto w-full">
            
            <!-- ENCABEZADO Y BOTÓN DE AYUDA -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white/5 dark:bg-white/[0.02] p-6 rounded-3xl border border-slate-200 dark:border-white/5 backdrop-blur-md">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Gestión de Asignaciones y Pagos</h1>
                        
                        <!-- BOTÓN DE AYUDA DEL SISTEMA -->
                        <button type="button" onclick="abrirModalAyuda()" class="w-6 h-6 rounded-full bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center text-xs font-bold shadow-xs cursor-pointer" title="Ver guía del módulo">
                            <i class="fas fa-question text-[10px]"></i>
                        </button>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Selecciona directamente el viaje en tarjeta para agregar pasajeros registrados o sin registro, comprobar pagos y apartar puestos.</p>
                </div>
            </div>

            <?php if (!empty($mensaje)) echo $mensaje; ?>

            <!-- SECCIÓN DE VIAJES DISPONIBLES EN TARJETAS CON BOTÓN DE ASIGNACIÓN -->
            <div class="space-y-6">
                <div class="flex justify-between items-center bg-white dark:bg-[#121826] p-5 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl">
                    <h2 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fas fa-bus text-emerald-400"></i> Viajes Activos y Cupos en Ruta
                    </h2>
                    <span class="text-[10px] font-mono text-slate-400 uppercase tracking-wider bg-black/20 px-3 py-1 rounded-xl border border-white/5">Actualizado en tiempo real</span>
                </div>

                <?php if($viajesDisponibles && $viajesDisponibles->num_rows > 0): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <?php 
                        while($vd = $viajesDisponibles->fetch_assoc()): 
                            $nombreImagen = trim($vd['img_rut'] ?? '');
                            $rutaImagen = !empty($nombreImagen) ? "../img/rutas/" . $nombreImagen : "";
                            
                            $fechaSalida = !empty($vd['fec_via']) ? date('d/m/Y', strtotime($vd['fec_via'])) : 'Sin fecha';
                            $horaSalida = !empty($vd['hor_sal_via']) ? date('h:i A', strtotime($vd['hor_sal_via'])) : '';
                        ?>
                            <div class="relative overflow-hidden rounded-2xl h-64 border border-slate-200 dark:border-white/10 shadow-lg group transition-all duration-300 hover:shadow-2xl flex flex-col justify-between p-4 bg-slate-950">
                                
                                <?php if (!empty($nombreImagen) && file_exists("../img/rutas/" . $nombreImagen)): ?>
                                    <img src="<?php echo htmlspecialchars($rutaImagen); ?>" 
                                         alt="<?php echo htmlspecialchars($vd['nom_rut']); ?>" 
                                         class="absolute inset-0 w-full h-full object-cover object-center z-0 opacity-70 transition-transform duration-500 group-hover:scale-110">
                                <?php endif; ?>
                                
                                <div class="absolute inset-0 bg-gradient-to-t from-black/95 via-black/50 to-black/60 z-0"></div>

                                <div class="relative z-10 flex items-center justify-between mb-2">
                                    <span class="text-[10px] font-mono font-bold text-white/90 bg-black/60 px-2 py-0.5 rounded-md backdrop-blur-md border border-white/10">
                                        #<?php echo $vd['id_via']; ?>
                                    </span>
                                    <?php if(intval($vd['cup_dis']) > 0): ?>
                                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-emerald-300 bg-emerald-900/60 px-2.5 py-0.5 rounded-full border border-emerald-500/40 flex items-center gap-1 backdrop-blur-md">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block animate-pulse"></span> <?php echo $vd['cup_dis']; ?> cupos libres
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-red-300 bg-red-900/60 px-2.5 py-0.5 rounded-full border border-red-500/40 backdrop-blur-md">
                                            Agotado
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="relative z-10 space-y-0.5 my-auto">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-amber-300 drop-shadow-md">
                                        $<?php echo number_format($vd['precio_ruta'], 0, ',', '.'); ?> COP
                                    </span>
                                    <h3 class="font-black text-white text-base tracking-tight leading-tight truncate drop-shadow-lg" title="<?php echo htmlspecialchars($vd['nom_rut']); ?>">
                                        <?php echo htmlspecialchars($vd['nom_rut']); ?>
                                    </h3>
                                    <div class="flex items-center justify-between text-[10px] text-slate-300 pt-1">
                                        <span><i class="fas fa-user-tie mr-1 text-slate-400"></i> <?php echo htmlspecialchars($vd['nom_conductor'] ?? 'Sin asignar'); ?></span>
                                        <span class="font-mono text-sky-300"><i class="fas fa-bus mr-1"></i> <?php echo htmlspecialchars($vd['pla_veh'] ?? 'S/P'); ?></span>
                                    </div>
                                    <div class="text-[10px] text-slate-300 pt-0.5 font-medium flex items-center gap-1.5">
                                        <i class="fas fa-calendar-alt text-sky-400"></i> <span><?php echo $fechaSalida; ?> - <?php echo $horaSalida; ?></span>
                                    </div>
                                </div>

                                <div class="relative z-10 pt-3 border-t border-white/20">
                                    <?php if(intval($vd['cup_dis']) > 0): ?>
                                        <button type="button" 
                                                onclick="abrirModalAsignar(<?php echo $vd['id_via']; ?>, '<?php echo htmlspecialchars($vd['nom_rut'], ENT_QUOTES); ?>', <?php echo $vd['cup_dis']; ?>, <?php echo $vd['precio_ruta']; ?>)" 
                                                class="w-full py-2.5 bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-sky-500/20 transition-all flex items-center justify-center gap-2 cursor-pointer">
                                            <i class="fas fa-user-plus text-xs"></i> Agregar Pasajero
                                        </button>
                                    <?php else: ?>
                                        <button type="button" disabled class="w-full py-2.5 bg-slate-800 text-slate-500 font-bold text-xs uppercase tracking-wider rounded-xl cursor-not-allowed">
                                            Cupos Agotados
                                        </button>
                                    <?php endif; ?>
                                </div>

                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center p-12 bg-white dark:bg-[#121826] rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl text-center">
                        <div class="w-16 h-16 rounded-2xl bg-sky-500/10 text-sky-400 flex items-center justify-center text-2xl mb-4">
                            <i class="fas fa-bus"></i>
                        </div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-white">No hay viajes activos</h3>
                        <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Actualmente no existen trayectos disponibles para asignación.</p>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <!-- MODAL FLOTANTE: AGREGAR PASAJERO Y CONFIRMAR PAGO -->
    <div id="overlayAsignar" onclick="cerrarModalAsignar()" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-40 opacity-0 pointer-events-none transition-opacity duration-300"></div>
    <div id="modalAsignar" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#121826] w-full max-w-md rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-4 transform scale-95 transition-all duration-300">
            
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-3">
                <div>
                    <h3 class="font-extrabold text-slate-900 dark:text-white text-base flex items-center gap-2">
                        <i class="fas fa-ticket-alt text-sky-400"></i> Asignación de Pasajero
                    </h3>
                    <p id="modalRutaTitulo" class="text-[11px] text-sky-400 font-semibold mt-0.5 truncate max-w-[320px]"></p>
                </div>
                <button onclick="cerrarModalAsignar()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>

            <form action="asignaciones.php" method="POST" class="space-y-4">
                <input type="hidden" name="id_viaje" id="input_id_viaje" value="">

                <!-- Selector de Tipo de Pasajero -->
                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Tipo de Pasajero</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" id="btnTipoRegistrado" onclick="cambiarTipoPasajero('registrado')" class="py-2 px-3 rounded-xl text-xs font-bold uppercase transition-all bg-sky-500 text-slate-950 shadow-sm cursor-pointer">Registrado</button>
                        <button type="button" id="btnTipoOcasional" onclick="cambiarTipoPasajero('ocasional')" class="py-2 px-3 rounded-xl text-xs font-bold uppercase transition-all bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white cursor-pointer">Sin Registro (Ocasional)</button>
                    </div>
                    <input type="hidden" name="tipo_pasajero" id="input_tipo_pasajero" value="registrado">
                </div>

                <!-- Campo para Pasajero Registrado -->
                <div id="seccionRegistrado" class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Seleccionar de la Lista</label>
                    <select name="id_pasajero" id="select_id_pasajero" class="w-full px-4 py-3 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-2xl outline-none focus:border-sky-400 text-xs text-slate-800 dark:text-white">
                        <option value="">Seleccione pasajero registrado...</option>
                        <?php foreach($pasajerosArr as $p): ?>
                            <option value="<?php echo $p['id_usu']; ?>"><?php echo htmlspecialchars($p['nom_usu']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Campo para Pasajero Sin Registro -->
                <div id="seccionOcasional" class="space-y-1.5 hidden">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nombre del Pasajero Ocasional</label>
                    <input type="text" name="nombre_ocasional" id="input_nombre_ocasional" placeholder="Ej.: Juan Pérez (usuario ocasional)" data-i18n-placeholder-es="Ej.: Juan Pérez (usuario ocasional)" data-i18n-placeholder-en="e.g. John Smith (walk-in)" class="w-full px-4 py-3 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-2xl outline-none focus:border-sky-400 text-xs text-slate-800 dark:text-white">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Cantidad de Puestos</label>
                        <input type="number" name="cantidad_puestos" id="input_cantidad_puestos" value="1" min="1" required class="w-full px-4 py-3 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-2xl outline-none focus:border-sky-400 text-xs font-mono text-slate-800 dark:text-white" oninput="calcularTotal()">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Valor Total ($)</label>
                        <input type="number" name="valor_pagado" id="input_valor_pagado" step="0.01" required class="w-full px-4 py-3 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-2xl outline-none focus:border-sky-400 text-xs font-mono text-slate-800 dark:text-white">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Confirmación / Método de Pago</label>
                    <select name="metodo_pago" required class="w-full px-4 py-3 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-2xl outline-none focus:border-sky-400 text-xs text-slate-800 dark:text-white">
                        <option value="Efectivo">Efectivo (Confirmado en ventanilla)</option>
                        <option value="Transferencia">Transferencia Bancaria (Comprobado)</option>
                        <option value="Tarjeta">Tarjeta / POS (Aprobado)</option>
                    </select>
                </div>

                <div class="pt-2 flex gap-3">
                    <button type="button" onclick="cerrarModalAsignar()" class="flex-1 py-3 bg-slate-100 dark:bg-white/5 text-slate-300 rounded-xl text-xs font-bold uppercase tracking-wider cursor-pointer">Cancelar</button>
                    <button type="submit" name="asignar" class="flex-1 py-3 bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold rounded-xl text-xs uppercase tracking-wider shadow-lg shadow-sky-500/20 hover:opacity-90 transition-all cursor-pointer">
                        Confirmar y Generar
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- MODAL DE AYUDA DEL MÓDULO -->
    <div id="overlayAyuda" onclick="cerrarModalAyuda()" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 opacity-0 pointer-events-none transition-opacity duration-300"></div>
    <div id="modalAyuda" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#121826] w-full max-w-md rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-4 transform scale-95 transition-all duration-300">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-3">
                <h3 class="font-extrabold text-slate-900 dark:text-white text-base flex items-center gap-2">
                    <i class="fas fa-info-circle text-sky-400"></i> Guía de Asignaciones y Pagos
                </h3>
                <button onclick="cerrarModalAyuda()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer"><i class="fas fa-times text-xs"></i></button>
            </div>
            <ul class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                <li class="flex items-start gap-2">
                    <i class="fas fa-user-plus text-sky-400 mt-0.5"></i>
                    <span><b>Pasajeros Registrados u Ocasionales:</b> Puedes elegir entre seleccionar un usuario de la lista o ingresar el nombre de un pasajero sin registro.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-chair text-emerald-400 mt-0.5"></i>
                    <span><b>Puestos y Pago:</b> Indica la cantidad de asientos requeridos y el sistema calculará automáticamente el valor total.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-file-pdf text-purple-400 mt-0.5"></i>
                    <span><b>Ticket Automático:</b> Tras confirmar la transacción, se descontarán los cupos y se abrirá el PDF con el comprobante[cite: 3].</span>
                </li>
            </ul>
            <button onclick="cerrarModalAyuda()" class="w-full py-3 bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-800 dark:text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer mt-2">
                Entendido
            </button>
        </div>
    </div>

    <!-- SCRIPTS DE CONTROL -->
    <script>
    let precioUnitarioRuta = 0;

    function abrirModalAsignar(idViaje, nombreRuta, cuposDisponibles, precioRuta) {
        document.getElementById('input_id_viaje').value = idViaje;
        document.getElementById('modalRutaTitulo').innerText = 'Ruta: ' + nombreRuta + ' (Disponibles: ' + cuposDisponibles + ')';
        document.getElementById('input_cantidad_puestos').value = 1;
        document.getElementById('input_cantidad_puestos').max = cuposDisponibles;
        
        precioUnitarioRuta = precioRuta;
        document.getElementById('input_valor_pagado').value = precioRuta;

        cambiarTipoPasajero('registrado');

        document.getElementById('overlayAsignar').classList.remove('opacity-0', 'pointer-events-none');
        document.getElementById('overlayAsignar').classList.add('opacity-100', 'pointer-events-auto');
        document.getElementById('modalAsignar').classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
        document.getElementById('modalAsignar').classList.add('opacity-100', 'pointer-events-auto', 'scale-100');
    }

    function cerrarModalAsignar() {
        document.getElementById('modalAsignar').classList.remove('opacity-100', 'pointer-events-auto', 'scale-100');
        document.getElementById('modalAsignar').classList.add('opacity-0', 'pointer-events-none', 'scale-95');
        document.getElementById('overlayAsignar').classList.remove('opacity-100', 'pointer-events-auto');
        document.getElementById('overlayAsignar').classList.add('opacity-0', 'pointer-events-none');
    }

    function cambiarTipoPasajero(tipo) {
        document.getElementById('input_tipo_pasajero').value = tipo;
        const btnReg = document.getElementById('btnTipoRegistrado');
        const btnOca = document.getElementById('btnTipoOcasional');
        const secReg = document.getElementById('seccionRegistrado');
        const secOca = document.getElementById('seccionOcasional');
        const selReg = document.getElementById('select_id_pasajero');
        const inpOca = document.getElementById('input_nombre_ocasional');

        if (tipo === 'registrado') {
            btnReg.className = 'py-2 px-3 rounded-xl text-xs font-bold uppercase transition-all bg-sky-500 text-slate-950 shadow-sm cursor-pointer';
            btnOca.className = 'py-2 px-3 rounded-xl text-xs font-bold uppercase transition-all bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white cursor-pointer';
            secReg.classList.remove('hidden');
            secOca.classList.add('hidden');
            selReg.required = true;
            inpOca.required = false;
        } else {
            btnOca.className = 'py-2 px-3 rounded-xl text-xs font-bold uppercase transition-all bg-sky-500 text-slate-950 shadow-sm cursor-pointer';
            btnReg.className = 'py-2 px-3 rounded-xl text-xs font-bold uppercase transition-all bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white cursor-pointer';
            secOca.classList.remove('hidden');
            secReg.classList.add('hidden');
            inpOca.required = true;
            selReg.required = false;
        }
    }

    function calcularTotal() {
        const cant = parseInt(document.getElementById('input_cantidad_puestos').value) || 1;
        document.getElementById('input_valor_pagado').value = cant * precioUnitarioRuta;
    }

    function abrirModalAyuda() {
        document.getElementById('overlayAyuda').classList.remove('opacity-0', 'pointer-events-none');
        document.getElementById('overlayAyuda').classList.add('opacity-100', 'pointer-events-auto');
        document.getElementById('modalAyuda').classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
        document.getElementById('modalAyuda').classList.add('opacity-100', 'pointer-events-auto', 'scale-100');
    }

    function cerrarModalAyuda() {
        document.getElementById('modalAyuda').classList.remove('opacity-100', 'pointer-events-auto', 'scale-100');
        document.getElementById('modalAyuda').classList.add('opacity-0', 'pointer-events-none', 'scale-95');
        document.getElementById('overlayAyuda').classList.remove('opacity-100', 'pointer-events-auto');
        document.getElementById('overlayAyuda').classList.add('opacity-0', 'pointer-events-none');
    }
    </script>
</body>
</html>