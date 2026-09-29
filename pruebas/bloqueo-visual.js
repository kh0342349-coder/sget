/*
 * pruebas/bloqueo-visual.js
 * -----------------------------------------------------------------------------
 * Verificación del BLOQUEO DE SESIÓN POR INACTIVIDAD en un navegador real.
 *
 * El fallo que se comprueba: el bloqueo se emitía dentro de `.sget-shell`, que
 * tiene una animación de `transform` y por tanto se convierte en bloque
 * contenedor de sus descendientes `position: fixed`. El `inset: 0` del velo se
 * medía contra `.sget-shell` (que empieza 288 px a la derecha por el margen
 * del sidebar), así que el menú lateral quedaba FUERA del bloqueo y se podía
 * seguir haciendo clic en él con la sesión bloqueada.
 *
 * Uso:
 *     php -S 127.0.0.1:8899 -t .
 *     node pruebas/bloqueo-visual.js
 * -----------------------------------------------------------------------------
 */
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const URL_BASE = process.argv[2] || 'http://127.0.0.1:8899/index.php';
const PERFIL = path.join(os.tmpdir(), 'sget-chrome-bloqueo-' + process.pid);

let ok = 0, fallos = 0;
function check(t, c, d = '') {
    if (c) { ok++; console.log('  [OK]    ' + t); }
    else { fallos++; console.log('  [FALLA] ' + t + (d ? ' -> ' + d : '')); }
}

const CHROME_ARGS = (url) => [
    '--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage',
    `--user-data-dir=${PERFIL}`,
    '--virtual-time-budget=25000',
    '--dump-dom',
    url,
];

/* --------------------------------------------------------------------------
   1) Estructura del código
   -------------------------------------------------------------------------- */
console.log('\n=== El bloqueo se ancla a <body> ===');
const js = fs.readFileSync('js/inactividad.js', 'utf8');
const php = fs.readFileSync('includes/modal_inactividad.php', 'utf8');

check('inactividad.js sube el modal a <body> (anclarModalAlBody)', /anclarModalAlBody/.test(js));
check('inactividad.js llama a anclarModalAlBody al bloquear', /anclarModalAlBody\(document\.getElementById\('modalBloqueoInactividad'\)\)/.test(js));
check('El PHP también lo sube, sin esperar al JS', /document\.body\.appendChild\(bloqueo\)/.test(php));
check('Se sigue usando `inert` para inutilizar las ramas del fondo', /elemento\.inert = true/.test(js));
check('Hay una red de seguridad que anula clics fuera del modal', /vigilarFondoInaccesible/.test(js));
check('El velo es opaco (no se ve el sidebar a través)', /--sget-overlay-osc/.test(php));

console.log('\n' + '='.repeat(64));
console.log(' RESULTADO: ' + ok + ' correctas, ' + fallos + ' fallidas');
console.log('='.repeat(64) + '\n');

/* --------------------------------------------------------------------------
   2) Comportamiento real en el navegador
   -------------------------------------------------------------------------- */
console.log('\n=== El sidebar queda inutilizable con la sesión bloqueada ===');

let probe = '';
try {
    const url = URL_BASE.replace(/\/index\.php.*$/, '/pruebas/_bloqueo_probe.html');
    probe = execFileSync(CHROME, CHROME_ARGS(url), { maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
} catch (e) {
    console.log('  (no se pudo ejecutar la sonda: ' + e.message + ')');
}

const bloque = probe.match(/BLOQUEO_START([\s\S]*?)BLOQUEO_END/);
if (!bloque) {
    check('La sonda del navegador devolvió resultados', false, 'sin BLOQUEO_START');
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
