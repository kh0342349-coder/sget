<?php

/**
 * pruebas/render.php
 * -----------------------------------------------------------------------------
 * PRUEBAS DE RENDERIZADO
 * -----------------------------------------------------------------------------
 *     touch pruebas/.habilitar
 *     php -S 127.0.0.1:8899 -t .
 *     php pruebas/render.php
 *     rm pruebas/.habilitar
 *
 * Comprueba que todas las páginas responden 200 con la sesión del rol
 * correspondiente, que el HTML contiene los elementos clave del refactor
 * (modales, campos salida/destino, CSS modular) y que NO emiten errores PHP.
 */
declare(strict_types=1);


require_once __DIR__ . '/_guardia.php';
require_once dirname(__DIR__) . '/core/Config.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8899', '/');
$jar  = sys_get_temp_dir() . '/sget_cookies.txt';
@unlink($jar);

$ok = 0; $fallos = 0;
function check(string $t, bool $c, string $d = ''): void
{
    global $ok, $fallos;
    if ($c) { $ok++;  echo "  [OK]   {$t}\n"; }
    else    { $fallos++; echo "  [FALLA] {$t}" . ($d ? " -> {$d}" : '') . "\n"; }
}

function pedir(string $url): string
{
    global $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
    ]);
    $html = (string)curl_exec($ch);
    curl_close($ch);
    return $html;
}

function pedirConCodigo(string $url): array
{
    global $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
    ]);
    $raw  = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, substr($raw, strpos($raw, "\r\n\r\n") + 4)];
}

function iniciarSesion(string $base, int $rol = 1): void
{
    pedir($base . '/pruebas/_sesion_test.php?rol=' . $rol);
}

function sinErroresPhp(string $html): bool
{
    return !preg_match('/(Fatal error|Parse error|Uncaught|Warning:|Deprecated:|Notice:)/i', $html);
}

function detalleError(string $html): string
{
    if (preg_match('/((Fatal error|Parse error|Uncaught|Warning:|Deprecated:|Notice:).*?)(<\/p>|<\/b>|<\/h1>|$)/si', $html, $m)) {
        return substr(trim(preg_replace('/\s+/', ' ', strip_tags($m[0]))), 0, 220);
    }
    return '';
}

/* ========================================================================== */
echo "\n=== Sitio público ===\n";
[$c, $html] = pedirConCodigo($base . '/index.php');
check('index.php responde 200', $c === 200, "código {$c}");
check('index.php carga el CSS modular de la landing', str_contains($html, 'assets/css/index.css'));
check('index.php sin errores PHP', sinErroresPhp($html), detalleError($html));

/* ---------------------------------------------------------------------- */
echo "\n=== Landing: estructura, navegación y coherencia ===\n";

/* 1. Todas las secciones que promete el menú existen de verdad.
   Antes el menú declaraba cinco secciones y el documento solo tenía tres:
   «Nosotros» y «Contacto» eran enlaces muertos. */
$seccionesMenu = ['inicio', 'viajes-disponibles', 'servicios', 'nosotros', 'contacto'];

foreach ($seccionesMenu as $seccion) {
    check("La sección #{$seccion} existe en la landing",
        str_contains($html, 'id="' . $seccion . '"'));
}

preg_match_all('/<section[^>]*id="([^"]+)"/', $html, $mSecciones);
$idsDuplicados = array_diff_assoc(
    $mSecciones[1],
    array_unique($mSecciones[1])
);
check('No hay ids de sección duplicados (HTML válido)', $idsDuplicados === [],
    implode(', ', $idsDuplicados));

/* 2. Los enlaces del menú apuntan a anclas, no a rutas que recargan la página. */
check('El menú usa anclas internas (no `index.php#...`, que recarga la portada)',
    !str_contains($html, 'index.php#'));

check('Cada entrada del menú declara su sección para el resaltado',
    substr_count($html, 'data-sget-seccion=') >= count($seccionesMenu),
    'encontradas: ' . substr_count($html, 'data-sget-seccion='));

