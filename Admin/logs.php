<?php
/**
 * Admin/logs.php
 * -----------------------------------------------------------------------------
 * MÓDULO: LOGS DE AUDITORÍA  (Admin)
 * -----------------------------------------------------------------------------
 * ⚠ REIMPLEMENTACIÓN  ·  2026-09-26
 * El archivo original se perdió al resolver mal un `git stash pop` con
 * conflictos y nunca había estado versionado. Si conservas una copia local del
 * anterior, compárala antes de darla por buena.
 *
 * Toda la lógica de consulta está en services/LogService.php; esta página solo
 * traduce el resultado a HTML. Reglas:
 *   1. La página no contiene SQL.
 *   2. Los filtros se normalizan en el servicio, no aquí.
 *   3. La exportación sale por el mismo servicio (LogService::exportarCsv).
 *
 * Escribe: helpers/Logger.php · Lee: services/LogService.php
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirAdmin();
Auth::requerirAcceso('logs');

// La auditoría nunca se borra: solo se consulta, se filtra y se exporta.
if (empty($_SESSION['sget_csrf'])) $_SESSION['sget_csrf'] = bin2hex(random_bytes(32));
$token = $_SESSION['sget_csrf'];

/* -------------------------------------------------------------------------- */
/* 1) Exportación a CSV (termina la respuesta)                                 */
/* -------------------------------------------------------------------------- */
if (($_GET['exportar'] ?? '') === 'csv') {
    if (!Auth::validarToken($_GET['_token'] ?? null)) {
        Flash::error('La sesión expiró. Vuelve a cargar la página para exportar.');
        sget_redirigir('logs.php');
    }
    $n = LogService::exportarCsv(LogService::normalizarFiltros($_GET));
    exit;   // LogService ya envió las cabeceras y el CSV
}

/* -------------------------------------------------------------------------- */
/* 2) Filtros y consulta                                                       */
/* -------------------------------------------------------------------------- */
$filtros = LogService::normalizarFiltros($_GET);
$pagina  = max(1, (int)($_GET['pag'] ?? 1));

$resultado = LogService::listar($filtros, $pagina, 30);
$resumen   = LogService::resumen($filtros);
$acciones  = LogService::acciones();
$usuarios  = LogService::usuarios();
$porDia    = LogService::agruparPorDia($resultado['filas']);

$hayFiltros = $filtros['accion'] !== '' || $filtros['usuario'] > 0
           || $filtros['desde'] !== '' || $filtros['hasta'] !== '' || $filtros['q'] !== '';

/** URL que conserva los filtros (paginación, exportación, limpieza de filtros). */
$urlCon = static function (array $cambios = []) use ($filtros): string {
    $params = array_filter(array_merge([
        'accion'  => $filtros['accion'],
        'usuario' => $filtros['usuario'],
        'desde'   => $filtros['desde'],
        'hasta'   => $filtros['hasta'],
        'q'       => $filtros['q'],
    ], $cambios), static fn($v) => $v !== '' && $v !== 0);

    return 'logs.php' . ($params ? '?' . http_build_query($params) : '');
};

// Escala de la gráfica de actividad: se toma del RESUMEN (que trae dia + n),
// no del listado agrupado (que trae filas completas).
$maxDia = 1;
foreach ($resumen['por_dia'] as $d) {
    $maxDia = max($maxDia, (int)($d['n'] ?? 0));
}

