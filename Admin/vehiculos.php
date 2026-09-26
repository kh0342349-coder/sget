<?php
/**
 * Admin/vehiculos.php
 * -----------------------------------------------------------------------------
 * MÓDULO: CONTROL DE FLOTA MÓVIL  (Admin)
 * -----------------------------------------------------------------------------
 * CORRECCIONES APLICADAS
 *   - `est_veh` tiene UNA sola definición (Config::VEH_*). Antes se mezclaban
 *     0/1, 1/2 y los textos 'Activo'/'Disponible' según el archivo.
 *   - Se eliminó `cambiar_estado_veh.php`, que no validaba sesión (cualquiera
 *     podía cambiar el estado de una unidad con un GET) y concatenaba el
 *     parámetro directamente en el SQL.
 *   - La tabla es responsive de verdad: en móvil cada fila se apila como tarjeta.
 *   - Suspender una unidad con viaje asignado se bloquea con un mensaje claro.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirAdmin();
Auth::requerirAcceso('vehiculos');

if (!empty($_GET['ok']))       Flash::exito((string)$_GET['ok']);
elseif (!empty($_GET['error'])) Flash::error((string)$_GET['error']);

ViajeService::cerrarVencidos();

$vehiculos = VehiculoService::todos();
$total     = count($vehiculos);
$disponibles = count(array_filter($vehiculos, fn($v) => (int)$v['est_veh'] === Config::VEH_DISPONIBLE));
$fuera      = $total - $disponibles;
$capacidad  = array_sum(array_map(fn($v) => (int)$v['cap_veh'], $vehiculos));

$tituloPagina = 'Control de Flota';
include __DIR__ . '/../views/partials/head.php';
?>
<<<<<<< Updated upstream
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Control de Vehículos</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style_admin.css">
    
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
=======
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="sget-shell">
    <?php include __DIR__ . '/../includes/header.php'; ?>
>>>>>>> Stashed changes

    <main class="sget-main">
        <header class="sget-page-head">
            <div>
                <h1 class="sget-page-title"><i class="fas fa-bus text-sky-500"></i> Control de Flota Móvil</h1>
                <p class="sget-page-sub">Unidades de transporte, capacidad de pasajeros y disponibilidad operativa.</p>
            </div>
            <div class="sget-page-actions">
                <button type="button" class="sget-btn sget-btn--primario" data-sget-modal="modalVehiculo" data-sget-nuevo="Registrar Vehículo">
                    <i class="fas fa-plus"></i> Agregar Vehículo
                </button>
            </div>
        </header>

        <?= Flash::render() ?>

        <section class="sget-grid sget-grid--kpi">
            <div class="sget-card sget-kpi">
                <span class="sget-kpi__icono" style="background:color-mix(in srgb,var(--sget-azul) 12%,transparent);color:var(--sget-azul)"><i class="fas fa-bus"></i></span>
                <div><p class="sget-label">Unidades</p><p class="sget-kpi__valor"><?= $total ?></p></div>
            </div>
            <div class="sget-card sget-kpi">
                <span class="sget-kpi__icono" style="background:color-mix(in srgb,var(--sget-emerald) 12%,transparent);color:var(--sget-emerald)"><i class="fas fa-circle-check"></i></span>
                <div><p class="sget-label">Disponibles</p><p class="sget-kpi__valor"><?= $disponibles ?></p></div>
            </div>
            <div class="sget-card sget-kpi">
                <span class="sget-kpi__icono" style="background:color-mix(in srgb,var(--sget-rojo) 12%,transparent);color:var(--sget-rojo)"><i class="fas fa-circle-xmark"></i></span>
                <div><p class="sget-label">Fuera de servicio</p><p class="sget-kpi__valor"><?= $fuera ?></p></div>
            </div>
            <div class="sget-card sget-kpi">
                <span class="sget-kpi__icono" style="background:color-mix(in srgb,var(--sget-ambars) 14%,transparent);color:var(--sget-ambars)"><i class="fas fa-layer-group"></i></span>
                <div><p class="sget-label">Puestos totales</p><p class="sget-kpi__valor"><?= $capacidad ?></p></div>
            </div>
        </section>

        <div class="sget-toolbar">
            <div class="sget-search">
                <i class="fas fa-magnifying-glass"></i>
                <input type="search" id="buscarVehiculo" class="sget-input" placeholder="Buscar placa o modelo… (Ctrl+K)">
            </div>
        </div>

        <?php if ($total === 0): ?>
            <div class="sget-vacio">
                <span class="sget-vacio__icono"><i class="fas fa-bus"></i></span>
                <h2 class="sget-label" style="font-size:.875rem">No hay vehículos registrados</h2>
                <p class="sget-page-sub" style="margin:0">Agrega la primera unidad para poder despachar viajes.</p>
                <button type="button" class="sget-btn sget-btn--primario" style="margin-top:1rem"
                        data-sget-modal="modalVehiculo" data-sget-nuevo="Registrar Vehículo">
                    <i class="fas fa-plus"></i> Agregar Vehículo
                </button>
            </div>
        <?php else: ?>
            <div class="sget-table-box">
                <div class="sget-table-wrap">
                    <table class="sget-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Placa</th>
                                <th>Línea / Modelo</th>
                                <th class="sget-centro">Capacidad</th>
                                <th class="sget-centro">Estado operativo</th>
                                <th class="sget-centro">Viajes</th>
                                <th class="acciones">Gestión</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vehiculos as $v):
                                $id     = (int)$v['id_veh'];
                                $estado = (int)$v['est_veh'];
                                $disp   = $estado === Config::VEH_DISPONIBLE;
                                $datos  = [
                                    'id_veh'  => $id,
                                    'pla_veh' => $v['pla_veh'],
                                    'mode_veh'=> $v['mode_veh'],
                                    'cap_veh' => (int)$v['cap_veh'],
                                    'est_veh' => $estado,
                                    'titulo'  => 'Editar Vehículo #' . $id,
                                ];
                            ?>
                            <tr data-sget-fila>
                                <td data-label="ID" class="sget-mono sget-suave">#<?= $id ?></td>
                                <td data-label="Placa">
                                    <span class="sget-badge sget-badge--info sget-mono"><?= htmlspecialchars((string)$v['pla_veh'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td data-label="Modelo"><span class="sget-linea-1"><?= htmlspecialchars((string)$v['mode_veh'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td data-label="Capacidad" class="sget-centro sget-mono"><?= (int)$v['cap_veh'] ?> puestos</td>
                                <td data-label="Estado" class="sget-centro">
                                    <span class="sget-badge <?= VehiculoService::claseEstado($estado) ?>">
                                        <span class="sget-punto"></span>
                                        <?= VehiculoService::etiquetaEstado($estado) ?>
                                    </span>
                                </td>
                                <td data-label="Viajes" class="sget-centro sget-mono sget-suave"><?= (int)($v['viajes_asignados'] ?? 0) ?></td>
                                <td class="acciones" data-label="Acciones">
                                    <button type="button" class="sget-icon-btn sget-icon-btn--editar" title="Editar" aria-label="Editar vehículo"
                                            data-sget-modal="modalVehiculo" data-sget-nuevo="Registrar Vehículo"
                                            data-sget-datos='<?= htmlspecialchars(json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                                        <i class="fas fa-pen"></i>
                                    </button>

                                    <button type="button" class="sget-icon-btn <?= $disp ? 'sget-icon-btn--peligro' : 'sget-icon-btn--exito' ?>"
                                            title="<?= $disp ? 'Poner fuera de servicio' : 'Marcar como disponible' ?>"
                                            aria-label="<?= $disp ? 'Poner fuera de servicio' : 'Marcar como disponible' ?>"
                                            data-sget-accion="alternar" data-sget-modulo="vehiculo"
                                            data-sget-dato='<?= htmlspecialchars(json_encode(['id' => $id], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                                        <i class="fas <?= $disp ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                    </button>

                                    <button type="button" class="sget-icon-btn sget-icon-btn--peligro" title="Eliminar" aria-label="Eliminar vehículo"
                                            data-sget-accion="eliminar" data-sget-modulo="vehiculo"
                                            data-sget-dato='<?= htmlspecialchars(json_encode(['id' => $id], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'
                                            data-sget-titulo="Eliminar vehículo"
                                            data-sget-texto='Se eliminará la unidad <strong><?= htmlspecialchars((string)$v['pla_veh'], ENT_QUOTES, 'UTF-8') ?></strong> del sistema. Solo es posible si no tiene viajes asignados.'
                                            data-sget-ok="Sí, eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="sget-sin-resultados" data-sget-sin-resultados hidden>Ningún vehículo coincide con la búsqueda.</p>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php include __DIR__ . '/../views/modals/vehiculo.php'; ?>

<script>
    window.__MOTIVOS_VIAJE__ = window.__MOTIVOS_VIAJE__ || [];
</script>
<?php
$jsExtra = ['sget-page.js'];
include __DIR__ . '/../views/partials/foot.php';
