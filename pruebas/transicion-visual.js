/*
 * pruebas/transicion-visual.js
 * -----------------------------------------------------------------------------
 * Verifica en Chrome headless que la transición entre módulos:
 *   1. se carga el CSS y el JS de transiciones
 *   2. el contenido entra animado al cargar
 *   3. un clic en un enlace interno dispara la salida y luego navega
 *   4. la barra de progreso aparece
 *   5. las anclas, los enlaces externos y prefers-reduced-motion NO se animan
 * -----------------------------------------------------------------------------
 *     php -S 127.0.0.1:8899 -t .
 *     node pruebas/transicion-visual.js
 */
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE = process.argv[2] || 'http://127.0.0.1:8899';
const PERFIL = path.join(os.tmpdir(), 'sget-chrome-transicion-' + process.pid);

let ok = 0, fallos = 0;
function check(t, c, d = '') {
    if (c) { ok++; console.log('  [OK]   ' + t); }
    else { fallos++; console.log('  [FALLA] ' + t + (d ? ' -> ' + d : '')); }
}

function cargar(url) {
    return execFileSync(CHROME, [
        '--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage',
        // Bloquea subrecursos externos: si no, el tiempo virtual se congela
        '--host-resolver-rules=MAP * 127.0.0.1, EXCLUDE 127.0.0.1',
        `--user-data-dir=${PERFIL}`, '--virtual-time-budget=14000', '--dump-dom', url,
    ], { maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
}

/* --------------------------------------------------------------------------
   1) Comprobación estática del HTML servido
   -------------------------------------------------------------------------- */
console.log('\n=== Archivos de la transición ===');
const dom = cargar(BASE + '/pruebas/_sesion_test.php?rol=1&ir=Admin%2Frutas.php');
check('Se sirve Admin/rutas.php con sesión de Admin', /Gestión de Rutas/.test(dom));
check('Carga 07-transiciones.css', /07-transiciones\.css/.test(dom));
check('Carga sget-transicion.js', /sget-transicion\.js/.test(dom));
check('La barra de progreso está en el DOM', /sget-barra-progreso/.test(dom));
check('El contenedor .sget-shell existe', /class="sget-shell"/.test(dom));
check('La hoja define la animación de entrada @keyframes sget-entrada',
    /@keyframes sget-entrada/.test(fs.readFileSync('assets/css/07-transiciones.css', 'utf8')));
check('La hoja define la animación de salida @keyframes sget-velo',
    /@keyframes sget-velo/.test(fs.readFileSync('assets/css/07-transiciones.css', 'utf8')));
check('La hoja respeta prefers-reduced-motion',
    (fs.readFileSync('assets/css/07-transiciones.css', 'utf8').match(/prefers-reduced-motion/g) || []).length >= 2);
check('El JS expone SGETTransicion', /window\.SGETTransicion/.test(fs.readFileSync('assets/js/sget-transicion.js', 'utf8')));

/* --------------------------------------------------------------------------
   2) Comportamiento real en el navegador
   -------------------------------------------------------------------------- */
console.log('\n=== Comportamiento en el navegador ===');

const probe = fs.readFileSync(path.join(__dirname, '_transicion_probe.html'), 'utf8');
const urlProbe = BASE + '/pruebas/_transicion_probe.html';
fs.writeFileSync(path.join(os.tmpdir(), 'sget-transicion-probe.html'), probe);

const salida = cargar(urlProbe);
const m = salida.match(/TRANS_PROBE_START([\s\S]*?)TRANS_PROBE_END/);

if (!m) {
    check('La sonda de transición se ejecutó', false, 'no devolvió resultados');
} else {
    m[1].trim().split('\n').forEach((line) => {
        if (!line.trim() || !line.includes('|') || line.startsWith('INFO')) return;
        const correcto = line.startsWith('OK');
        const p = line.replace('|', '~~').split('~~').map((s) => s.trim());
        check(p[1] + (p[2] ? '  [' + p[2] + ']' : ''), correcto, correcto ? '' : p[2] || '');
    });
}

console.log('\n' + '='.repeat(60));
console.log(' TOTAL: ' + ok + ' correctas, ' + fallos + ' fallidas');
console.log('='.repeat(60) + '\n');
process.exit(fallos === 0 ? 0 : 1);
