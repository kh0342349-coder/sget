/*
 * pruebas/cancelacion-visual.js
 * -----------------------------------------------------------------------------
 * Prueba en navegador real del flujo de cancelación de viaje:
 *   · el modal se abre con los datos del viaje
 *   · el título, la salida, la duración y los pasajeros se rellenan
 *   · la anotación es OBLIGATORIA si el viaje aún no sale
 *   · el contador de caracteres y el error en vivo funcionan
 *   · el motivo vacío también bloquea el envío
 *   · un viaje VENCIDO muestra el aviso y oculta el formulario
 * -----------------------------------------------------------------------------
 *     php -S 127.0.0.1:8899 -t .
 *     node pruebas/cancelacion-visual.js
 */
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE = process.argv[2] || 'http://127.0.0.1:8899';
const PERFIL = path.join(os.tmpdir(), 'sget-chrome-cancelacion-' + process.pid);

let ok = 0, fallos = 0;
function check(t, c, d = '') {
    if (c) { ok++; console.log('  [OK]   ' + t); }
    else { fallos++; console.log('  [FALLA] ' + t + (d ? ' -> ' + d : '')); }
}

function cargar(url) {
    return execFileSync(CHROME, [
        '--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage',
        // Bloquea subrecursos externos: si no, el tiempo virtual se congela
        '--host-resolver-rules=MAP * 127.0.0.1, EXCLUDE 127.0.0.1, EXCLUDE cdn.tailwindcss.com',
        `--user-data-dir=${PERFIL}`, '--virtual-time-budget=18000', '--dump-dom', url,
    ], { maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
}

console.log('\n=== Estructura del modal en el HTML servido ===');
const dom = cargar(BASE + '/pruebas/_sesion_test.php?rol=1&ir=Admin%2Fviajes.php');
for (const aguja of ['id="modalCancelarViaje"', 'data-sget-anotacion', 'data-contador',
                    'name="anotacion"', 'name="motivo"', 'data-sget-texto="pasajeros"',
                    'data-sget-mostrar="avisoVencido"', 'data-sget-cerrar-al-guardar']) {
    check(`Admin/viajes.php contiene «${aguja}»`, dom.includes(aguja));
}
check('La página no trae el diálogo duplicado en JS',
    !dom.includes('confirmarMotivo('), 'quedó una segunda implementación');

console.log('\n=== Comportamiento en el navegador ===');
const salida = cargar(BASE + '/pruebas/_cancelacion_probe.html');
const m = salida.match(/CANCEL_PROBE_START([\s\S]*?)CANCEL_PROBE_END/);

if (!m) {
    check('La sonda de cancelación se ejecutó', false, 'no devolvió resultados');
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
