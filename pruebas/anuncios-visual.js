/*
 * pruebas/anuncios-visual.js
 * -----------------------------------------------------------------------------
 * Verificación del MÓDULO DE ANUNCIOS de la landing (Admin/anuncios.php) y del
 * carrusel que lo consume (index.php).
 *
 * Uso:
 *     touch pruebas/.habilitar
 *     php -S 127.0.0.1:8899 -t .
 *     node pruebas/anuncios-visual.js
 *     rm pruebas/.habilitar
 * -----------------------------------------------------------------------------
 */
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

/**
 * Valida que el banner por defecto sea un XML bien formado.
 *
 * POR QUÉ HACE FALTA ESTA COMPROBACIÓN
 *   getimagesize() no lee el contenido de un SVG, así que un banner con el XML
 *   roto pasaba la validación de la subida, se guardaba, se servía con un 200…
 *   y el navegador NO lo pintaba: en la landing solo se veía el texto `alt`
 *   sobre el fondo. El caso real era un comentario XML con dos guiones
 *   seguidos (`---`), que es XML inválido y no dice ningún error visible.
 *
 *   Se delega en PHP porque es el mismo parser que usa AnuncioService al
 *   validar la subida, y así la prueba y la aplicación no pueden discrepar.
 */
function svgEsValido(ruta) {
    try {
        execFileSync('php', ['-r', `
            libxml_use_internal_errors(true);
            exit(@simplexml_load_file($argv[1]) !== false ? 0 : 1);
        `, ruta], { stdio: 'ignore' });
        return true;
    } catch (e) {
        return false;
    }
}

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const URL_BASE = process.argv[2] || 'http://127.0.0.1:8899/index.php';
const PERFIL = path.join(os.tmpdir(), 'sget-chrome-anuncio-' + process.pid);

let ok = 0, fallos = 0;
function check(t, c, d = '') {
    if (c) { ok++; console.log('  [OK]    ' + t); }
    else { fallos++; console.log('  [FALLA] ' + t + (d ? ' -> ' + d : '')); }
}

/* --------------------------------------------------------------------------
   1) Estructura del código
   -------------------------------------------------------------------------- */
console.log('\n=== El módulo existe y está separado por capas ===');
check('Existe Admin/anuncios.php', fs.existsSync('Admin/anuncios.php'));
check('Existe el modal views/modals/anuncio.php', fs.existsSync('views/modals/anuncio.php'));
check('Existe services/AnuncioService.php', fs.existsSync('services/AnuncioService.php'));
check('El bootstrap carga AnuncioService',
      /AnuncioService\.php/.test(fs.readFileSync('core/bootstrap.php', 'utf8')));
check('La página no tiene SQL (todo vive en el servicio)',
      !/\bSELECT\b/i.test(fs.readFileSync('Admin/anuncios.php', 'utf8')));
check('El API expone guardar / alternarEstado / eliminar',
      ['guardar', 'alternarEstado', 'eliminar'].every(a => fs.readFileSync('api/index.php', 'utf8').includes("'" + a + "'")));
check('La landing pinta los anuncios con AnuncioService::vigentes',
      /AnuncioService::vigentes\(\)/.test(fs.readFileSync('index.php', 'utf8')));
check('Las imágenes se guardan fuera de la raíz de código',
      /uploads\/anuncios/.test(fs.readFileSync('services/AnuncioService.php', 'utf8')));
check('Se validan tamaño y tipo de la imagen',
      /MAX_BYTES/.test(fs.readFileSync('services/AnuncioService.php', 'utf8'))
      && /EXTENSIONES/.test(fs.readFileSync('services/AnuncioService.php', 'utf8')));
check('El enlace del anuncio solo admite URLs o módulos internos',
      /enlaceSeguro/.test(fs.readFileSync('services/AnuncioService.php', 'utf8')));
check('La migración 006 crea la tabla de anuncios',
      /CREATE TABLE IF NOT EXISTS anuncio/.test(fs.readFileSync('migraciones/006_buzon_y_anuncios.php', 'utf8')));
check('Existe una migración que repara los anuncios sin imagen',
      fs.existsSync('migraciones/007_banner_y_anuncios_rotos.php'));
check('El banner por defecto del ejemplo EXISTE como archivo real',
      fs.existsSync('img/anuncios/portada.svg'));
check('El banner por defecto es un SVG con XML válido (si no, el navegador no lo pinta)',
      svgEsValido('img/anuncios/portada.svg'));
check('Los SVG se validan antes de subirse (XML bien formado y sin script)',
      /validarSvg/.test(fs.readFileSync('services/AnuncioService.php', 'utf8')));
