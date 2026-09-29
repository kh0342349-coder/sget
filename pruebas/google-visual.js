/*
 * pruebas/google-visual.js
 * -----------------------------------------------------------------------------
 * Verificación con navegador real (Chrome headless) de que el botón
 * "Continuar con Google" aparece en el modal de inicio de sesión.
 *
 * Uso:
 *     php -S 127.0.0.1:8899 -t .
 *     node pruebas/google-visual.js
 * -----------------------------------------------------------------------------
 */
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const URL_BASE = process.argv[2] || 'http://127.0.0.1:8899/index.php';
const PERFIL = path.join(os.tmpdir(), 'sget-chrome-google-' + process.pid);

let ok = 0, fallos = 0;
function check(t, c, d = '') {
    if (c) { ok++; console.log('  [OK]    ' + t); }
    else { fallos++; console.log('  [FALLA] ' + t + (d ? ' -> ' + d : '')); }
}

const CHROME_ARGS = (url) => [
    '--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage',
    // Sin bloquear accounts.google.com: el botón oficial lo dibuja GSI desde
    // el SDK remoto, y sin red la sonda solo vería el aviso de respaldo.
    `--user-data-dir=${PERFIL}`,
    '--virtual-time-budget=25000',
    '--dump-dom',
    url,
];

/* --------------------------------------------------------------------------
   1) Estructura servida
   -------------------------------------------------------------------------- */
console.log('\n=== Estructura del botón de Google en el HTML servido ===');
let dom = '';
try {
    dom = execFileSync(CHROME, CHROME_ARGS(URL_BASE), { maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
} catch (e) {
    console.log('  (no se pudo abrir Chrome: ' + e.message + ')');
}
fs.writeFileSync(path.join(os.tmpdir(), 'sget-google-dom.html'), dom);

check('El SDK de Google Identity Services está en el <head>',
      /accounts\.google\.com\/gsi\/client/.test(dom));
check('Se carga assets/js/sget-google.js', /assets\/js\/sget-google\.js/.test(dom));
check('El modal de login tiene la zona [data-sget-google-zona]',
      /data-sget-google-zona/.test(dom));
check('El hueco del botón es [data-sget-google] (no la clase g_id_signin)',
      /data-sget-google[=" ]/.test(dom) && !/class="[^"]*\bg_id_signin\b/.test(dom));
check('Hay un aviso de respaldo si Google no carga', /data-sget-google-aviso/.test(dom));
check('Se muestra el separador «o continúa con»', /sget-sep-google__texto/.test(dom));

console.log('\n=== El motor de modales avisa de las aperturas ===');
const modalJs = fs.readFileSync('assets/js/sget-modal.js', 'utf8');
check('sget-modal.js emite el evento sget:modal-abierto',
      /sget:modal-abierto/.test(modalJs));
const googleJs = fs.readFileSync('assets/js/sget-google.js', 'utf8');
check('sget-google.js escucha sget:modal-abierto',
      /addEventListener\('sget:modal-abierto'/.test(googleJs));
check('sget-google.js no llama a abrirPanel() para montarse',
      !/abrirPanel\s*\(/.test(googleJs.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, '')));
check('El client_id de Google está declarado en un solo sitio',
      (googleJs.match(/apps\.googleusercontent\.com/g) || []).length === 1 &&
      !/4uh6adhaklk2bpsvli6hnmrgg0bgktlp/.test(fs.readFileSync('index.php', 'utf8')));

console.log('\n' + '='.repeat(64));
console.log(' RESULTADO: ' + ok + ' correctas, ' + fallos + ' fallidas');
console.log('='.repeat(64) + '\n');

/* --------------------------------------------------------------------------
   2) Interacción real (Chrome headless sobre _google_probe.html)
   -------------------------------------------------------------------------- */
console.log('\n=== Comportamiento real en el navegador ===');

let probe = '';
try {
    const url = URL_BASE.replace(/\/index\.php.*$/, '/pruebas/_google_probe.html');
    probe = execFileSync(CHROME, CHROME_ARGS(url), { maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
} catch (e) {
    console.log('  (no se pudo ejecutar la sonda: ' + e.message + ')');
}

const bloque = probe.match(/GOOGLE_PROBE_START([\s\S]*?)GOOGLE_PROBE_END/);
if (!bloque) {
    check('La sonda del navegador devolvió resultados', false, 'sin GOOGLE_PROBE_START');
} else {
    bloque[1].split('\n')
        .map(l => l.trim())
        .filter(l => l && !/^FALLOS=/.test(l) && l !== 'FALLOS=0')
        .forEach(l => {
        const esInfo = l.startsWith('INFO');
        const buena = l.startsWith('OK');
        check(l.split('|').slice(1).join('|').trim() + (esInfo ? ' [info]' : ''), esInfo || buena, l);
    });
    const f = bloque[1].match(/FALLOS=(\d+)/);
    check('La sonda no reporta fallos', f && Number(f[1]) === 0, f ? 'fallos=' + f[1] : 'sin dato');
}

console.log('\n' + '='.repeat(64));
console.log(' TOTAL: ' + ok + ' correctas, ' + fallos + ' fallidas');
console.log('='.repeat(64) + '\n');
process.exit(fallos ? 1 : 0);
