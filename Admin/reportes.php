<?php
/**
 * Admin/reportes.php
 * -----------------------------------------------------------------------------
 * MÓDULO: PANEL DE INFORMACIÓN  (Admin)
 * -----------------------------------------------------------------------------
 * QUÉ ES Y QUÉ YA NO ES
 *   Antes este módulo solo descargaba archivos CSV ("Exportar viajes / Exportar
 *   reservas"). No ayudaba a responder preguntas del día a día: ¿cómo viene la
 *   operación?, ¿qué pasó con ese viaje?, ¿quién es este usuario?, ¿qué ruta
 *   deja más plata?, ¿cuánto ganamos este mes?
 *
 *   AHORA es un tablero de consulta y consulta únicamente: no exporta nada.
 *   Todo el SQL vive en services/InformacionService.php; esta página solo
 *   dibuja. Estructura:
 *
 *     ?tab=general     Fotografía del sistema: personas, flota, catálogo,
 *                      operación de hoy, auditoría reciente y próximas salidas.
 *     ?tab=viajes      Historial completo de viajes, con estado, motivo de
 *                      cancelación y recaudo, filtrable y paginado.
 *     ?tab=usuarios    Historial de usuarios: rol, reservas, viajes
 *                      conducidos, calificación, gasto y cuenta de Google.
 *     ?tab=rutas       Historial y rendimiento de cada ruta.
 *     ?tab=ganancias   Ingresos REALES (reservas confirmadas), comparados con
 *                      el período anterior, desglosados por día, método de
 *                      pago, ruta, conductor y pasajero.
 *
 *   El permiso del módulo sigue siendo 'reportes' en gestion_permisos.php
 *   para no romper los perfiles ya guardados en la base de datos.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirAdmin();
Auth::requerirAcceso('reportes');

/* -------------------------------------------------------------------------- */
/* Filtros comunes a todas las secciones                                       */
/* -------------------------------------------------------------------------- */
$rango = (string)($_GET['rango'] ?? '30');
[$desde, $hasta] = InformacionService::fechasDesdeParametro(
    $rango,
    (string)($_GET['desde'] ?? ''),
    (string)($_GET['hasta'] ?? '')
);

$tab = (string)($_GET['tab'] ?? 'general');
$secciones = ['general', 'viajes', 'usuarios', 'rutas', 'ganancias'];
if (!in_array($tab, $secciones, true)) $tab = 'general';

$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

/** URL conservando los filtros de la sección actual. */
$url = static function (array $c = []) use ($rango, $desde, $hasta, $tab, $e): string {
    $p = array_filter(array_merge([
        'rango' => $rango, 'desde' => $desde, 'hasta' => $hasta, 'tab' => $tab,
    ], $c), static fn($v) => $v !== '' && $v !== null);

    return 'reportes.php?' . http_build_query($p);
};

/** Muestra el rango de fechas solo cuando el período no es "todo". */
$mostrarRango = $rango !== 'todo';