check('El desplazamiento respeta `prefers-reduced-motion`',
    str_contains($html, "prefers-reduced-motion: reduce")
    && str_contains($html, "behavior: suave ? 'smooth' : 'auto'"));

/* El ancla del menú fallaba porque se encadenaban DOS desplazamientos
   (scrollIntoView + scrollBy): el segundo cancelaba al primero y la página no se
   movía. Ahora hay una sola llamada y el hueco de la cabecera fija lo declara
   el CSS, en un único sitio. */
/* Se leen los COMENTARIOS fuera: el patrón antiguo aparece justamente en el
   comentario que documenta el fallo, y buscarlo en el HTML entero daría un
   falso positivo. */
$scriptLanding = preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', $html);
check('El ancla del menú hace UN solo desplazamiento',
    str_contains((string)$scriptLanding, 'seccion.scrollIntoView(')
    && !str_contains((string)$scriptLanding, 'window.scrollBy('));

check('El foco viaja a la sección (teclado)',
    str_contains($html, 'seccion.focus({ preventScroll: true })'));

check('El CSS deja margen de scroll para que la cabecera fija no tape los títulos',
    str_contains((string)file_get_contents(Config::raiz('assets/css/index.css')),
        'scroll-margin-top:'));

/* Acceso flotante a SGET */
check('La landing tiene un acceso flotante al sistema',
    str_contains($html, 'id="accesoFlotante"'));
check('El acceso flotante se oculta mientras el aviso de cookies está abierto',
    str_contains($html, 'MutationObserver')
    && str_contains($html, 'sget:cookies-cerradas'));

/* 3. El hero tiene llamadas a la acción: es el punto de entrada de la página. */
check('El hero ofrece «Iniciar sesión»',
    str_contains($html, 'Iniciar sesión') && str_contains($html, 'data-sget-ir-a="panelLogin"'));
check('El hero ofrece «Ver viajes disponibles»',
    str_contains($html, 'Ver viajes disponibles'));

/* 4. El botón del anuncio lleva SIEMPRE al acceso, no a un destino arbitrario. */
check('El botón del anuncio abre el acceso al sistema',
    str_contains($html, 'data-sget-ir-a="panelLogin"'));

/* 5. Los destinos del anuncio solo pueden ser internos: sin redirecciones abiertas.
   El filtrado ocurre en PHP, así que se comprueba el CÓDIGO FUENTE, no el HTML
   servido (en el servido ya no queda rastro de la expresión regular). */
$fuenteLanding = (string)file_get_contents(Config::raiz('index.php'));
check('Los destinos de anuncios se filtran a rutas internas',
    str_contains($fuenteLanding, '(?:https?:)?//'));

/* 6. El panel de cookies refleja la realidad (ver pruebas/cookies.php). */
check('La landing no promete cookies analíticas',
    !str_contains($html, 'Nos ayudan a recopilar información anónima'));
check('La landing declara que no usa analítica',
    str_contains($html, 'No utilizamos cookies analíticas'));
check('El enlace «Configuración de Cookies» sigue disponible',
    str_contains($html, 'abrirConfiguracionCookies()'));

/* ========================================================================== */
echo "\n=== Módulos refactorizados (rol Admin) ===\n";
iniciarSesion($base, Config::ROL_ADMIN);

$modulos = [
    '/Admin/rutas.php'     => ['Gestión de Rutas', 'modalRuta', 'ori_rut', 'des_rut', 'dis_rut', 'val_rut', '01-base.css', '04-modales.css'],
    '/Admin/vehiculos.php' => ['Control de Flota',   'modalVehiculo', 'pla_veh', 'est_veh', '05-tablas.css'],
    '/Admin/usuarios.php'  => ['Administración de Usuarios', 'modalUsuario', 'id_rol_usu', 'sget-tab'],
    '/Admin/viajes.php'    => ['Despacho de Viajes', 'modalViaje', 'fec_via', 'hor_sal_via', 'sget-page.js', '07-transiciones.css'],
];