check('El servicio avisa de las imágenes que no están en el servidor',
      /imagenExiste/.test(fs.readFileSync('services/AnuncioService.php', 'utf8')));
check('El menú lateral incluye el módulo',
      /anuncios\.php/.test(fs.readFileSync('includes/sidebar.php', 'utf8')));
check('El carrusel tiene estilos propios',
      /sget-anuncio-carrusel/.test(fs.readFileSync('assets/css/index.css', 'utf8')));

/* ------------------------------------------------------------------ */
/* El carrusel se movió a su propio archivo: la regla de la casa es     */
/* que las páginas no llevan JavaScript escrito dentro.                  */
/* ------------------------------------------------------------------ */
check('El carrusel vive en assets/js/sget-anuncios.js',
      fs.existsSync('assets/js/sget-anuncios.js'));
check('La landing carga el carrusel como archivo, no como <script> en línea',
      /assets\/js\/sget-anuncios\.js/.test(fs.readFileSync('index.php', 'utf8'))
      && !/data-sget-carrusel-move[\s\S]{0,400}addEventListener/.test(fs.readFileSync('index.php', 'utf8')));
check('El carrusel cuenta las vistas (el KPI antes se quedaba en 0)',
      /contarVistas/.test(fs.readFileSync('assets/js/sget-anuncios.js', 'utf8')));
check('Existe el endpoint que suma la vista',
      fs.existsSync('procesos/anuncio_vista.php'));

/* ------------------------------------------------------------------ */
/* La barra de buscador/filtros del módulo no tenía ningún manejador:  */
/* los botones de Todos / Visibles / Ocultos no hacían nada.            */
/* ------------------------------------------------------------------ */
const jsPage = fs.readFileSync('assets/js/sget-page.js', 'utf8');
check('El buscador y los filtros los cablea sget-page.js',
      /prepararBusquedaYFiltros/.test(jsPage));
check('El buscador del módulo está marcado para cablearse',
      /data-sget-buscar/.test(fs.readFileSync('Admin/anuncios.php', 'utf8')));
check('Los listados que ya lo cableaban a mano no lo hacen por segunda vez',
      !/SGETCRUD\.filtrarEstado/.test(fs.readFileSync('Admin/reportes_pasajeros.php', 'utf8')));
check('Cada script lleva su propia versión de caché (no la de otro archivo)',
      !/\$mv\b/.test(fs.readFileSync('views/partials/foot.php', 'utf8')));

console.log('\n' + '='.repeat(64));
console.log(' RESULTADO: ' + ok + ' correctas, ' + fallos + ' fallidas');
console.log('='.repeat(64) + '\n');

/* --------------------------------------------------------------------------
   2) Comportamiento real
   -------------------------------------------------------------------------- */
const PROBAS = [
    ['_anuncio_probe.html',          'ANUNCIO'],
    ['_anuncio_preview_probe.html',  'PREVIEW'],
];

for (const [archivo, marca] of PROBAS) {
    console.log('\n=== ' + archivo + ' ===');

    let probe = '';
    try {
        const url = URL_BASE.replace(/\/[^/]*$/, '/pruebas/' + archivo);
        probe = execFileSync(CHROME, [
            '--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage',
            `--user-data-dir=${PERFIL + '-' + marca}`,
            '--virtual-time-budget=30000',
            '--dump-dom', url,
        ], { maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
    } catch (e) {
        console.log('  (no se pudo ejecutar la sonda: ' + e.message + ')');
    }

    const bloque = probe.match(new RegExp(marca + '_START([\\s\\S]*?)' + marca + '_END'));
    if (!bloque) {
        check('La sonda ' + archivo + ' devolvió resultados', false, 'sin ' + marca + '_START');
        continue;
    }
    bloque[1].split('\n')
        .map(l => l.trim())
        .filter(l => l && !/^FALLOS=/.test(l))
        .forEach(l => {
            const esInfo = l.startsWith('INFO');
            check(l.split('|').slice(1).join('|').trim() + (esInfo ? ' [info]' : ''),
                  esInfo || l.startsWith('OK'), l);
        });
    const f = bloque[1].match(/FALLOS=(\d+)/);
    check('La sonda ' + archivo + ' no reporta fallos', f && Number(f[1]) === 0, f ? 'fallos=' + f[1] : 'sin dato');
}

console.log('\n' + '='.repeat(64));
console.log(' TOTAL: ' + ok + ' correctas, ' + fallos + ' fallidas');
console.log('='.repeat(64) + '\n');
process.exit(fallos ? 1 : 0);
