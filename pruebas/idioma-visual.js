/*
 * pruebas/idioma-visual.js
 * -----------------------------------------------------------------------------
 * Verificación del SELECTOR DE IDIOMA de la landing (index.php).
 *
 * El fallo que se comprueba: includes/i18n.php calculaba el prefijo de rutas
 * mirando si `$_SERVER['SCRIPT_NAME']` tenía alguna barra. En una instalación
 * real dentro de una carpeta (http://localhost/sget/) eso es SIEMPRE cierto,
 * también para la landing, así que la página pedía:
 *
 *     <script src="../js/i18n.js">        → 404  ← el script entero no cargaba
 *     window.SGET_LANGUAGE_URL = "../set_language.php"  → 404
 *
 * Resultado: el `<select>` de idioma se quedaba muerto (sin manejadores, sin
 * traducción) y el POST que guarda el idioma en la sesión fallaba en silencio
 * dentro de un try/catch.
 *
 * Uso:
 *     php -S 127.0.0.1:8899 -t .                 (proyecto en la raíz)
 *     php -S 127.0.0.1:8900 -t C:/xampp/htdocs   (proyecto en /html/sget, como Apache)
 *     node pruebas/idioma-visual.js http://127.0.0.1:8900/html/sget/index.php
 * -----------------------------------------------------------------------------
 */
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const URL_BASE = process.argv[2] || 'http://127.0.0.1:8899/index.php';
const PERFIL = path.join(os.tmpdir(), 'sget-chrome-idioma-' + process.pid);

let ok = 0, fallos = 0;
function check(t, c, d = '') {
    if (c) { ok++; console.log('  [OK]    ' + t); }
    else { fallos++; console.log('  [FALLA] ' + t + (d ? ' -> ' + d : '')); }
}

const CHROME_ARGS = (url) => [
    '--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage',
    `--user-data-dir=${PERFIL}`,
    '--virtual-time-budget=30000',
    '--dump-dom',
    url,
];

/* --------------------------------------------------------------------------
   1) Cálculo del prefijo (unitario)
   -------------------------------------------------------------------------- */
console.log('\n=== Cálculo del prefijo de rutas ===');
try {
    execFileSync('php', ['pruebas/prefijo_test.php'], { stdio: 'inherit' });
    check('pruebas/prefijo_test.php pasa todos los casos', true);
} catch (e) {
    check('pruebas/prefijo_test.php pasa todos los casos', false, 'hay casos incorrectos');
}

/* --------------------------------------------------------------------------
   2) HTML servido
   -------------------------------------------------------------------------- */
console.log('\n=== La landing carga sus propios recursos ===');
let dom = '';
try {
    dom = execFileSync(CHROME, CHROME_ARGS(URL_BASE), { maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
} catch (e) {
    console.log('  (no se pudo abrir Chrome: ' + e.message + ')');
}

const idiomaUrl = (dom.match(/SGET_LANGUAGE_URL\s*=\s*"([^"]*)"/) || [])[1];
const i18nSrc   = (dom.match(/src="([^"]*js\/i18n\.js[^"]*)"/) || [])[1];

check('La landing declara SGET_LANGUAGE_URL', !!idiomaUrl, idiomaUrl);
check('La landing carga js/i18n.js', !!i18nSrc, i18nSrc);
check('La URL de set_language.php NO sale de la raíz del proyecto',
      idiomaUrl === 'set_language.php' || idiomaUrl === './set_language.php', idiomaUrl);
check('js/i18n.js NO sale de la raíz del proyecto',
      !!i18nSrc && !i18nSrc.startsWith('../'), i18nSrc);
check('El selector de idioma está en el marcado', /data-sget-language/.test(dom));

/* --------------------------------------------------------------------------
   3) Comportamiento real (ciclo completo de cambio de idioma)
   -------------------------------------------------------------------------- */
console.log('\n=== El cambio de idioma funciona de punta a punta ===');
let probe = '';
try {
    const url = URL_BASE.replace(/\/[^/]*$/, '/pruebas/_idioma_probe.html');
    probe = execFileSync(CHROME, CHROME_ARGS(url), { maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
} catch (e) {
    console.log('  (no se pudo ejecutar la sonda: ' + e.message + ')');
}

const bloque = probe.match(/IDIOMA_START([\s\S]*?)IDIOMA_END/);
if (!bloque) {
    check('La sonda del navegador devolvió resultados', false, 'sin IDIOMA_START');
} else {
    bloque[1].split('\n')
        .map(l => l.trim())
        .filter(l => l && !/^FALLOS=/.test(l))
        .forEach(l => {
            const esInfo = l.startsWith('INFO');
            check(l.split('|').slice(1).join('|').trim() + (esInfo ? ' [info]' : ''),
                  esInfo || l.startsWith('OK'), l);
        });
    const f = bloque[1].match(/FALLOS=(\d+)/);
    check('La sonda no reporta fallos', f && Number(f[1]) === 0, f ? 'fallos=' + f[1] : 'sin dato');
}

console.log('\n' + '='.repeat(64));
console.log(' TOTAL: ' + ok + ' correctas, ' + fallos + ' fallidas');
console.log('='.repeat(64) + '\n');
process.exit(fallos ? 1 : 0);
