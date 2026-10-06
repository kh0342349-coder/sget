<?php
/**
 * Pasajero/modificar_reserva.php
 * -----------------------------------------------------------------------------
 * Aumentar o reducir puestos en una reserva existente.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirSesion();
Auth::requerirRol(Config::ROL_PASAJERO);

$idReserva = (int)($_GET['id_reserva'] ?? 0);
$idViaje   = (int)($_GET['id_via'] ?? 0);

if ($idReserva <= 0 || $idViaje <= 0) {
    Flash::error('Datos de reserva no válidos.');
    sget_redirigir(Config::basePath() . '/Pasajero/viajes_pasajero.php');
}

$reserva = ReservaService::porId($idReserva);
if (!$reserva || (int)$reserva['id_usu_res'] !== Auth::id()) {
    Flash::error('No tienes permiso para modificar esta reserva.');
    sget_redirigir(Config::basePath() . '/Pasajero/viajes_pasajero.php');
}

if ((string)$reserva['estado_pago'] === Config::RES_CANCELADA) {
    Flash::error('No se puede modificar una reserva cancelada.');
    sget_redirigir(Config::basePath() . '/Pasajero/viajes_pasajero.php');
}

$puestosActuales = (int)($reserva['cantidad_puestos'] ?? 1);
$disponibles = ReservaService::cuposDisponibles($idViaje);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevosPuestos = (int)($_POST['puestos'] ?? 1);
    $nuevosPuestos = max(1, $nuevosPuestos);

    $diferencia = $nuevosPuestos - $puestosActuales;
    if ($diferencia > $disponibles) {
        Flash::error("No hay suficientes cupos disponibles. Solo quedan {$disponibles}.");
        sget_redirigir(Config::basePath() . '/Pasajero/modificar_reserva.php?id_reserva=' . $idReserva . '&id_via=' . $idViaje);
    }

    // Actualizamos el registro existente con la nueva cantidad
    Database::query(
        "UPDATE reserva SET cantidad_puestos = ?, valor_pagado = ? WHERE id_res = ?",
        [$nuevosPuestos, (float)$reserva['val_via'] * $nuevosPuestos, $idReserva]
    );

    // Recalcular cupos
    ReservaService::recalcularCupos($idViaje);

    Flash::exito(sprintf('Reserva actualizada a %d puesto(s).', $nuevosPuestos));
    sget_redirigir(Config::basePath() . '/Pasajero/historial_pasajero.php');
}

$viaje = Database::one("SELECT v.*, r.nom_rut FROM viaje v LEFT JOIN rutas r ON r.id_rut = v.id_rut_via WHERE v.id_via = ?", [$idViaje]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Modificar Reserva - SGET</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] min-h-screen flex items-center justify-center p-6">
<div class="max-w-md w-full bg-white dark:bg-[#1e293b] rounded-3xl border border-slate-200 dark:border-white/10 shadow-2xl p-8 space-y-6">
    <div class="text-center space-y-2">
        <h1 class="text-xl font-black text-slate-900 dark:text-white">Modificar Puestos</h1>
        <p class="text-xs text-slate-500">Ruta: <span class="font-bold text-slate-800 dark:text-slate-200 capitalize"><?= htmlspecialchars($viaje['nom_rut'] ?? 'Ruta') ?></span></p>
        <p class="text-[10px] text-slate-400">Puestos actuales: <span class="font-mono font-bold text-blue-600"><?= $puestosActuales ?></span></p>
        <p class="text-[10px] text-emerald-600 font-bold">Disponibles: <?= $disponibles ?></p>
    </div>

    <form method="POST" class="space-y-4">
        <div>
            <label for="puestos" class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-2">Cantidad total de puestos</label>
            <input type="number" id="puestos" name="puestos" min="1" max="<?= $puestosActuales + $disponibles ?>" value="<?= $puestosActuales ?>" required
                class="w-full px-4 py-3 bg-slate-100 dark:bg-[#161e2e] border border-slate-200 dark:border-white/10 rounded-xl text-slate-900 dark:text-white font-mono font-bold focus:outline-none focus:border-blue-500 text-center text-xl">
        </div>
        <div>
            <p class="text-[11px] text-slate-500 leading-relaxed">
                El valor a pagar se recalculará según la tarifa del viaje ($<?= number_format((float)$reserva['val_via'], 0, ',', '.') ?> por puesto).
            </p>
        </div>
        <div class="flex gap-3">
            <a href="historial_pasajero.php" class="flex-1 py-3 text-center bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 rounded-xl font-bold text-xs uppercase tracking-wider hover:bg-slate-200 dark:hover:bg-white/10 transition-all">Cancelar</a>
            <button type="submit" class="flex-1 py-3 bg-gradient-to-r from-amber-500 to-amber-600 text-white rounded-xl font-black text-xs uppercase tracking-wider shadow-md shadow-amber-500/20 hover:opacity-95 transition-all">Guardar Cambios</button>
        </div>
    </form>
</div>
</body>
</html>
