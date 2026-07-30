<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php'; 

// 1. Validar que el usuario sea un pasajero autenticado (rol 3)
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 3) {
    header("Location: ../index.php");
    exit();
}

// 2. Verificar que la petición sea por el método POST y vengan los datos necesarios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_via = $_POST['id_via'] ?? null;
    $puestos_solicitados = intval($_POST['puestos'] ?? 0);
    $id_usuario = $_SESSION['id_usu'] ?? null;

    if (!$id_via || $puestos_solicitados <= 0 || !$id_usuario) {
        $_SESSION['error'] = "Los datos de la reserva no son válidos.";
        header("Location: viajes_pasajero.php");
        exit();
    }

    // 3. Validar disponibilidad real del viaje y capacidad del vehículo antes de insertar
    $sql_verificar = "SELECT v.*, r.nom_rut, u.nom_usu as conductor, ve.cap_veh, 
                             (SELECT COUNT(*) FROM reserva WHERE id_via_res = v.id_via) as ocupados
                      FROM viaje v
                      LEFT JOIN rutas r ON v.id_rut_via = r.id_rut
                      LEFT JOIN usuario u ON v.id_usu_via = u.id_usu
                      LEFT JOIN vehiculo ve ON v.id_veh = ve.id_veh
                      WHERE v.id_via = ? AND v.est_via = 'Activo'";
                      
    $stmt = $conexion->prepare($sql_verificar);
    $stmt->bind_param("i", $id_via);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        $_SESSION['error'] = "El viaje seleccionado no existe o ya no se encuentra activo.";
        header("Location: viajes_pasajero.php");
        exit();
    }

    $viaje = $resultado->fetch_assoc();
    $cupos_totales = intval($viaje['cap_veh']);
    $ocupados = intval($viaje['ocupados']);
    $disponibles = $cupos_totales - $ocupados;

    if ($puestos_solicitados > $disponibles) {
        $_SESSION['error'] = "Lo sentimos, no hay suficientes puestos disponibles. Solo quedan $disponibles cupos.";
        header("Location: viajes_pasajero.php");
        exit();
    }

    // 4. Registrar la reserva en la base de datos (con método de pago presencial y estado pendiente)
    $stmt_insert = $conexion->prepare("INSERT INTO reserva (id_via_res, id_usu_res, fech_res, metodo_pago, estado_pago) VALUES (?, ?, NOW(), 'Efectivo al Abordar', 'Pendiente')");
    
    $exito = true;
    for ($i = 0; $i < $puestos_solicitados; $i++) {
        $stmt_insert->bind_param("ii", $id_via, $id_usuario);
        if (!$stmt_insert->execute()) {
            $exito = false;
            break;
        }
    }

    if ($exito) {
        $nombre_pasajero = $_SESSION['nombre_usuario'] ?? "Pasajero";
        $nombre_ruta = $viaje['nom_rut'] ?? "Ruta General";
        $fecha_viaje = $viaje['fec_via'];
        $hora_viaje = date("h:i A", strtotime($viaje['hor_sal_via']));
        $valor_unitario = floatval($viaje['val_via']);
        $total_pagar = $valor_unitario * $puestos_solicitados;
        ?>
        <!DOCTYPE html>
        <html lang="es" class="dark">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Comprobante de Reserva - SGET</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
            <script>
                tailwind.config = { darkMode: 'class', theme: { extend: { colors: { 'bg-tarjeta': '#1e293b' } } } }
            </script>
        </head>
        <body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 flex items-center justify-center min-h-screen p-6">

            <div class="max-w-md w-full bg-white dark:bg-bg-tarjeta rounded-3xl border border-slate-200 dark:border-white/10 shadow-2xl p-8 space-y-6 relative overflow-hidden">
                
                <!-- Encabezado de Éxito -->
                <div class="text-center space-y-2">
                    <div class="w-16 h-16 bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 rounded-2xl flex items-center justify-center mx-auto text-3xl shadow-inner">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <span class="text-[10px] font-black bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 px-3 py-1 rounded-full uppercase tracking-widest">
                        ¡Reserva Exitosa!
                    </span>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white mt-2">Comprobante de Reserva</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Tu cupo ha sido apartado con éxito en el sistema SGET.</p>
                </div>

                <!-- Detalles del Ticket -->
                <div class="space-y-3 bg-slate-100 dark:bg-[#161e2e] p-5 rounded-2xl border border-slate-200 dark:border-white/5 text-xs font-mono">
                    <div class="flex justify-between border-b border-slate-200 dark:border-white/5 pb-2">
                        <span class="text-slate-400 font-sans font-bold">Pasajero:</span>
                        <span class="text-slate-900 dark:text-white font-bold"><?php echo htmlspecialchars($nombre_pasajero, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="flex justify-between border-b border-slate-200 dark:border-white/5 pb-2">
                        <span class="text-slate-400 font-sans font-bold">Ruta:</span>
                        <span class="text-slate-900 dark:text-white font-bold capitalize"><?php echo htmlspecialchars($nombre_ruta, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="flex justify-between border-b border-slate-200 dark:border-white/5 pb-2">
                        <span class="text-slate-400 font-sans font-bold">Fecha / Hora:</span>
                        <span class="text-slate-900 dark:text-white font-bold"><?php echo $fecha_viaje; ?> - <?php echo $hora_viaje; ?></span>
                    </div>
                    <div class="flex justify-between border-b border-slate-200 dark:border-white/5 pb-2">
                        <span class="text-slate-400 font-sans font-bold">Puestos reservados:</span>
                        <span class="text-blue-500 font-bold"><?php echo $puestos_solicitados; ?></span>
                    </div>
                    <div class="flex justify-between border-b border-slate-200 dark:border-white/5 pb-2">
                        <span class="text-slate-400 font-sans font-bold">Modalidad de Pago:</span>
                        <span class="text-amber-500 font-bold">Efectivo al Abordar</span>
                    </div>
                    <div class="flex justify-between pt-1 text-sm font-bold">
                        <span class="text-slate-400 font-sans uppercase text-[10px]">Total a Cancelar:</span>
                        <span class="text-emerald-500 font-mono text-base">$<?php echo number_format($total_pagar, 0, ',', '.'); ?></span>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="space-y-3 pt-2">
                    <button onclick="window.print()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3.5 rounded-xl text-xs font-black tracking-widest uppercase transition-all shadow-lg shadow-blue-600/20 flex items-center justify-center gap-2">
                        <i class="fas fa-file-pdf text-base"></i> Descargar / Imprimir Comprobante
                    </button>
                    <a href="viajes_pasajero.php" class="block text-center w-full py-3 rounded-xl text-xs font-black uppercase tracking-widest bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-700 transition-colors">
                        Regresar a Viajes
                    </a>
                </div>

            </div>
        </body>
        </html>
        <?php
        exit();
    } else {
        $_SESSION['error'] = "Ocurrió un error al procesar tu reserva. Inténtalo de nuevo.";
        header("Location: viajes_pasajero.php");
        exit();
    }

} else {
    header("Location: viajes_pasajero.php");
    exit();
}
?>