foreach ($modulos as $ruta => $esperados) {
    [$code, $html] = pedirConCodigo($base . $ruta);
    check("{$ruta} responde 200", $code === 200, "código {$code}");
    if ($code !== 200) continue;

    foreach ($esperados as $aguja) {
        check("{$ruta} contiene «{$aguja}»", str_contains($html, $aguja));
    }
    if ($ruta === '/Admin/viajes.php') {
        $camposViaje = ['id_rut_via', 'id_usu_via', 'id_veh', 'fec_via', 'hor_sal_via', 'hor_lleg_via', 'val_via'];
        $precargaCompleta = true;
        foreach ($camposViaje as $campoViaje) {
            $precargaCompleta = $precargaCompleta
                && str_contains($html, 'data-sget-campo="' . $campoViaje . '"');
        }
        check('El modal de viaje declara todos los campos para precarga', $precargaCompleta);
        check('El modal actualiza disponibilidad al abrirse',
            str_contains($html, "document.addEventListener('sget:modal-abierto'")
            && str_contains($html, "e.detail.id !== 'modalViaje'"));
    }
    if ($ruta === '/Admin/rutas.php') {
        check('El formulario de rutas no solicita hora de salida', !str_contains($html, 'name="hora_salida"'));
    }
    check("{$ruta} incluye el modal de ayuda",    str_contains($html, 'id="modalAyuda"'));
    check("{$ruta} incluye el buzón de avisos",   str_contains($html, 'id="modalNotificaciones"'));
    check("{$ruta} incluye el token CSRF",         str_contains($html, 'name="_token"'));
    check("{$ruta} sin errores PHP",               sinErroresPhp($html), detalleError($html));
}

/* ========================================================================== */
echo "\n=== Módulo de auditoría (rol Admin) ===\n";
iniciarSesion($base, Config::ROL_ADMIN);
[$lcode, $lhtml] = pedirConCodigo($base . '/Admin/logs.php');
check('/Admin/logs.php responde 200', $lcode === 200, "código {$lcode}");

if ($lcode === 200) {
    foreach (['Exportar CSV', 'modalDetalleLog', 'data-sget-fila',
              'sget-transicion.js', '07-transiciones.css', 'sget-barra-progreso',
              'Actividad de los últimos 14 días'] as $aguja) {
        check("/Admin/logs.php contiene «{$aguja}»", str_contains($lhtml, $aguja));
    }
    check('/Admin/logs.php no tiene SQL (usa LogService)', !str_contains($lhtml, 'SELECT ')
                                                     && !str_contains($lhtml, 'FROM sget_logs_auditoria'));
    check('/Admin/logs.php sin errores PHP', sinErroresPhp($lhtml), detalleError($lhtml));

    // Los filtros deben funcionar por URL
    foreach (['accion=LOGIN', 'q=LOGIN', 'desde=' . date('Y-m-d', strtotime('-30 days'))] as $qs) {
        [$c2, $h2] = pedirConCodigo($base . '/Admin/logs.php?' . $qs);
        check("/Admin/logs.php?{$qs} responde 200", $c2 === 200, "código {$c2}");
        check("/Admin/logs.php?{$qs} sin errores PHP", $c2 === 200 && sinErroresPhp($h2), detalleError($h2));
    }

    // La exportación CSV debe devolver un archivo, no HTML.
    // El token viaja en la URL del botón de exportar.
    preg_match('/exportar=csv&amp;_token=([a-f0-9]+)/', $lhtml, $tm);
    $tokenLog = $tm[1] ?? '';
    check('El botón de exportar lleva token CSRF', $tokenLog !== '');
    check('La página expone SGET_CSRF a los scripts', str_contains($lhtml, 'window.SGET_CSRF'));
    if ($tokenLog !== '') {
        $ch = curl_init($base . '/Admin/logs.php?exportar=csv&_token=' . $tokenLog);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_COOKIEJAR      => $jar,
            CURLOPT_COOKIEFILE     => $jar,
        ]);
        $raw = (string)curl_exec($ch);
        curl_close($ch);
        check('La exportación CSV responde con un archivo', str_contains($raw, 'text/csv'), substr($raw, 0, 120));
        check('El CSV trae cabecera y filas', str_contains($raw, 'Fecha y hora') && str_contains($raw, 'Descripci'));
    }
}