$tituloPagina = 'Logs de Auditoría';
include __DIR__ . '/../views/partials/head.php';
?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="sget-shell">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="sget-main">

        <!-- ============================ ENCABEZADO ============================ -->
        <header class="sget-page-head">
            <div>
                <h1 class="sget-page-title"><i class="fas fa-file-alt text-sky-500"></i> Logs de Auditoría</h1>
                <p class="sget-page-sub">
                    Traza completa de las acciones sensibles del sistema. Los registros son
                    <strong>inmutables</strong>: no se pueden editar ni borrar desde la interfaz.
                </p>
            </div>
            <div class="sget-page-actions">
                <a class="sget-btn sget-btn--primario"
                   href="<?= htmlspecialchars($urlCon(['exportar' => 'csv', '_token' => $token]), ENT_QUOTES, 'UTF-8') ?>"
                   title="Descargar los registros filtrados en formato CSV">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                </a>
            </div>
        </header>

        <?= Flash::render() ?>

        <!-- ============================== KPIs ============================== -->
        <section class="sget-grid sget-grid--kpi">
            <?php
            $kpis = [
                ['fa-list',          'var(--sget-azul)',    'Eventos',       number_format((int)$resumen['total'], 0, ',', '.')],
                ['fa-calendar-day',  'var(--sget-morado)',  'Hoy',           number_format((int)$resumen['hoy'], 0, ',', '.')],
                ['fa-calendar-week', 'var(--sget-emerald)', 'Últimos 7 días', number_format((int)$resumen['semana'], 0, ',', '.')],
                ['fa-triangle-exclamation', 'var(--sget-ambars)', 'Críticos', number_format((int)$resumen['criticos'], 0, ',', '.')],
                ['fa-users',         'var(--sget-azul)',    'Usuarios',      number_format((int)$resumen['usuarios'], 0, ',', '.')],
            ];
            foreach ($kpis as [$icono, $color, $titulo, $valor]): ?>
                <div class="sget-card sget-kpi">
                    <span class="sget-kpi__icono" style="background:color-mix(in srgb,<?= $color ?> 12%,transparent);color:<?= $color ?>">
                        <i class="fas <?= $icono ?>"></i></span>
                    <div>
                        <p class="sget-label"><?= $titulo ?></p>
                        <p class="sget-kpi__valor"><?= $valor ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <!-- ====================== ACTIVIDAD POR DÍA ========================= -->
        <?php if ($resumen['por_dia']): ?>
        <section class="sget-card">
            <p class="sget-label" style="margin-bottom:.75rem">
                <i class="fas fa-chart-column"></i> Actividad de los últimos 14 días
            </p>
            <div style="display:flex;align-items:flex-end;gap:.375rem;height:5rem">
                <?php
                $diasMapa = [];
                foreach ($resumen['por_dia'] as $d) $diasMapa[$d['dia']] = (int)$d['n'];
                for ($i = 13; $i >= 0; $i--) {
                    $clave = date('Y-m-d', strtotime("-{$i} days"));
                    $n     = $diasMapa[$clave] ?? 0;
                    $alto  = $n > 0 ? max(8, (int)round(($n / $maxDia) * 100)) : 3; ?>
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:.25rem;height:100%;justify-content:flex-end"
                         title="<?= $clave ?>: <?= $n ?> evento(s)">
                        <span style="font-size:.5625rem;font-weight:800;color:var(--sget-texto-suave)"><?= $n ?: '' ?></span>
                        <div style="width:100%;height:<?= $alto ?>%;border-radius:.375rem .375rem .125rem .125rem;
                                    background:<?= $n > 0 ? 'linear-gradient(180deg,var(--sget-azul),var(--sget-morado))' : 'var(--sget-borde)' ?>"></div>
                        <span style="font-size:.5rem;color:var(--sget-texto-tenue);white-space:nowrap"><?= date('d/m', strtotime($clave)) ?></span>
                    </div>
                <?php } ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ============================ FILTROS ============================== -->
        <form method="GET" class="sget-toolbar" style="align-items:flex-end" data-sget-form-solo>
            <div class="sget-search" style="flex:2 1 14rem">
                <i class="fas fa-magnifying-glass"></i>
                <!--
                    Filtro de SERVIDOR: el texto que hay aquí describe las
                    filas que se están mostrando abajo. Por eso este campo está
                    exento de «empezar vacío»: borrarlo sin recargar dejaría la
                    tabla filtrada con el buscador en blanco, que es peor.

                    Aun así, `data-sget-valor-inicial` se fija solo si la URL
                    trae el filtro. Si el usuario vuelve con un enlace limpio
                    (`/Admin/logs.php`), la caja aparece vacía.
                -->
                <input type="search" name="q" id="buscarLog"
                       value="<?= htmlspecialchars($filtros['q'], ENT_QUOTES, 'UTF-8') ?>"
                       <?= $filtros['q'] !== '' ? 'data-sget-valor-inicial' : '' ?>
                       autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                       class="sget-input" placeholder="Buscar descripción, usuario, IP o navegador… (Ctrl+K)">
            </div>

            <div class="sget-field" style="flex:1 1 12rem">
                <label class="sget-label" for="f_accion">Acción</label>
                <select id="f_accion" name="accion" class="sget-select">
                    <option value="">Todas</option>
                    <?php foreach ($acciones as $a): ?>
                        <option value="<?= htmlspecialchars((string)$a['accion'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= $filtros['accion'] === $a['accion'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$a['accion'], ENT_QUOTES, 'UTF-8') ?> (<?= (int)$a['n'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sget-field" style="flex:1 1 12rem">
                <label class="sget-label" for="f_usuario">Usuario</label>
                <select id="f_usuario" name="usuario" class="sget-select">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= (int)$u['id_usu'] ?>" <?= $filtros['usuario'] === (int)$u['id_usu'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$u['nom_usu_log'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sget-field" style="flex:0 1 9rem">
                <label class="sget-label" for="f_desde">Desde</label>
                <input type="date" id="f_desde" name="desde" class="sget-input" autocomplete="off" spellcheck="false" value="<?= htmlspecialchars($filtros['desde'], ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="sget-field" style="flex:0 1 9rem">
                <label class="sget-label" for="f_hasta">Hasta</label>
                <input type="date" id="f_hasta" name="hasta" class="sget-input" autocomplete="off" spellcheck="false" value="<?= htmlspecialchars($filtros['hasta'], ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <button type="submit" class="sget-btn sget-btn--primario"><i class="fas fa-filter"></i> Filtrar</button>
            <?php if ($hayFiltros): ?>
                <a href="logs.php" class="sget-btn sget-btn--fantasma"><i class="fas fa-xmark"></i> Limpiar</a>
            <?php endif; ?>
        </form>

        <!-- Rangos rápidos -->
        <?php if ($hayFiltros): ?>
            <div class="sget-toolbar" style="padding:.625rem 1rem">
                <span class="sget-label">Rango activo</span>
                <?php if ($filtros['desde'] || $filtros['hasta']): ?>
                    <span class="sget-badge sget-badge--info">
                        <i class="fas fa-calendar"></i>
                        <?= $filtros['desde'] ? date('d/m/Y', strtotime($filtros['desde'])) : '…' ?>
                        →
                        <?= $filtros['hasta'] ? date('d/m/Y', strtotime($filtros['hasta'])) : '…' ?>
                    </span>
                <?php endif; ?>
                <?php if ($filtros['accion']): ?>
                    <span class="sget-badge <?= LogService::claseAccion($filtros['accion']) ?>"><?= $filtros['accion'] ?></span>
                <?php endif; ?>
                <?php if ($filtros['q']): ?>
                    <span class="sget-badge sget-badge--neutro">“<?= $filtros['q'] ?>”</span>
                <?php endif; ?>
                <span class="sget-help" style="margin-left:auto"><?= number_format((int)$resultado['total'], 0, ',', '.') ?> evento(s) coinciden</span>
            </div>
        <?php endif; ?>

        <!-- ============================ LISTADO ============================== -->
        <?php if (empty($resultado['filas'])): ?>
            <div class="sget-vacio">
                <span class="sget-vacio__icono"><i class="fas fa-file-circle-question"></i></span>
                <h2 class="sget-label" style="font-size:.875rem">No hay eventos que coincidan</h2>
                <p class="sget-page-sub" style="margin:0">
                    <?= $hayFiltros ? 'Prueba a ampliar el rango de fechas o a quitar algún filtro.' : 'La auditoría se alimentará en cuanto se registren acciones.' ?>
                </p>
                <?php if ($hayFiltros): ?>
                    <a href="logs.php" class="sget-btn sget-btn--primario" style="margin-top:1rem">
                        <i class="fas fa-xmark"></i> Quitar filtros
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>

            <?php foreach ($porDia as $dia => $eventos): ?>
                <section class="sget-table-box">
                    <header style="padding:.875rem 1.25rem;border-bottom:1px solid var(--sget-borde);
                                   display:flex;align-items:center;gap:.625rem;background:var(--sget-superficie-2)">
                        <i class="fas fa-calendar-day" style="font-size:.75rem;color:var(--sget-azul)"></i>
                        <h2 class="sget-label" style="font-size:.75rem"><?= LogService::etiquetaDia($dia) ?></h2>
                        <span class="sget-badge sget-badge--neutro" style="margin-left:auto">
                            <?= count($eventos) ?> evento(s)
                        </span>
                    </header>

                    <div class="sget-table-wrap">
                        <table class="sget-table sget-table--compacta">
                            <thead>
                                <tr>
                                    <th style="width:3.5rem">#</th>
                                    <th style="width:9.5rem">Hora</th>
                                    <th style="width:11rem">Acción</th>
                                    <th style="width:12rem">Usuario</th>
                                    <th>Descripción</th>
                                    <th style="width:8rem" class="sget-hide-mobile">IP</th>
                                    <th class="acciones" style="width:4rem"></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($eventos as $l): ?>
                                <tr data-sget-fila>
                                    <td data-label="ID" class="sget-mono sget-suave"><?= (int)$l['id_log'] ?></td>
                                    <td data-label="Hora" class="sget-mono sget-nowrap">
                                        <?= date('H:i:s', strtotime((string)$l['fec_log'])) ?>
                                    </td>
                                    <td data-label="Acción">
                                        <span class="sget-badge <?= LogService::claseAccion((string)$l['accion']) ?>">
                                            <i class="fas <?= LogService::iconoAccion((string)$l['accion']) ?>"></i>
                                            <?= htmlspecialchars((string)$l['accion'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td data-label="Usuario" class="sget-truncar">
                                        <?= htmlspecialchars((string)($l['nom_usu_log'] ?: 'Anónimo / Sistema'), ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($l['num_doc_usu'])): ?>
                                            <span class="sget-mono sget-suave" style="font-size:.6875rem">· <?= htmlspecialchars((string)$l['num_doc_usu'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Descripción">
                                        <span class="sget-linea-2"><?= htmlspecialchars((string)$l['descripcion'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td data-label="IP" class="sget-mono sget-suave sget-nowrap sget-hide-mobile">
                                        <?= htmlspecialchars((string)($l['ip_origen'] ?: '—'), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="acciones" data-label="">
                                        <button type="button" class="sget-icon-btn sget-icon-btn--ver"
                                                title="Ver detalle completo" aria-label="Ver detalle del evento"
                                                data-sget-modal="modalDetalleLog"
                                                data-sget-datos='<?= htmlspecialchars(json_encode([
                                                    'id'      => (int)$l['id_log'],
                                                    'fecha'   => Fecha::legible($l['fec_log']),
                                                    'accion'  => (string)$l['accion'],
                                                    'usuario' => (string)($l['nom_usu_log'] ?: 'Anónimo / Sistema'),
                                                    'doc'     => (string)($l['num_doc_usu'] ?? '—'),
                                                    'desc'    => (string)$l['descripcion'],
                                                    'ip'      => (string)($l['ip_origen'] ?: '—'),
                                                    'agente'  => (string)($l['user_agent'] ?: '—'),
                                                ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="sget-sin-resultados" data-sget-sin-resultados hidden>Ningún registro coincide con la búsqueda.</p>
                    </div>
                </section>
            <?php endforeach; ?>

            <!-- ========================== PAGINACIÓN =========================== -->
            <?php if ($resultado['paginas'] > 1): ?>
                <nav class="sget-toolbar" style="justify-content:center" aria-label="Paginación de la auditoría">
                    <a class="sget-btn sget-btn--fantasma sget-btn--sm"
                       href="<?= htmlspecialchars($urlCon(['pag' => $resultado['pagina'] - 1]), ENT_QUOTES, 'UTF-8') ?>"
                       <?= $resultado['pagina'] <= 1 ? 'aria-disabled="true" tabindex="-1" style="opacity:.4;pointer-events:none"' : '' ?>>
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                    <span class="sget-label" style="padding:0 .5rem">
                        Página <?= $resultado['pagina'] ?> de <?= $resultado['paginas'] ?>
                        · <?= number_format((int)$resultado['total'], 0, ',', '.') ?> evento(s)
                    </span>
                    <a class="sget-btn sget-btn--fantasma sget-btn--sm"
                       href="<?= htmlspecialchars($urlCon(['pag' => $resultado['pagina'] + 1]), ENT_QUOTES, 'UTF-8') ?>"
                       <?= $resultado['pagina'] >= $resultado['paginas'] ? 'aria-disabled="true" tabindex="-1" style="opacity:.4;pointer-events:none"' : '' ?>>
                        Siguiente <i class="fas fa-chevron-right"></i>
                    </a>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<!-- ====================== MODAL · DETALLE DEL EVENTO ======================= -->
<div class="sget-modal-wrap" id="modalDetalleLog" data-sget-capa data-titulo="Detalle del evento">
    <div class="sget-overlay"></div>
    <div class="sget-modal sget-modal--lg" role="dialog" aria-modal="true" aria-labelledby="tituloDetalleLog">
        <header class="sget-modal__head">
            <div>
                <h2 class="sget-modal__titulo" id="tituloDetalleLog">
                    <span class="sget-modal__icono"><i class="fas fa-file-lines"></i></span>
                    <span>Evento #<span data-sget-texto="id"></span></span>
                </h2>
                <p class="sget-modal__sub" data-sget-texto="fecha"></p>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll">
            <div class="sget-card" style="padding:1rem;background:var(--sget-superficie-2)">
                <p class="sget-label">Acción registrada</p>
                <p style="margin-top:.375rem">
                    <span class="sget-badge sget-badge--info" data-sget-texto="accion"></span>
                </p>
            </div>

            <div class="sget-form-2col" style="margin-top:1rem">
                <div>
                    <p class="sget-label">Usuario</p>
                    <p class="sget-truncar" data-sget-texto="usuario" style="margin-top:.25rem"></p>
                </div>
                <div>
                    <p class="sget-label">Documento</p>
                    <p class="sget-mono" data-sget-texto="doc" style="margin-top:.25rem"></p>
                </div>
            </div>

            <div class="sget-field" style="margin-top:1rem">
                <p class="sget-label">Descripción</p>
                <p style="margin-top:.375rem;font-size:.8125rem;line-height:1.6;white-space:pre-wrap"
                   data-sget-texto="desc"></p>
            </div>

            <div class="sget-form-2col" style="margin-top:1rem">
                <div>
                    <p class="sget-label">IP de origen</p>
                    <p class="sget-mono" data-sget-texto="ip" style="margin-top:.25rem"></p>
                </div>
                <div>
                    <p class="sget-label">Navegador / agente</p>
                    <p class="sget-help sget-linea-3" data-sget-texto="agente" style="margin-top:.25rem"></p>
                </div>
            </div>
        </div>

        <footer class="sget-modal__foot">
            <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Cerrar</button>
        </footer>
    </div>
</div>

<?php
$jsExtra = ['sget-page.js'];
include __DIR__ . '/../views/partials/foot.php';

if (isset($_GET['test']) && $_GET['test'] === 'modal' && is_file(__DIR__ . '/../pruebas/.habilitar')) { ?>
    <script>
    /* Sonda de autoprueba: solo se activa desde pruebas/modal-visual.js y
       únicamente si existe pruebas/.habilitar (ignorado por git). */
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () {
            var out = [], fallos = 0;
            function ok(c, t, d) { if (!c) fallos++; out.push((c ? 'OK   ' : 'FALLA') + ' | ' + t + (d ? ' | ' + d : '')); }

            var modal = document.getElementById('modalDetalleLog');
            var btn   = document.querySelector('[data-sget-modal="modalDetalleLog"]');
            ok(!!modal, 'existe el modal de detalle');
            ok(!!btn, 'hay boton para abrir el detalle');
            ok(document.querySelectorAll('[data-sget-fila]').length > 0, 'hay filas de log',
               document.querySelectorAll('[data-sget-fila]').length + ' filas');
            // innerText ignora el código de los <script>, así que no se detecta
            // a sí mismo (el propio literal 'Fatal error' está en este script).
            var pintado = document.body.innerText || '';
            ok(!pintado.includes('Fatal error'), 'sin error PHP visible');
            ok(!pintado.includes('Acceso Restringido'), 'no fue bloqueado por permisos');

            if (btn) {
                btn.click();
                setTimeout(function () {
                    var g = function (n) { var e = modal.querySelector('[data-sget-texto="' + n + '"]'); return e ? e.textContent.trim() : ''; };
                    ok(modal.dataset.abierto === '1', 'el modal se abre', 'abierto=' + modal.dataset.abierto);
                    ok(g('desc').length > 0,    'la descripcion se carga', '"' + g('desc').slice(0, 50) + '"');
                    ok(g('accion').length > 0,  'la accion se carga', g('accion'));
                    ok(g('ip').length > 0,      'la IP se carga', g('ip'));
                    ok(g('agente').length > 0,  'el user-agent se carga');
                    ok(g('id').length > 0,      'el id se carga');

                    var cerrar = modal.querySelector('[data-sget-cerrar]');
                    if (cerrar) cerrar.click();
                    setTimeout(function () {
                        ok(modal.dataset.abierto !== '1', 'el boton X cierra el modal');
                        var pre = document.createElement('pre');
                        pre.id = 'sgetProbe';
                        pre.textContent = 'LOGS_PROBE_START\n' + out.join('\n') + '\nFALLOS=' + fallos + '\nLOGS_PROBE_END';
                        document.body.appendChild(pre);
                    }, 350);
                }, 400);
            } else {
                var pre0 = document.createElement('pre');
                pre0.id = 'sgetProbe';
                pre0.textContent = 'LOGS_PROBE_START\n' + out.join('\n') + '\nFALLOS=' + fallos + '\nLOGS_PROBE_END';
                document.body.appendChild(pre0);
            }
        }, 500);
    });
    </script>
<?php } ?>
