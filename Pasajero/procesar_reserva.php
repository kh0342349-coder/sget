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

    // 3. La reserva la crea services/ReservaService.php
// ANTES: esta página tenía su propio INSERT, con la cuenta de cupos calculada a
// mano y sin transacción. Dos pasajeros que reservaban a la vez podían pasar la
// misma comprobación y sobrevender el viaje. Además no se notificaba a nadie y
// `cup_dis` se quedaba desfasado.
try {
    $resultado = ReservaService::crear((int)$id_via, (int)$id_usuario, $puestos_solicitados, [
        'metodo'   => 'Efectivo al Abordar',
        'confirmar' => false,   // el pago se hace al embarcar: queda pendiente
    ]);
} catch (Throwable $e) {
    error_log('[SGET][procesar_reserva] ' . $e->getMessage());
    $resultado = ['ok' => false, 'ids' => [], 'puestos' => 0, 'mensaje' => 'No se pudo registrar la reserva.'];
}

if (!$resultado['ok']) {
    $_SESSION['error'] = $resultado['mensaje'];
    header("Location: viajes_pasajero.php");
    exit();
}

// Comprobante de la reserva
$id_nueva_reserva = (int)($resultado['ids'][0] ?? 0);

// Datos que se pintan en el comprobante (se leen del viaje ya reservado)
$viaje = Database::one(
    "SELECT v.fec_via, v.hor_sal_via, v.val_via, r.nom_rut
       FROM viaje v
       LEFT JOIN rutas r ON r.id_rut = v.id_rut_via
      WHERE v.id_via = ?",
    [(int)$id_via]
) ?: ['fec_via' => date('Y-m-d'), 'hor_sal_via' => '00:00:00', 'val_via' => 0, 'nom_rut' => 'Ruta'];

$nombre_ruta     = (string)($viaje['nom_rut'] ?: 'Ruta');
$fecha_viaje     = (string)$viaje['fec_via'];
$hora_viaje      = date('h:i A', strtotime((string)$viaje['hor_sal_via']));
$valor_unitario  = (float)$viaje['val_via'];
$total_pagar     = $valor_unitario * $puestos_solicitados;

{
    $nombre_pasajero = $_SESSION['nombre_usuario'] ?? 'Pasajero';
    $exito = true;
?>
<!DOCTYPE html>
<html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Comprobante de Reserva - SGET</title>
            <script src="https://cdn.tailwindcss.com"></script>
    <!-- SISTEMA VISUAL SGET (CSS modular): tema, componentes, modales y responsive -->
    <link rel="stylesheet" href="../assets/css/01-base.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/02-layout.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/03-componentes.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/04-modales.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/05-tablas.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/06-responsive.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/07-transiciones.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <script src="../assets/js/theme-init.js?v=<?= @filemtime('../assets/js/theme-init.js') ?: '1' ?>"></script>
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
}

} else {
    // Solo se acepta POST: una recarga suelta no debe crear reservas.
    header("Location: viajes_pasajero.php");
    exit();
}
?>