echo "\n=== Buscadores: deben empezar vacíos ===\n";
/*
 * Un buscador «se rellena solo» por tres motivos, y los tres se comprueban aquí:
 *
 *   1. El navegador lo autocompleta. Se combaite con `autocomplete="off"` en el
 *      marcado; solo con eso NO basta (Chrome lo ignora en campos con `name`).
 *   2. El HTML llega con un `value` escrito porque el filtro venía por la URL.
 *      Solo se permite en los filtros de SERVIDOR, marcados con
 *      `data-sget-valor-inicial`.
 *   3. Al volver atrás (BFCache) se restaura la página desde memoria. Eso lo
 *      resuelve `sget-modal.js` escuchando `pageshow`.
 */
$carpetasPaginas = glob(Config::raiz('Admin/*.php'))
    + glob(Config::raiz('Conductor/*.php'))
    + glob(Config::raiz('Pasajero/*.php'))
    + glob(Config::raiz('views/modals/*.php'));

$patronBuscador = '/<input\b[^>]*?(?:data-sget-buscar|type="search")[^>]*>/s';
$totalBuscadores = 0;
$sinAutocomplete  = [];
$conValue         = [];

foreach ($carpetasPaginas as $archivo) {
    $contenido = (string)file_get_contents($archivo);

    /* Los bloques PHP se eliminan ANTES de buscar los `<input>`: si no, el
       `?>` de un `value="<?= … ?>"` corta la expresión regular por la mitad y
       la comprobación da un falso positivo. Además, lo interesante aquí es el
       HTML literal: un valor escrito a mano en el marcado, no el que genera PHP
       en tiempo de ejecución (eso se revisa aparte, sobre la página servida). */
    $contenido = preg_replace('/<\?(?:php|=).*?\?>/s', '', (string)$contenido);

    if (!preg_match_all($patronBuscador, (string)$contenido, $m)) {
        continue;
    }
    $rel = str_replace(Config::raiz() . '/', '', $archivo);

    foreach ($m[0] as $tag) {
        $totalBuscadores++;

        if (!str_contains($tag, 'autocomplete="off"')) {
            $sinAutocomplete[] = $rel;
        }
        if (preg_match('/\svalue="([^"]*)"/', $tag, $v) && trim($v[1]) !== ''
            && !str_contains($tag, 'data-sget-valor-inicial')) {
            $conValue[] = $rel . ' -> ' . trim($v[1]);
        }
    }
}

check('Se han revisado los buscadores de todas las pantallas', $totalBuscadores > 0,
    "encontrados: $totalBuscadores");
check('Todos los buscadores llevan autocomplete="off"', $sinAutocomplete === [],
    implode(', ', array_unique($sinAutocomplete)));
check('Ningún buscador llega escrito en el marcado', $conValue === [],
    implode(' | ', $conValue));

/* Ahora sobre el HTML REAL servido, que es donde se vería el síntoma.
   Se piden las pestañas que contienen buscador (`?tab=…`). */
$paginasConBuscador = [
    Config::ROL_ADMIN => [
        '/Admin/rutas.php', '/Admin/vehiculos.php', '/Admin/usuarios.php',
        '/Admin/viajes.php', '/Admin/logs.php', '/Admin/asignaciones.php',
        '/Admin/reportes_pasajeros.php', '/Admin/reportes.php?tab=viajes',
        '/Admin/reportes.php?tab=pasajeros', '/Admin/reportes.php?tab=actividad',
    ],
    Config::ROL_PASAJERO => ['/Pasajero/historial_pasajero.php'],
];

