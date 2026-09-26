<?php
/**
 * Admin/rutas.php
 * -----------------------------------------------------------------------------
 * MÓDULO: GESTIÓN DE RUTAS  (Admin)
 * -----------------------------------------------------------------------------
 * REFACTOR 2026 · Arquitectura por capas
 *   core/       Config, Database(PDO), Fecha, Auth, Validator, Flash
 *   services/   RutaService  → reglas de negocio de rutas
 *   views/      modales y partials reutilizables
 *   assets/     CSS modular + motor único de modales
 *
 * CORRECCIONES APLICADAS
 *   - El INSERT/UPDATE ya no pierde ori_rut / des_rut / dis_rut (quedaban '').
 *   - La imagen se valida (tipo + tamaño) y se borra de la carpeta correcta.
 *   - Suspender y eliminar pasan por el API, con confirmación y auditoría.
 *   - El listado pasa a modo tarjeta en móvil (responsive real).
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirAdmin();
Auth::requerirAcceso('rutas');

/* -------------------------------------------------------------------------- */
/* Mensajes de retorno del API (PRG)                                          */
/* -------------------------------------------------------------------------- */
if (!empty($_GET['ok']))     Flash::exito((string)$_GET['ok']);
elseif (!empty($_GET['error'])) Flash::error((string)$_GET['error']);

// Cierre automático de viajes vencidos (mantenimiento transversal)
ViajeService::cerrarVencidos();

$rutas  = RutaService::todas();
$total  = count($rutas);
$activa = count(array_filter($rutas, fn($r) => (int)($r['estado'] ?? 1) === 1));

$tituloPagina = 'Gestión de Rutas';
include __DIR__ . '/../views/partials/head.php';
?>
<<<<<<< Updated upstream
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Gestión de Rutas</title>
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
    
    <?php include '../includes/sidebar.php'; ?>
=======
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
>>>>>>> Stashed changes

