/*
 * pruebas/modal-visual.js
 * -----------------------------------------------------------------------------
 * Verificación con navegador real (Chrome headless) de que los modales de la
 * landing (login, registro, política, cookies) se abren y cierran de verdad:
 * display, opacidad, tamaño y que no bloquean la página cerrada.
 *
 * Uso:
 *     php -S 127.0.0.1:8899 -t .
 *     node pruebas/modal-visual.js
 * -----------------------------------------------------------------------------
 */
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const URL_BASE = process.argv[2] || 'http://127.0.0.1:8899/index.php';
// Perfil único por ejecución: si dos Chrome usan el mismo --user-data-dir en
// paralelo, el segundo falla con "profile in use" y la sonda devuelve vacío.
const PERFIL = path.join(os.tmpdir(), 'sget-chrome-test-' + process.pid);

let ok = 0, fallos = 0;
function check(t, c, d = '') {
    if (c) { ok++; console.log('  [OK]   ' + t); }
    else { fallos++; console.log('  [FALLA] ' + t + (d ? ' -> ' + d : '')); }
}

/* --------------------------------------------------------------------------
   1) Se ejecuta un snippet en la página y se lee el JSON resultado
   -------------------------------------------------------------------------- */
function enPagina(snippet) {
    const out = path.join(os.tmpdir(), 'sget-cdp-out.txt');
    const r = execFileSync(CHROME, [
        '--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage',
        // Bloquea subrecursos externos: si no, el tiempo virtual se congela
        '--host-resolver-rules=MAP * 127.0.0.1, EXCLUDE 127.0.0.1',
        `--user-data-dir=${PERFIL}`,
        '--virtual-time-budget=9000',
        '--dump-dom',
        URL_BASE,
    ], { maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
    return r;
}

console.log('\n=== Estructura de los modales en el HTML servido ===');
const dom = enPagina();
fs.writeFileSync(path.join(os.tmpdir(), 'sget-dom.html'), dom);

for (const id of ['panelLogin', 'panelRegistro', 'panelPolitica', 'panelConfigCookies']) {
    const re = new RegExp('id="' + id + '"[^>]*');
    const m = dom.match(re);
    check(`#${id} existe en el DOM`, !!m);
    if (!m) continue;

    const attrs = m[0];
    check(`#${id} está marcado como capa del motor (data-sget-capa)`, /data-sget-capa/.test(attrs), attrs.slice(0, 120));
    check(`#${id} NO depende de la utilidad "hidden"`, !/class="[^"]*\bhidden\b/.test(attrs), attrs.slice(0, 120));
    check(`#${id} usa la clase .sget-modal-wrap`, /sget-modal-wrap/.test(attrs));
    check(`#${id} declara role="dialog"`, /role="dialog"/.test(attrs));
}

console.log('\n=== Motor y hoja de estilos ===');
check('Se carga assets/js/sget-modal.js', /assets\/js\/sget-modal\.js/.test(dom));
check('Se carga assets/css/index.css', /assets\/css\/index\.css/.test(dom));
check('index.css importa 01-base.css', /@import url\('01-base\.css'\)/.test(fs.readFileSync('assets/css/index.css', 'utf8')));
check('index.css importa 04-modales.css', /@import url\('04-modales\.css'\)/.test(fs.readFileSync('assets/css/index.css', 'utf8')));

/* La trampa que-was causing the bug: declarar `display` sobre el contenedor */
const css = fs.readFileSync('assets/css/index.css', 'utf8');
const bloque = css.match(/\.modal-isla-container\s*\{[^}]*\}/);
check('`.modal-isla-container` existe en index.css', !!bloque);
check('`.modal-isla-container` NO declara `display` (causaba overlays invisibles)',
    bloque && !/display\s*:/.test(bloque[0]), bloque ? bloque[0] : 'bloque no encontrado');

const navBloque = css.match(/\.landing-nav\s*\{[^}]*\}/);
check('`.landing-nav` NO declara `display` (causaba el menú roto)',
    navBloque && !/^\s*display\s*:/m.test(navBloque[0]), navBloque ? navBloque[0] : 'no encontrado');

console.log('\n=== Controles de los formularios ===');
check('El modal de login tiene el campo de documento', /id="loginDocumento"/.test(dom));
check('El modal de login tiene el campo de contraseña', /id="loginClave"/.test(dom));
check('El modal de registro tiene el confirmador de contraseña', /id="reg_pass_confirm"/.test(dom));
check('Existe la casilla de política de datos', /id="acepta_politica"/.test(dom));
check('Los botones de apertura usan data-sget-modal', /data-sget-modal="panelLogin"/.test(dom));
check('Los botones de navegación interna usan data-sget-ir-a', /data-sget-ir-a="panelRegistro"/.test(dom));
check('El menú móvil tiene botón hamburguesa', /data-sget-burger/.test(dom));
check('Las funciones abrirPanel/cerrarPanel siguen disponibles', /function abrirPanel/.test(dom));

console.log('\n' + '='.repeat(60));
console.log(' RESULTADO: ' + ok + ' correctas, ' + fallos + ' fallidas');
console.log('='.repeat(60) + '\n');

/* ==========================================================================
   PRUEBA DE INTERACCIÓN REAL (Chrome headless sobre _modal_probe.html)
   ========================================================================== */
console.log('\n=== Comportamiento real en el navegador ===');

const CHROME_ARGS = (url) => [
    '--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage',
    `--user-data-dir=${PERFIL}`,
    '--virtual-time-budget=20000',
    '--dump-dom',
    url,
];

let probe = '';
try {
    const url = URL_BASE.replace(/\/index\.php.*$/, '/pruebas/_modal_probe.html');
    probe = execFileSync(CHROME, CHROME_ARGS(url), { maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
} catch (e) {
    check('Se pudo ejecutar la sonda de interacción', false, String(e.message).slice(0, 120));
}
process.on('exit', () => { try { require('fs').rmSync(PERFIL, { recursive: true, force: true }); } catch (e) {} });

const m = probe.match(/MODAL_PROBE_START([\s\S]*?)MODAL_PROBE_END/);
if (m) {
    m[1].trim().split('\n').forEach((line) => {
        if (!line.trim() || !line.includes('|')) return;   // ignora la línea FALLOS=
        if (line.startsWith('INFO')) return;               // líneas de diagnóstico
        const correcto = line.startsWith('OK');
        const partes = line.replace('|', '~~').split('~~').map((s) => s.trim());
        check(partes[1] + (partes[2] ? '  [' + partes[2] + ']' : ''), correcto, correcto ? '' : partes[2] || '');
    });
} else {
    check('La sonda de interacción devolvió resultados', false, 'no se encontró MODAL_PROBE_START');
}

console.log('\n' + '='.repeat(60));
console.log(' TOTAL: ' + ok + ' correctas, ' + fallos + ' fallidas');
console.log('='.repeat(60) + '\n');
process.exit(fallos === 0 ? 0 : 1);