$conTextoServido = 0;
$revisados = 0;

foreach ($paginasConBuscador as $rol => $rutas) {
    iniciarSesion($base, $rol);

    foreach ($rutas as $ruta) {
        [$code, $html] = pedirConCodigo($base . $ruta);
        if ($code !== 200) {
            check("{$ruta} responde 200 (no se pudo revisar su buscador)", false, "código $code");
            continue;
        }
        if (!preg_match_all($patronBuscador, $html, $m)) {
            continue;   // pantalla sin buscador en este estado (p. ej. sin datos)
        }

        $revisados++;
        foreach ($m[0] as $tag) {
            if (!str_contains($tag, 'autocomplete="off"')) {
                check("{$ruta}: buscador servido sin autocomplete=\"off\"", false);
            }
            if (preg_match('/\svalue="([^"]*)"/', $tag, $v) && trim($v[1]) !== ''
                && !str_contains($tag, 'data-sget-valor-inicial')) {
                $conTextoServido++;
            }
        }
    }
}

check('Ninguna pantalla sirve un buscador de cliente ya escrito', $conTextoServido === 0,
    "con texto: $conTextoServido");
check('Se han revisado páginas servidas con buscador', $revisados >= 6,
    "revisadas: $revisados");

/* El vaciado en BFCache solo puede garantizarse desde el motor de modales,
   que es el único script que cargan TODAS las pantallas. */
$jsModal = (string)file_get_contents(Config::raiz('assets/js/sget-modal.js'));
check('El motor común limpia los buscadores al cargar', str_contains($jsModal, 'pageshow'));
check('El motor común neutraliza el autocompletado del navegador',
    str_contains($jsModal, "setAttribute('name', 'sget_q')"));
check('El motor común respeta los filtros de servidor',
    str_contains($jsModal, 'data-sget-valor-inicial'));
check('El motor común NO renombra los `name` de los formularios GET',
    str_contains($jsModal, "method.toLowerCase() === 'get'"));

$jsCru = (string)file_get_contents(Config::raiz('assets/js/sget-cru.js'));
check('El CRUD delega en el motor común (no hay dos implementaciones)',
    str_contains($jsCru, 'SGETModal.limpiarBuscadores')
    && !str_contains($jsCru, "setAttribute('name', 'sget_q')"));

echo "\n=== Páginas heredadas contra el esquema nuevo (por rol) ===\n";

$porRol = [
    Config::ROL_ADMIN => [
        '/Admin/admin.php', '/Admin/asignaciones.php', '/Admin/gestion_permisos.php',
        '/Admin/reportes.php', '/Admin/ranking_conductores.php',
        '/Admin/reportes_pasajeros.php',
    ],
    Config::ROL_CONDUCTOR => [
        '/Conductor/conductor.php', '/Conductor/viajes_conductor.php',
        '/Conductor/viaje_asignado.php', '/Conductor/resenas_conductor.php',
    ],
    Config::ROL_PASAJERO => [
        '/Pasajero/pasajero.php', '/Pasajero/viajes_pasajero.php', '/Pasajero/historial_pasajero.php',
        '/Pasajero/calificar.php',
    ],
];

foreach ($porRol as $rol => $rutas) {
    iniciarSesion($base, $rol);
    foreach ($rutas as $ruta) {
        [$code, $html] = pedirConCodigo($base . $ruta);
        check("{$ruta} (rol {$rol}) responde 200", $code === 200, "código {$code}");
        if ($code !== 200) continue;
        check("{$ruta} (rol {$rol}) sin errores PHP", sinErroresPhp($html), detalleError($html));
    }
}

/* ========================================================================== */
echo "\n" . str_repeat('=', 60) . "\n";
echo " RESULTADO: {$ok} correctas, {$fallos} fallidas\n";
echo str_repeat('=', 60) . "\n\n";
exit($fallos === 0 ? 0 : 1);