$tituloPagina = 'Panel de información';
include __DIR__ . '/../views/partials/head.php';
?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="sget-shell">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="sget-main">
        <div class="sget-toast-zona" role="status" aria-live="polite"></div>

        <!-- ============================ ENCABEZADO ============================ -->
        <header class="sget-page-head">
            <div>
                <h1 class="sget-page-title">
                    <i class="fas fa-chart-pie text-sky-500"></i> Panel de información
                </h1>
                <p class="sget-page-sub">
                    Información general del sistema, historiales de viajes, usuarios y rutas,
                    y las ganancias reales del negocio.
                </p>
            </div>
            <div class="sget-page-actions">
                <a class="sget-btn sget-btn--neutro" href="<?= $e($url(['tab' => 'viajes', 'pagina' => null])) ?>">
                    <i class="fas fa-clock-rotate-left"></i> Ver historial de viajes
                </a>
                <a class="sget-btn sget-btn--primario" href="<?= $e($url(['tab' => 'ganancias'])) ?>">
                    <i class="fas fa-money-bill-wave"></i> Ver ganancias
                </a>
            </div>
        </header>

        <!-- ============================= FILTROS ============================== -->
        <form method="GET" class="sget-toolbar" data-sget-form-solo>
            <input type="hidden" name="tab" value="<?= $e($tab) ?>">

            <div class="sget-field" style="flex:0 1 13rem">
                <label class="sget-label" for="f_rango">Período</label>
                <select id="f_rango" name="rango" class="sget-select" onchange="this.form.submit()">
                    <?php foreach (InformacionService::rangosRapidos() as $valor => $etiqueta): ?>
                        <option value="<?= $e((string)$valor) ?>" <?= $rango === (string)$valor ? 'selected' : '' ?>>
                            <?= $e($etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sget-field" style="flex:1 1 9rem">
                <label class="sget-label" for="f_desde">Desde</label>
                <input type="date" id="f_desde" name="desde" class="sget-input"
                       value="<?= $e($desde) ?>" max="<?= $e($hasta) ?>">
            </div>

            <div class="sget-field" style="flex:1 1 9rem">
                <label class="sget-label" for="f_hasta">Hasta</label>
                <input type="date" id="f_hasta" name="hasta" class="sget-input"
                       value="<?= $e($hasta) ?>" min="<?= $e($desde) ?>">
            </div>

            <button type="submit" class="sget-btn sget-btn--primario">
                <i class="fas fa-filter"></i> Aplicar
            </button>
        </form>

        <p class="sget-help" style="margin-top:-.5rem">
            <i class="fas fa-circle-info"></i>
            <?php if ($mostrarRango): ?>
                Periodo del <strong><?= $e(date('d/m/Y', strtotime($desde))) ?></strong> al
                <strong><?= $e(date('d/m/Y', strtotime($hasta))) ?></strong>.
            <?php else: ?>
                Periodo: <strong>todo el histórico</strong>.
            <?php endif; ?>
            Los ingresos cuentan únicamente reservas <strong>confirmadas</strong>.
        </p>

        <!-- ============================== PESTAÑAS ============================= -->
        <div class="sget-tabs" role="tablist" aria-label="Secciones del panel de información">
            <?php foreach ([
                'general'   => ['fa-chart-pie', 'Información general'],
                'viajes'    => ['fa-bus',      'Historial de viajes'],
                'usuarios'  => ['fa-users',    'Historial de usuarios'],
                'rutas'     => ['fa-route',    'Historial de rutas'],
                'ganancias' => ['fa-money-bill-wave', 'Ganancias'],
            ] as $clave => [$icono, $etiqueta]): ?>
                <a class="sget-tab" role="tab" aria-selected="<?= $tab === $clave ? 'true' : 'false' ?>"
                   href="<?= $e($url(['tab' => $clave, 'pagina' => null])) ?>">
                    <i class="fas <?= $icono ?>"></i> <?= $e($etiqueta) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- ================================================================== -->
        <!-- 1 · INFORMACIÓN GENERAL                                              -->
        <!-- ================================================================== -->
        <?php if ($tab === 'general'):
            $g          = InformacionService::panelGeneral();
            $porRol     = InformacionService::composicionUsuarios();
            $flota      = InformacionService::composicionFlota();
            $estados    = InformacionService::estadosDeViajes();
            $proximos   = InformacionService::proximosViajes(6);
            $actividad  = InformacionService::actividadReciente(8);
            $maxRol     = 1;
            foreach ($porRol as $r) $maxRol = max($maxRol, (int)$r['total']);
            $maxEstado  = 1;
            foreach ($estados as $e2) $maxEstado = max($maxEstado, (int)$e2['total']);
        ?>

            <!-- ---- Indicadores globales ---- -->
            <section class="sget-grid sget-grid--kpi">
                <?php
                /* [icono, color, título, valor, detalle opcional]
                   OJO: solo iconos de Font Awesome 6 FREE. Los de PRO
                   (fa-sack-dollar, fa-coins…) se dibujan como un cuadro vacío. */
                $kpis = [
                    ['fa-users',        'var(--sget-morado)',  'Usuarios registrados',  InformacionService::numero($g['usuarios']),            InformacionService::numero($g['usuarios_suspendidos']) . ' suspendido(s)'],
                    ['fa-user-tie',     'var(--sget-azul)',    'Conductores',           InformacionService::numero($g['conductores']),         InformacionService::numero($g['conductores_libres']) . ' disponible(s)'],
                    ['fa-user',         'var(--sget-azul)',    'Pasajeros',            InformacionService::numero($g['pasajeros']),           'Rol de pasajero'],
                    ['fa-bus',          'var(--sget-emerald)', 'Vehículos',            InformacionService::numero($g['vehiculos']),           InformacionService::numero($g['vehiculos_libres']) . ' disponible(s)'],
                    ['fa-route',        'var(--sget-ambars)',  'Rutas',                InformacionService::numero($g['rutas']),                 InformacionService::numero($g['rutas_activas']) . ' activa(s)'],
                    ['fa-bus-simple',   'var(--sget-morado)',  'Viajes totales',       InformacionService::numero($g['viajes']),               InformacionService::numero($g['viajes_mes']) . ' este mes'],
                    ['fa-ticket',       'var(--sget-azul)',    'Reservas',             InformacionService::numero($g['reservas']),             InformacionService::numero($g['reservas_pendientes']) . ' pendiente(s) de pago'],
                    ['fa-money-bill-wave','var(--sget-emerald)','Ingresos cobrados',    InformacionService::money($g['ingresos']),              InformacionService::money($g['ingresos_mes']) . ' este mes'],
                    ['fa-calendar-day', 'var(--sget-ambars)',  'Viajes de hoy',        InformacionService::numero($g['viajes_hoy']),            InformacionService::numero($g['viajes_en_curso']) . ' en curso'],
                    ['fa-hourglass-half','var(--sget-rojo)',   'Por cobrar',           InformacionService::money($g['por_cobrar']),            'Reservas sin confirmar'],
                ];
                foreach ($kpis as [$icono, $color, $titulo, $valor, $detalle]): ?>
                    <div class="sget-card sget-kpi">
                        <span class="sget-kpi__icono"
                              style="background:color-mix(in srgb,<?= $color ?> 14%,transparent);color:<?= $color ?>">
                            <i class="fas <?= $icono ?>"></i></span>
                        <div style="min-width:0">
                            <p class="sget-label"><?= $e($titulo) ?></p>
                            <p class="sget-kpi__valor" style="font-size:1.25rem"><?= $valor ?></p>
                            <p class="sget-help sget-linea-1"><?= $e($detalle) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

            <div class="sget-grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,22rem),1fr))">

                <!-- ---- Composición de usuarios por rol ---- -->
                <section class="sget-card">
                    <p class="sget-label" style="margin-bottom:.75rem">
                        <i class="fas fa-users"></i> Composición de usuarios
                    </p>
                    <div class="sget-cols-2" style="gap:.75rem">
                        <?php foreach ($porRol as $r): ?>
                            <div>
                                <p class="sget-label" style="margin-bottom:.25rem">
                                    <?= $e((string)$r['nom_rol']) ?>
                                </p>
                                <p class="sget-mono" style="font-weight:800">
                                    <?= InformacionService::numero($r['total']) ?>
                                    <span class="sget-help" style="font-weight:600">
                                        (<?= InformacionService::numero($r['activos']) ?> activos)
                                    </span>
                                </p>
                                <div style="height:.375rem;border-radius:999px;background:var(--sget-superficie-2);margin-top:.25rem;overflow:hidden">
                                    <div style="height:100%;width:<?= (int)$r['total'] > 0 ? round(((int)$r['total'] / $maxRol) * 100) : 0 ?>%;
                                                background:linear-gradient(90deg,var(--sget-azul),var(--sget-morado))"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="sget-help" style="margin-top:.75rem">
                        <i class="fab fa-google"></i>
                        <?= InformacionService::numero($g['cuentas_google']) ?> cuenta(s) con acceso por Google.
                    </p>
                </section>

                <!-- ---- Estados de los viajes ---- -->
                <section class="sget-card">
                    <p class="sget-label" style="margin-bottom:.75rem">
                        <i class="fas fa-flag-checkered"></i> Estado de los viajes
                    </p>
                    <div class="sget-cols-2" style="gap:.75rem">
                        <?php foreach ($estados as $est): ?>
                            <div>
                                <p class="sget-label" style="margin-bottom:.25rem">
                                    <?= $e((string)$est['estado']) ?>
                                </p>
                                <p class="sget-mono" style="font-weight:800">
                                    <?= InformacionService::numero($est['total']) ?>
                                </p>
                                <div style="height:.375rem;border-radius:999px;background:var(--sget-superficie-2);margin-top:.25rem;overflow:hidden">
                                    <div style="height:100%;width:<?= (int)$est['total'] > 0 ? round(((int)$est['total'] / $maxEstado) * 100) : 0 ?>%;
                                                background:<?= $est['estado'] === Config::VIA_CANCELADO ? 'var(--sget-rojo)'
                                                    : ($est['estado'] === Config::VIA_FINALIZADO ? 'var(--sget-emerald)' : 'var(--sget-azul)') ?>"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="sget-cols-2" style="gap:.5rem;margin-top:.75rem">
                        <p class="sget-help">
                            Tasa de cierre: <strong><?= InformacionService::numero($g['tasa_cierre']) ?>%</strong>
                        </p>
                        <p class="sget-help">
                            Tasa de cancelación: <strong><?= InformacionService::numero($g['tasa_cancelacion']) ?>%</strong>
                        </p>
                    </div>
                </section>

                <!-- ---- Situación de la flota ---- -->
                <section class="sget-card">
                    <p class="sget-label" style="margin-bottom:.75rem">
                        <i class="fas fa-bus"></i> Situación de la flota
                    </p>
                    <?php if (empty($flota)): ?>
                        <p class="sget-help">No hay vehículos registrados.</p>
                    <?php else: ?>
                        <div class="sget-table-wrap">
                            <table class="sget-table sget-table--compacta">
                                <thead><tr>
                                    <th>Estado</th><th class="sget-centro">Unidades</th>
                                    <th class="acciones">Capacidad</th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ($flota as $f): ?>
                                    <tr>
                                        <td data-label="Estado">
                                            <span class="sget-badge <?= (int)$f['est_veh'] === Config::VEH_DISPONIBLE ? 'sget-badge--exito' : 'sget-badge--neutro' ?>">
                                                <?= (int)$f['est_veh'] === Config::VEH_DISPONIBLE ? 'Disponible' : 'Fuera de servicio' ?>
                                            </span>
                                        </td>
                                        <td data-label="Unidades" class="sget-centro sget-mono"><?= InformacionService::numero($f['total']) ?></td>
                                        <td class="acciones" data-label="Capacidad">
                                            <span class="sget-mono"><?= InformacionService::numero($f['capacidad']) ?> cupos</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- ---- Operación de hoy ---- -->
                <section class="sget-card">
                    <p class="sget-label" style="margin-bottom:.75rem">
                        <i class="fas fa-clipboard-list"></i> Operación de hoy · <?= $e(date('d/m/Y')) ?>
                    </p>
                    <div class="sget-cols-2" style="gap:.5rem">
                        <p class="sget-help">Viajes programados hoy: <strong><?= InformacionService::numero($g['viajes_hoy']) ?></strong></p>
                        <p class="sget-help">Viajes en curso: <strong><?= InformacionService::numero($g['viajes_en_curso']) ?></strong></p>
                        <p class="sget-help">Próximas salidas: <strong><?= InformacionService::numero($g['viajes_programados']) ?></strong></p>
                        <p class="sget-help">Reservas pendientes de pago: <strong><?= InformacionService::numero($g['reservas_pendientes']) ?></strong></p>
                        <p class="sget-help">Ocupación histórica: <strong><?= InformacionService::numero($g['ocupacion']) ?>%</strong></p>
                        <p class="sget-help">Ticket promedio: <strong><?= InformacionService::money($g['ticket_promedio']) ?></strong></p>
                    </div>
                </section>

                <!-- ---- Próximas salidas ---- -->
                <section class="sget-card" style="grid-column:1/-1">
                    <p class="sget-label" style="margin-bottom:.75rem">
                        <i class="fas fa-forward"></i> Próximas salidas programadas
                    </p>
                    <?php if (empty($proximos)): ?>
                        <p class="sget-help">No hay viajes programados en los próximos días.</p>
                    <?php else: ?>
                        <div class="sget-table-wrap">
                            <table class="sget-table sget-table--compacta">
                                <thead><tr>
                                    <th>Salida</th><th>Ruta</th><th>Conductor</th><th>Unidad</th>
                                    <th class="sget-centro">Pasajeros</th><th class="sget-centro">Estado</th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ($proximos as $v):
                                    $cupos = (int)$v['cup_tot'];
                                    $vend  = (int)$v['reservas'];
                                    $ocup  = $cupos > 0 ? min(100, (int)round(($vend / $cupos) * 100)) : 0; ?>
                                    <tr>
                                        <td data-label="Salida" class="sget-mono sget-nowrap">
                                            <?= Fecha::legible($v['fec_via'], false) ?><br>
                                            <span class="sget-suave"><?= Fecha::soloHora($v['hor_sal_via']) ?></span>
                                        </td>
                                        <td data-label="Ruta">
                                            <?= $e((string)$v['nom_rut']) ?><br>
                                            <span class="sget-help"><?= $e(trim(($v['ori_rut'] ?? '') . ' → ' . ($v['des_rut'] ?? ''))) ?></span>
                                        </td>
                                        <td data-label="Conductor" class="sget-truncar"><?= $e((string)($v['conductor'] ?: 'Sin asignar')) ?></td>
                                        <td data-label="Unidad" class="sget-mono"><?= $e((string)($v['pla_veh'] ?: '—')) ?></td>
                                        <td data-label="Pasajeros" class="sget-centro">
                                            <span class="sget-mono"><?= $vend ?>/<?= $cupos ?></span>
                                            <span class="sget-badge <?= $ocup >= 80 ? 'sget-badge--exito' : ($ocup >= 40 ? 'sget-badge--aviso' : 'sget-badge--neutro') ?>">
                                                <?= $ocup ?>%
                                            </span>
                                        </td>
                                        <td data-label="Estado" class="sget-centro">
                                            <span class="sget-badge <?= ViajeService::claseEstado((string)$v['est_via']) ?>">
                                                <?= $e((string)$v['est_via']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- ---- Auditoría reciente ---- -->
                <section class="sget-card" style="grid-column:1/-1">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:.75rem">
                        <p class="sget-label">
                            <i class="fas fa-file-alt"></i> Actividad reciente del sistema
                        </p>
                        <a class="sget-btn sget-btn--sm sget-btn--fantasma" href="logs.php">
                            Ver auditoría completa <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <?php if (empty($actividad)): ?>
                        <p class="sget-help">Todavía no hay movimientos registrados.</p>
                    <?php else: ?>
                        <div class="sget-table-wrap">
                            <table class="sget-table sget-table--compacta">
                                <thead><tr>
                                    <th>Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ($actividad as $l): ?>
                                    <tr>
                                        <td data-label="Fecha" class="sget-mono sget-nowrap sget-suave">
                                            <?= $e(Fecha::legible($l['fec_log'], false)) ?>
                                        </td>
                                        <td data-label="Usuario" class="sget-truncar">
                                            <?= $e((string)$l['nom_usu_log']) ?>
                                            <span class="sget-help"><?= $e((string)$l['nom_rol_log']) ?></span>
                                        </td>
                                        <td data-label="Acción">
                                            <span class="sget-badge <?= LogService::claseAccion((string)$l['accion']) ?>">
                                                <?= $e(str_replace('_', ' ', (string)$l['accion'])) ?>
                                            </span>
                                        </td>
                                        <td data-label="Detalle" class="sget-suave sget-truncar">
                                            <?= $e((string)$l['descripcion']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

        <!-- ================================================================== -->
        <!-- 2 · HISTORIAL DE VIAJES                                              -->
        <!-- ================================================================== -->
        <?php elseif ($tab === 'viajes'):
            $filtros = [
                'desde'     => (string)($_GET['desde'] ?? ''),
                'hasta'     => (string)($_GET['hasta'] ?? ''),
                'estado'    => (string)($_GET['estado'] ?? ''),
                'conductor' => (string)($_GET['conductor'] ?? ''),
                'q'         => mb_substr(trim((string)($_GET['q'] ?? '')), 0, 60),
            ];
            $pagina = max(1, (int)($_GET['pagina'] ?? 1));
            $hist   = InformacionService::historialViajes($filtros, $pagina, 25);
        ?>

            <form method="GET" class="sget-toolbar" data-sget-form-solo>
                <input type="hidden" name="tab" value="viajes">
                <input type="hidden" name="rango" value="<?= $e($rango) ?>">
                <input type="hidden" name="desde" value="<?= $e($filtros['desde']) ?>">
                <input type="hidden" name="hasta" value="<?= $e($filtros['hasta']) ?>">

                <div class="sget-field" style="flex:1 1 14rem">
                    <label class="sget-label" for="f_q">Buscar</label>
                    <input type="search" id="f_q" name="q" class="sget-input" value="<?= $e($filtros['q']) ?>"
                           placeholder="Ruta, conductor, placa o # de viaje">
                </div>

                <div class="sget-field" style="flex:0 1 11rem">
                    <label class="sget-label" for="f_estado">Estado</label>
                    <select id="f_estado" name="estado" class="sget-select">
                        <option value="">Todos</option>
                        <?php foreach (Config::VIA_ESTADOS as $estado): ?>
                            <option value="<?= $e($estado) ?>" <?= $filtros['estado'] === $estado ? 'selected' : '' ?>>
                                <?= $e($estado) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sget-field" style="flex:0 1 13rem">
                    <label class="sget-label" for="f_conductor">Conductor</label>
                    <select id="f_conductor" name="conductor" class="sget-select">
                        <option value="">Todos</option>
                        <?php foreach (InformacionService::conductores() as $c): ?>
                            <option value="<?= (int)$c['id_usu'] ?>" <?= $filtros['conductor'] === (string)$c['id_usu'] ? 'selected' : '' ?>>
                                <?= $e((string)$c['nom_usu']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="sget-btn sget-btn--primario">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
                <a class="sget-btn sget-btn--neutro" href="<?= $e($url(['q' => null, 'estado' => null, 'conductor' => null, 'pagina' => null])) ?>">
                    <i class="fas fa-rotate-left"></i> Limpiar
                </a>
            </form>

            <p class="sget-help" style="margin-top:-.5rem">
                <i class="fas fa-circle-info"></i>
                <strong><?= InformacionService::numero($hist['total']) ?></strong> viaje(s) en el historial.
            </p>

            <div class="sget-table-box">
                <div class="sget-table-wrap">
                    <table class="sget-table sget-table--compacta">
                        <thead><tr>
                            <th>#</th><th>Salida</th><th>Viaje</th><th>Conductor</th><th>Unidad</th>
                            <th class="sget-centro">Ocup.</th><th class="acciones">Recaudo</th>
                            <th class="sget-centro">Estado</th>
                        </tr></thead>
                        <tbody>
                        <?php if (empty($hist['filas'])): ?>
                            <tr><td colspan="8" class="sget-sin-resultados">Ningún viaje coincide con el filtro.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($hist['filas'] as $v):
                            $cupos = (int)($v['cup_tot'] ?? 0);
                            $vend  = (int)($v['reservas'] ?? 0);
                            $ocup  = $cupos > 0 ? min(100, (int)round(($vend / $cupos) * 100)) : 0; ?>
                            <tr>
                                <td data-label="ID" class="sget-mono sget-suave">#<?= (int)$v['id_via'] ?></td>
                                <td data-label="Salida" class="sget-mono sget-nowrap">
                                    <?= Fecha::legible($v['fec_via'], false) ?><br>
                                    <span class="sget-suave"><?= Fecha::soloHora($v['hor_sal_via']) ?></span>
                                </td>
                                <td data-label="Viaje">
                                    <?= $e((string)$v['nom_rut']) ?><br>
                                    <span class="sget-help"><?= $e(trim(($v['ori_rut'] ?? '') . ' → ' . ($v['des_rut'] ?? ''))) ?></span>
                                </td>
                                <td data-label="Conductor" class="sget-truncar"><?= $e((string)($v['conductor'] ?: 'Sin asignar')) ?></td>
                                <td data-label="Unidad" class="sget-mono"><?= $e((string)($v['pla_veh'] ?: '—')) ?></td>
                                <td data-label="Ocupación" class="sget-centro">
                                    <span class="sget-mono"><?= $vend ?>/<?= $cupos ?></span>
                                    <span class="sget-badge <?= $ocup >= 80 ? 'sget-badge--exito' : ($ocup >= 40 ? 'sget-badge--aviso' : 'sget-badge--neutro') ?>">
                                        <?= $ocup ?>%
                                    </span>
                                </td>
                                <td class="acciones" data-label="Recaudo">
                                    <span class="sget-mono" style="font-weight:800"><?= InformacionService::money((float)$v['recaudo']) ?></span>
                                    <span class="sget-help"><?= InformacionService::numero($v['val_via']) ?> pax</span>
                                </td>
                                <td data-label="Estado" class="sget-centro">
                                    <span class="sget-badge <?= ViajeService::claseEstado((string)$v['est_via']) ?>">
                                        <?= $e((string)$v['est_via']) ?>
                                    </span>
                                    <?php if (!empty($v['motivo_cancelacion'])): ?>
                                        <span class="sget-help" title="<?= $e((string)$v['anotacion_cancelacion']) ?>">
                                            <?= $e(ViajeService::etiquetaMotivo((string)$v['motivo_cancelacion'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($hist['paginas'] > 1): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap;margin-top:.75rem">
                    <p class="sget-help">
                        Página <?= (int)$hist['pagina'] ?> de <?= (int)$hist['paginas'] ?>
                        · <?= InformacionService::numero($hist['total']) ?> registro(s)
                    </p>
                    <div style="display:flex;gap:.5rem">
                        <?php if ($hist['pagina'] > 1): ?>
                            <a class="sget-btn sget-btn--sm sget-btn--neutro"
                               href="<?= $e($url(['pagina' => $hist['pagina'] - 1])) ?>">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </a>
                        <?php endif; ?>
                        <?php if ($hist['pagina'] < $hist['paginas']): ?>
                            <a class="sget-btn sget-btn--sm sget-btn--primario"
                               href="<?= $e($url(['pagina' => $hist['pagina'] + 1])) ?>">
                                Siguiente <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <!-- ================================================================== -->
        <!-- 3 · HISTORIAL DE USUARIOS                                            -->
        <!-- ================================================================== -->
        <?php elseif ($tab === 'usuarios'):
            $filtros = [
                'rol'    => (int)($_GET['rol'] ?? 0),
                'estado' => (string)($_GET['estado'] ?? ''),
                'q'      => mb_substr(trim((string)($_GET['q'] ?? '')), 0, 60),
            ];
            $pagina = max(1, (int)($_GET['pagina'] ?? 1));
            $histU  = InformacionService::historialUsuarios($filtros, $pagina, 25);
        ?>

            <form method="GET" class="sget-toolbar" data-sget-form-solo>
                <input type="hidden" name="tab" value="usuarios">
                <input type="hidden" name="rango" value="<?= $e($rango) ?>">

                <div class="sget-field" style="flex:1 1 14rem">
                    <label class="sget-label" for="fu_q">Buscar</label>
                    <input type="search" id="fu_q" name="q" class="sget-input" value="<?= $e($filtros['q']) ?>"
                           placeholder="Nombre, documento, correo o teléfono">
                </div>

                <div class="sget-field" style="flex:0 1 11rem">
                    <label class="sget-label" for="fu_rol">Rol</label>
                    <select id="fu_rol" name="rol" class="sget-select">
                        <option value="0">Todos</option>
                        <?php foreach (InformacionService::composicionUsuarios() as $r): ?>
                            <option value="<?= (int)$r['id_rol'] ?>" <?= $filtros['rol'] === (int)$r['id_rol'] ? 'selected' : '' ?>>
                                <?= $e((string)$r['nom_rol']) ?> (<?= (int)$r['total'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sget-field" style="flex:0 1 10rem">
                    <label class="sget-label" for="fu_estado">Cuenta</label>
                    <select id="fu_estado" name="estado" class="sget-select">
                        <option value="">Todas</option>
                        <option value="1" <?= $filtros['estado'] === '1' ? 'selected' : '' ?>>Activas</option>
                        <option value="0" <?= $filtros['estado'] === '0' ? 'selected' : '' ?>>Suspendidas</option>
                    </select>
                </div>

                <button type="submit" class="sget-btn sget-btn--primario">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
                <a class="sget-btn sget-btn--neutro" href="<?= $e($url(['q' => null, 'rol' => null, 'estado' => null, 'pagina' => null])) ?>">
                    <i class="fas fa-rotate-left"></i> Limpiar
                </a>
            </form>

            <p class="sget-help" style="margin-top:-.5rem">
                <i class="fas fa-circle-info"></i>
                <strong><?= InformacionService::numero($histU['total']) ?></strong> usuario(s) en el historial.
            </p>

            <div class="sget-table-box">
                <div class="sget-table-wrap">
                    <table class="sget-table sget-table--compacta">
                        <thead><tr>
                            <th>Usuario</th><th>Contacto</th><th>Rol</th>
                            <th class="sget-centro">Reservas</th><th class="acciones">Pagado</th>
                            <th class="sget-centro">Viajes</th><th class="sget-centro">Calificación</th>
                            <th class="sget-centro">Última reserva</th><th class="sget-centro">Cuenta</th>
                        </tr></thead>
                        <tbody>
                        <?php if (empty($histU['filas'])): ?>
                            <tr><td colspan="9" class="sget-sin-resultados">Ningún usuario coincide con el filtro.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($histU['filas'] as $u):
                            $cal = $u['promedio'] !== null ? (float)$u['promedio'] : null; ?>
                            <tr>
                                <td data-label="Usuario">
                                    <div style="display:flex;align-items:center;gap:.625rem">
                                        <span class="sget-avatar">
                                            <?= $e(mb_strtoupper(mb_substr((string)$u['nom_usu'], 0, 1))) ?>
                                        </span>
                                        <span class="sget-truncar">
                                            <?= $e((string)$u['nom_usu']) ?><br>
                                            <span class="sget-help sget-mono">
                                                <?= $e(trim(($u['tip_doc_usu'] ?? '') . ' ' . ($u['num_doc_usu'] ?? ''))) ?>
                                            </span>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Contacto" class="sget-truncar">
                                    <?= $e((string)$u['corre_usu']) ?><br>
                                    <span class="sget-help"><?= $e((string)($u['tel_usu'] ?: 'Sin teléfono')) ?></span>
                                </td>
                                <td data-label="Rol">
                                    <span class="sget-badge <?= UsuarioService::claseRol((int)$u['id_rol']) ?>">
                                        <?= $e((string)$u['nom_rol']) ?>
                                    </span>
                                    <?php if ((int)$u['id_rol'] === Config::ROL_CONDUCTOR): ?>
                                        <span class="sget-help">
                                            <?= $u['est_con_usu'] !== null && (int)$u['est_con_usu'] === Config::CON_DISPONIBLE
                                                ? 'Disponible' : 'Ocupado' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Reservas" class="sget-centro sget-mono"><?= InformacionService::numero($u['reservas']) ?></td>
                                <td class="acciones" data-label="Pagado">
                                    <span class="sget-mono" style="font-weight:800"><?= InformacionService::money((float)$u['pagado']) ?></span>
                                </td>
                                <td data-label="Viajes" class="sget-centro sget-mono"><?= InformacionService::numero($u['viajes_conducidos']) ?></td>
                                <td data-label="Calificación" class="sget-centro">
                                    <?php if ($cal === null): ?>
                                        <span class="sget-help">—</span>
                                    <?php else: ?>
                                        <span class="sget-badge <?= $cal >= 4 ? 'sget-badge--exito' : ($cal >= 3 ? 'sget-badge--aviso' : 'sget-badge--error') ?>">
                                            <i class="fas fa-star"></i> <?= number_format($cal, 1, ',', '.') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Última reserva" class="sget-centro sget-mono sget-suave sget-nowrap">
                                    <?= $u['ultima_reserva'] ? $e(Fecha::legible($u['ultima_reserva'], false)) : '—' ?>
                                </td>
                                <td data-label="Cuenta" class="sget-centro">
                                    <?php if (!empty($u['google_id'])): ?>
                                        <span class="sget-badge sget-badge--info" title="Acceso con Google vinculado">
                                            <i class="fab fa-google"></i>
                                        </span>
                                    <?php endif; ?>
                                    <span class="sget-badge <?= (int)$u['estado'] === Config::USU_ACTIVO ? 'sget-badge--exito' : 'sget-badge--error' ?>">
                                        <?= $e(UsuarioService::etiquetaEstado($u['estado'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($histU['paginas'] > 1): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap;margin-top:.75rem">
                    <p class="sget-help">
                        Página <?= (int)$histU['pagina'] ?> de <?= (int)$histU['paginas'] ?>
                        · <?= InformacionService::numero($histU['total']) ?> registro(s)
                    </p>
                    <div style="display:flex;gap:.5rem">
                        <?php if ($histU['pagina'] > 1): ?>
                            <a class="sget-btn sget-btn--sm sget-btn--neutro"
                               href="<?= $e($url(['pagina' => $histU['pagina'] - 1])) ?>">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </a>
                        <?php endif; ?>
                        <?php if ($histU['pagina'] < $histU['paginas']): ?>
                            <a class="sget-btn sget-btn--sm sget-btn--primario"
                               href="<?= $e($url(['pagina' => $histU['pagina'] + 1])) ?>">
                                Siguiente <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <!-- ================================================================== -->
        <!-- 4 · HISTORIAL DE RUTAS                                               -->
        <!-- ================================================================== -->
        <?php elseif ($tab === 'rutas'):
            $rutas = InformacionService::historialRutas($desde, $hasta, 100);
            $maxIngresos = 1;
            foreach ($rutas as $r) $maxIngresos = max($maxIngresos, (float)$r['ingresos']);
        ?>

            <p class="sget-help">
                <i class="fas fa-circle-info"></i>
                Rutas ordenadas por ingresos generados en el período seleccionado.
                Las rutas sin viajes en el período aparecen con valores en cero.
            </p>

            <div class="sget-table-box">
                <div class="sget-table-wrap">
                    <table class="sget-table sget-table--compacta">
                        <thead><tr>
                            <th>Ruta</th><th>Trayecto</th><th class="sget-centro">Duración</th>
                            <th class="sget-centro">Tarifa</th><th class="sget-centro">Viajes</th>
                            <th class="sget-centro">Pasajeros</th><th class="sget-centro">Ocupación</th>
                            <th class="acciones">Ingresos</th><th class="sget-centro">Última salida</th>
                        </tr></thead>
                        <tbody>
                        <?php if (empty($rutas)): ?>
                            <tr><td colspan="9" class="sget-sin-resultados">No hay rutas registradas.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rutas as $r):
                            $ocup = (int)$r['cupos'] > 0
                                ? min(100, (int)round(((int)$r['pasajeros'] / (int)$r['cupos']) * 100))
                                : 0; ?>
                            <tr>
                                <td data-label="Ruta">
                                    <?= $e((string)$r['nom_rut']) ?>
                                    <?php if ((int)$r['estado'] !== Config::USU_ACTIVO): ?>
                                        <span class="sget-badge sget-badge--neutro">Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Trayecto" class="sget-suave">
                                    <?= $e(trim(($r['ori_rut'] ?? '') . ' → ' . ($r['des_rut'] ?? ''))) ?>
                                </td>
                                <td data-label="Duración" class="sget-centro sget-mono sget-suave">
                                    <?= RutaService::duracionLegible($r['duracion_min'] ?? null) ?>
                                </td>
                                <td data-label="Tarifa" class="sget-centro sget-mono"><?= InformacionService::money((float)$r['val_rut']) ?></td>
                                <td data-label="Viajes" class="sget-centro sget-mono"><?= InformacionService::numero($r['viajes']) ?></td>
                                <td data-label="Pasajeros" class="sget-centro sget-mono"><?= InformacionService::numero($r['pasajeros']) ?></td>
                                <td data-label="Ocupación" class="sget-centro">
                                    <span class="sget-badge <?= $ocup >= 80 ? 'sget-badge--exito' : ($ocup >= 40 ? 'sget-badge--aviso' : 'sget-badge--neutro') ?>">
                                        <?= $ocup ?>%
                                    </span>
                                </td>
                                <td class="acciones" data-label="Ingresos">
                                    <span class="sget-mono" style="font-weight:800"><?= InformacionService::money((float)$r['ingresos']) ?></span>
                                    <div style="height:.25rem;border-radius:999px;background:var(--sget-superficie-2);margin-top:.25rem">
                                        <div style="height:100%;width:<?= round(((float)$r['ingresos'] / $maxIngresos) * 100) ?>%;
                                                    background:linear-gradient(90deg,var(--sget-azul),var(--sget-morado))"></div>
                                    </div>
                                </td>
                                <td data-label="Última salida" class="sget-centro sget-mono sget-suave sget-nowrap">
                                    <?= $r['ultima_salida'] ? $e(Fecha::legible($r['ultima_salida'], false)) : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- ================================================================== -->
        <!-- 5 · GANANCIAS                                                        -->
        <!-- ================================================================== -->
        <?php else:
            $kpi       = InformacionService::indicadores($desde, $hasta);
            $serie     = InformacionService::serieDiaria($desde, $hasta);
            $metodo    = InformacionService::porMetodoPago($desde, $hasta);
            $porRuta   = InformacionService::ingresosPorRuta($desde, $hasta, 8);
            $porCond   = InformacionService::ingresosPorConductor($desde, $hasta, 10);
            $pasajeros = InformacionService::topPasajeros($desde, $hasta, 8);
            $reservas  = InformacionService::reservas($desde, $hasta, 100);
            $comp      = InformacionService::comparativa($desde, $hasta);
            $anterior  = InformacionService::periodoAnterior($desde, $hasta);

            $maxSerie = 1;
            foreach ($serie as $p) $maxSerie = max($maxSerie, (float)$p['ingresos']);
        ?>

            <!-- ---- Indicadores del período ---- -->
            <section class="sget-grid sget-grid--kpi">
                <?php
                $kpis = [
                    ['fa-money-bill-wave', 'var(--sget-emerald)', 'Ingresos cobrados',  InformacionService::money($kpi['ingresos']), $comp['ingresos_pct']],
                    ['fa-ticket',      'var(--sget-azul)',    'Reservas pagadas',  InformacionService::numero($kpi['reservas']),  $comp['reservas_pct']],
                    ['fa-bus',         'var(--sget-azul)',    'Viajes del período',InformacionService::numero($kpi['viajes']),   $comp['viajes_pct']],
                    ['fa-hourglass-half','var(--sget-ambars)','Por cobrar',        InformacionService::money($kpi['por_cobrar']), null],
                    ['fa-receipt',     'var(--sget-morado)',  'Ticket promedio',   InformacionService::money($kpi['ticket_promedio']), null],
                    ['fa-percent',     'var(--sget-emerald)', 'Ocupación',         $kpi['ocupacion'] . '%', null],
                    ['fa-ban',         'var(--sget-rojo)',    'Viajes cancelados', InformacionService::numero($kpi['viajes_cancelados']), null],
                    ['fa-check',       'var(--sget-emerald)', 'Viajes finalizados',InformacionService::numero($kpi['viajes_finalizados']), null],
                ];
                foreach ($kpis as [$icono, $color, $titulo, $valor, $varia]): ?>
                    <div class="sget-card sget-kpi">
                        <span class="sget-kpi__icono"
                              style="background:color-mix(in srgb,<?= $color ?> 14%,transparent);color:<?= $color ?>">
                            <i class="fas <?= $icono ?>"></i></span>
                        <div style="min-width:0">
                            <p class="sget-label"><?= $e($titulo) ?></p>
                            <p class="sget-kpi__valor" style="font-size:1.25rem"><?= $valor ?></p>
                            <?php if ($varia !== null): ?>
                                <p class="sget-help sget-linea-1" style="color:<?= $varia >= 0 ? 'var(--sget-emerald)' : 'var(--sget-rojo)' ?>">
                                    <i class="fas fa-arrow-trend-<?= $varia >= 0 ? 'up' : 'down' ?>"></i>
                                    <?= InformacionService::variacion($varia) ?> vs. período anterior
                                </p>
                            <?php else: ?>
                                <p class="sget-help sget-linea-1">Sin período anterior para comparar</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

            <p class="sget-help">
                <i class="fas fa-code-compare"></i>
                La comparación usa el período del
                <strong><?= $e(date('d/m/Y', strtotime($anterior[0]))) ?></strong> al
                <strong><?= $e(date('d/m/Y', strtotime($anterior[1]))) ?></strong>.
            </p>

            <div class="sget-grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,26rem),1fr))">

                <!-- ---- Ingresos diarios ---- -->
                <section class="sget-card">
                    <p class="sget-label" style="margin-bottom:.75rem">
                        <i class="fas fa-chart-area"></i> Ingresos diarios
                    </p>
                    <?php if ($maxSerie <= 1): ?>
                        <p class="sget-help">Sin ingresos cobrados en el período seleccionado.</p>
                    <?php else: ?>
                        <div style="display:flex;align-items:flex-end;gap:.25rem;height:9rem">
                            <?php foreach ($serie as $p):
                                $alto = (float)$p['ingresos'] > 0
                                    ? max(6, (int)round(((float)$p['ingresos'] / $maxSerie) * 100))
                                    : 2; ?>
                                <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;height:100%"
                                     title="<?= $e(date('d/m/Y', strtotime($p['dia']))) ?> · <?= InformacionService::money((float)$p['ingresos']) ?> · <?= (int)$p['reservas'] ?> reserva(s)">
                                    <span style="font-size:.5rem;font-weight:800;color:var(--sget-texto-suave)">
                                        <?= (float)$p['ingresos'] > 0 ? InformacionService::numero($p['ingresos']) : '' ?>
                                    </span>
                                    <div style="height:<?= $alto ?>%;border-radius:.25rem .25rem .0625rem .0625rem;
                                                background:<?= (float)$p['ingresos'] > 0
                                                    ? 'linear-gradient(180deg,var(--sget-emerald),#059669)'
                                                    : 'var(--sget-borde)' ?>"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="sget-help" style="margin-top:.5rem;text-align:center">
                            Máximo diario: <?= InformacionService::money($maxSerie) ?>
                        </p>
                    <?php endif; ?>
                </section>

                <!-- ---- Método de pago ---- -->
                <section class="sget-card">
                    <p class="sget-label" style="margin-bottom:.75rem">
                        <i class="fas fa-credit-card"></i> Ingresos por método de pago
                    </p>
                    <?php if (empty($metodo)): ?>
                        <p class="sget-help">Sin pagos confirmados en el período.</p>
                    <?php else:
                        $totalMetodo = array_sum(array_map(static fn($m) => (float)$m['ingresos'], $metodo)) ?: 1; ?>
                        <div class="sget-cols-2" style="gap:.75rem">
                            <?php foreach ($metodo as $m):
                                $pct = round(((float)$m['ingresos'] / $totalMetodo) * 100, 1); ?>
                                <div>
                                    <p class="sget-label" style="margin-bottom:.25rem"><?= $e((string)$m['metodo_pago']) ?></p>
                                    <p class="sget-mono" style="font-weight:800"><?= InformacionService::money((float)$m['ingresos']) ?></p>
                                    <div style="height:.375rem;border-radius:999px;background:var(--sget-superficie-2);margin-top:.25rem;overflow:hidden">
                                        <div style="height:100%;width:<?= $pct ?>%;background:var(--sget-azul)"></div>
                                    </div>
                                    <p class="sget-help"><?= (int)$m['transacciones'] ?> transacción(es) · <?= $pct ?>%</p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- ---- Ganancias por ruta ---- -->
                <section class="sget-card" style="grid-column:1/-1">
                    <p class="sget-label" style="margin-bottom:.75rem">
                        <i class="fas fa-route"></i> Ganancias por ruta
                    </p>
                    <?php if (empty($porRuta)): ?>
                        <p class="sget-help">Sin reservas confirmadas en el período.</p>
                    <?php else: ?>
                        <div class="sget-table-wrap">
                            <table class="sget-table sget-table--compacta">
                                <thead><tr>
                                    <th>Ruta</th><th>Trayecto</th>
                                    <th class="sget-centro">Reservas</th>
                                    <th class="sget-centro">Duración</th>
                                    <th class="acciones">Ingresos</th>
                                </tr></thead>
                                <tbody>
                                <?php $maxRuta = 1; foreach ($porRuta as $t) $maxRuta = max($maxRuta, (float)$t['ingresos']);
                                foreach ($porRuta as $t): ?>
                                    <tr>
                                        <td data-label="Ruta"><?= $e((string)$t['nom_rut']) ?></td>
                                        <td data-label="Trayecto" class="sget-suave">
                                            <?= $e(trim(($t['ori_rut'] ?? '') . ' → ' . ($t['des_rut'] ?? ''))) ?>
                                        </td>
                                        <td data-label="Reservas" class="sget-centro sget-mono"><?= (int)$t['reservas'] ?></td>
                                        <td data-label="Duración" class="sget-centro sget-mono sget-suave">
                                            <?= RutaService::duracionLegible($t['duracion_min'] ?? null) ?>
                                        </td>
                                        <td class="acciones" data-label="Ingresos">
                                            <span class="sget-mono" style="font-weight:800"><?= InformacionService::money((float)$t['ingresos']) ?></span>
                                            <div style="height:.25rem;border-radius:999px;background:var(--sget-superficie-2);margin-top:.25rem">
                                                <div style="height:100%;width:<?= round(((float)$t['ingresos'] / $maxRuta) * 100) ?>%;
                                                            background:linear-gradient(90deg,var(--sget-azul),var(--sget-morado))"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- ---- Ganancias por conductor ---- -->
                <section class="sget-card" style="grid-column:1/-1">
                    <p class="sget-label" style="margin-bottom:.75rem">
                        <i class="fas fa-user-tie"></i> Ganancias por conductor
                    </p>
                    <?php if (empty($porCond)): ?>
                        <p class="sget-help">Sin conductores registrados.</p>
                    <?php else: ?>
                        <div class="sget-table-wrap">
                            <table class="sget-table sget-table--compacta">
                                <thead><tr>
                                    <th>Conductor</th><th class="sget-centro">Viajes</th>
                                    <th class="sget-centro">Reservas</th>
                                    <th class="sget-centro">Calificación</th>
                                    <th class="acciones">Ingresos</th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ($porCond as $c):
                                    $cal = $c['calificacion'] !== null ? (float)$c['calificacion'] : null; ?>
                                    <tr>
                                        <td data-label="Conductor">
                                            <div style="display:flex;align-items:center;gap:.625rem">
                                                <span class="sget-avatar">
                                                    <?= $e(mb_strtoupper(mb_substr((string)$c['nom_usu'], 0, 1))) ?>
                                                </span>
                                                <span class="sget-truncar"><?= $e((string)$c['nom_usu']) ?></span>
                                            </div>
                                        </td>
                                        <td data-label="Viajes" class="sget-centro sget-mono"><?= (int)$c['viajes'] ?></td>
                                        <td data-label="Reservas" class="sget-centro sget-mono"><?= (int)$c['reservas'] ?></td>
                                        <td data-label="Calificación" class="sget-centro">
                                            <?php if ($cal === null): ?>
                                                <span class="sget-badge sget-badge--neutro">Sin evaluar</span>
                                            <?php else: ?>
                                                <span class="sget-badge <?= $cal >= 4 ? 'sget-badge--exito' : ($cal >= 3 ? 'sget-badge--aviso' : 'sget-badge--error') ?>">
                                                    <i class="fas fa-star"></i> <?= number_format($cal, 1, ',', '.') ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="acciones" data-label="Ingresos">
                                            <span class="sget-mono" style="font-weight:800"><?= InformacionService::money((float)$c['ingresos']) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- ---- Pasajeros que más pagan ---- -->
                <section class="sget-card">
                    <p class="sget-label" style="margin-bottom:.75rem">
                        <i class="fas fa-crown"></i> Pasajeros con mayor gasto
                    </p>
                    <?php if (empty($pasajeros)): ?>
                        <p class="sget-help">Sin pagos confirmados en el período.</p>
                    <?php else: ?>
                        <div class="sget-table-wrap">
                            <table class="sget-table sget-table--compacta">
                                <thead><tr>
                                    <th>Pasajero</th><th class="sget-centro">Reservas</th>
                                    <th class="acciones">Pagado</th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ($pasajeros as $p): ?>
                                    <tr>
                                        <td data-label="Pasajero" class="sget-truncar">
                                            <?= $e((string)$p['nom_usu']) ?><br>
                                            <span class="sget-help sget-mono"><?= $e((string)$p['num_doc_usu']) ?></span>
                                        </td>
                                        <td data-label="Reservas" class="sget-centro sget-mono"><?= (int)$p['reservas'] ?></td>
                                        <td class="acciones" data-label="Pagado">
                                            <span class="sget-mono" style="font-weight:800"><?= InformacionService::money((float)$p['pagado']) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- ---- Detalle de cobros ---- -->
                <section class="sget-card" style="grid-column:1/-1">
                    <p class="sget-label" style="margin-bottom:.75rem">
                        <i class="fas fa-receipt"></i> Detalle de cobros del período
                        <span class="sget-help">(<?= InformacionService::numero(count($reservas)) ?> de <?= InformacionService::numero($kpi['reservas']) ?> reservas confirmadas)</span>
                    </p>
                    <?php if (empty($reservas)): ?>
                        <p class="sget-help">No hay cobros registrados en el período.</p>
                    <?php else: ?>
                        <div class="sget-table-wrap">
                            <table class="sget-table sget-table--compacta">
                                <thead><tr>
                                    <th>#</th><th>Fecha</th><th>Pasajero</th><th>Viaje</th>
                                    <th class="sget-centro">Método</th><th class="acciones">Valor</th>
                                    <th class="sget-centro">Estado</th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ($reservas as $r): ?>
                                    <tr>
                                        <td data-label="ID" class="sget-mono sget-suave">#<?= (int)$r['id_res'] ?></td>
                                        <td data-label="Fecha" class="sget-mono sget-nowrap"><?= Fecha::legible($r['fech_res'], false) ?></td>
                                        <td data-label="Pasajero" class="sget-truncar">
                                            <?= $e((string)$r['pasajero']) ?><br>
                                            <span class="sget-help sget-mono"><?= $e((string)$r['num_doc_usu']) ?></span>
                                        </td>
                                        <td data-label="Viaje" class="sget-truncar">
                                            <?= $e((string)$r['nom_rut']) ?><br>
                                            <span class="sget-help">
                                                <?= Fecha::legible($r['fec_via'], false) ?> · <?= Fecha::soloHora($r['hor_sal_via']) ?>
                                            </span>
                                        </td>
                                        <td data-label="Método" class="sget-centro"><?= $e((string)$r['metodo_pago']) ?></td>
                                        <td class="acciones" data-label="Valor">
                                            <span class="sget-mono" style="font-weight:800"><?= InformacionService::money((float)$r['valor_pagado']) ?></span>
                                        </td>
                                        <td data-label="Estado" class="sget-centro">
                                            <span class="sget-badge <?= $r['estado_pago'] === Config::RES_CONFIRMADA ? 'sget-badge--exito' : ($r['estado_pago'] === Config::RES_CANCELADA ? 'sget-badge--error' : 'sget-badge--aviso') ?>">
                                                <?= $e((string)$r['estado_pago']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php
$jsExtra = ['sget-page.js'];
include __DIR__ . '/../views/partials/foot.php';