<div class="sget-shell">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="sget-main">
        <header class="sget-page-head">
            <div>
                <h1 class="sget-page-title"><i class="fas fa-route text-sky-500"></i> Gestión de Rutas</h1>
                <p class="sget-page-sub">
                    Registra cada trayecto con su <strong>ciudad de salida</strong>, <strong>ciudad de destino</strong>,
                    distancia, tarifa base y hora de salida por defecto.
                </p>
            </div>
            <div class="sget-page-actions">
                <button type="button" class="sget-btn sget-btn--primario" data-sget-modal="modalRuta">
                    <i class="fas fa-plus"></i> Nueva Ruta
                </button>
            </div>
        </header>

        <?= Flash::render() ?>

        <!-- KPIs -->
        <section class="sget-grid sget-grid--kpi">
            <div class="sget-card sget-kpi">
                <span class="sget-kpi__icono" style="background:color-mix(in srgb,var(--sget-azul) 12%,transparent);color:var(--sget-azul)"><i class="fas fa-route"></i></span>
                <div><p class="sget-label">Rutas registradas</p><p class="sget-kpi__valor"><?= $total ?></p></div>
            </div>
            <div class="sget-card sget-kpi">
                <span class="sget-kpi__icono" style="background:color-mix(in srgb,var(--sget-emerald) 12%,transparent);color:var(--sget-emerald)"><i class="fas fa-circle-check"></i></span>
                <div><p class="sget-label">Activas</p><p class="sget-kpi__valor"><?= $activa ?></p></div>
            </div>
            <div class="sget-card sget-kpi">
                <span class="sget-kpi__icono" style="background:color-mix(in srgb,var(--sget-ambars) 14%,transparent);color:var(--sget-ambars)"><i class="fas fa-pause"></i></span>
                <div><p class="sget-label">Suspendidas</p><p class="sget-kpi__valor"><?= $total - $activa ?></p></div>
            </div>
        </section>

        <!-- Buscador + filtros -->
        <div class="sget-toolbar">
            <div class="sget-search">
                <i class="fas fa-magnifying-glass"></i>
                <input type="search" id="buscarRuta" class="sget-input" placeholder="Buscar ruta, salida o destino… (Ctrl+K)">
            </div>
            <button type="button" class="sget-btn sget-btn--fantasma sget-btn--sm" data-sget-filtro="*">Todas</button>
            <button type="button" class="sget-btn sget-btn--fantasma sget-btn--sm" data-sget-filtro="1">Activas</button>
            <button type="button" class="sget-btn sget-btn--fantasma sget-btn--sm" data-sget-filtro="0">Suspendidas</button>
        </div>

        <!-- Listado -->
        <?php if ($total === 0): ?>
            <div class="sget-vacio">
                <span class="sget-vacio__icono"><i class="fas fa-route"></i></span>
                <h2 class="sget-label" style="font-size:.875rem">No hay rutas registradas</h2>
                <p class="sget-page-sub" style="margin:0">Crea la primera ruta indicando su ciudad de salida, su destino y la tarifa base.</p>
                <button type="button" class="sget-btn sget-btn--primario" style="margin-top:1rem" data-sget-modal="modalRuta">
                    <i class="fas fa-plus"></i> Crear la primera ruta
                </button>
            </div>
        <?php else: ?>
            <section class="sget-grid sget-grid--ancho">
                <?php foreach ($rutas as $r):
                    $id     = (int)$r['id_rut'];
                    $imgUrl = RutaService::urlImagen($r['img_rut'] ?? null);
                    $estado = (int)($r['estado'] ?? 1);
                    $hora   = Fecha::soloHora($r['hora_salida'] ?? '');
                    $datos  = [
                        'id_rut'      => $id,
                        'nom_rut'     => $r['nom_rut'],
                        'ori_rut'     => $r['ori_rut'],
                        'des_rut'     => $r['des_rut'],
                        'dis_rut'     => $r['dis_rut'],
                        'val_rut'     => $r['val_rut'],
                        'hora_salida' => $hora,
                        'img_actual'  => $r['img_rut'],
                        'estado'      => (string)$estado,
                        'titulo'      => 'Editar Ruta #' . $id,
                    ];
                ?>
                <article class="sget-card sget-card--interactiva sget-fila"
                         data-sget-fila data-estado="<?= $estado ?>" style="<?= $estado ? '' : 'opacity:.62;' ?>">

                    <header style="display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem">
                        <div style="min-width:0">
                            <p class="sget-label">
                                <i class="fas fa-location-arrow text-emerald-400"></i>
                                <span class="sget-linea-1"><?= htmlspecialchars((string)$r['ori_rut'], ENT_QUOTES, 'UTF-8') ?></span>
                            </p>
                            <h3 class="sget-page-title" style="font-size:1.0625rem;margin:.25rem 0">
                                <i class="fas fa-arrow-right" style="font-size:.7rem;color:var(--sget-texto-tenue)"></i>
                                <span class="sget-linea-1"><?= htmlspecialchars((string)$r['des_rut'], ENT_QUOTES, 'UTF-8') ?></span>
                            </h3>
                            <p class="sget-page-sub sget-linea-1" style="margin:0"><?= htmlspecialchars((string)$r['nom_rut'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <span class="sget-badge <?= $estado ? 'sget-badge--exito' : 'sget-badge--neutro' ?>">
                            <?= $estado ? 'Activa' : 'Suspendida' ?>
                        </span>
                    </header>

                    <div class="sget-form-3col" style="margin-top:1rem;gap:.5rem">
                        <div>
                            <p class="sget-label">Tarifa</p>
                            <p class="sget-mono" style="font-size:.875rem;font-weight:800">$<?= number_format((float)$r['val_rut'], 0, ',', '.') ?></p>
                        </div>
                        <div>
                            <p class="sget-label">Distancia</p>
                            <p class="sget-mono" style="font-size:.875rem"><?= (float)$r['dis_rut'] > 0 ? number_format((float)$r['dis_rut'], 1, ',', '.') . ' km' : '—' ?></p>
                        </div>
                        <div>
                            <p class="sget-label">Salida</p>
                            <p class="sget-mono" style="font-size:.875rem"><?= $hora !== '' ? $hora : '—' ?></p>
                        </div>
                    </div>

                    <?php if ($imgUrl): ?>
                        <img src="../<?= htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars((string)$r['nom_rut'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy"
                             style="margin-top:.875rem;width:100%;height:7rem;object-fit:cover;border-radius:var(--sget-radio-sm)">
                    <?php endif; ?>

                    <footer style="display:flex;gap:.5rem;margin-top:1rem;padding-top:.875rem;border-top:1px solid var(--sget-borde)">
                        <button type="button" class="sget-btn sget-btn--neutro sget-btn--sm" style="flex:1"
                                data-sget-modal="modalRuta"
                                data-sget-nuevo="Registrar Nueva Ruta"
                                data-sget-datos='<?= htmlspecialchars(json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                            <i class="fas fa-pen"></i> Editar
                        </button>

                        <button type="button" class="sget-icon-btn <?= $estado ? 'sget-icon-btn--editar' : 'sget-icon-btn--exito' ?>"
                                title="<?= $estado ? 'Suspender ruta' : 'Reactivar ruta' ?>"
                                aria-label="<?= $estado ? 'Suspender ruta' : 'Reactivar ruta' ?>"
                                data-sget-accion="alternar" data-sget-modulo="ruta"
                                data-sget-dato='<?= htmlspecialchars(json_encode(['id' => $id, 'estado' => $estado ? 0 : 1, 'accion' => 'cambiarEstado'], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                            <i class="fas <?= $estado ? 'fa-pause' : 'fa-play' ?>"></i>
                        </button>

                        <button type="button" class="sget-icon-btn sget-icon-btn--peligro"
                                title="Eliminar ruta" aria-label="Eliminar ruta"
                                data-sget-accion="eliminar" data-sget-modulo="ruta"
                                data-sget-dato='<?= htmlspecialchars(json_encode(['id' => $id], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'
                                data-sget-titulo="Eliminar ruta"
                                data-sget-texto='Se eliminará la ruta <strong><?= htmlspecialchars((string)$r['nom_rut'], ENT_QUOTES, 'UTF-8') ?></strong>. Solo es posible si no tiene viajes activos ni programados.'
                                data-sget-ok="Sí, eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </footer>
                </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</div>

<?php include __DIR__ . '/../views/modals/ruta.php'; ?>

<?php
$jsExtra = ['sget-page.js'];
include __DIR__ . '/../views/partials/foot.php';
