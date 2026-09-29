/*
 * pruebas/calificaciones-visual.js
 * -----------------------------------------------------------------------------
 * Verificación del FLUJO DE CALIFICACIÓN del pasajero.
 *
 * El fallo que se comprueba: el formulario de calificación (duplicado en
 * pasajero.php e historial_pasajero.php) enviaba a `guardar_calificacion.php`,
 * un archivo que NO existía. Es decir, el sistema de reseñas estaba muerto:
 * nadie podía calificar y los conductores no se enteraban.
 *
 * Uso:
 *     touch pruebas/.habilitar
 *     php -S 127.0.0.1:8899 -t .
 *     node pruebas/calificaciones-visual.js
 *     rm pruebas/.habilitar
 * -----------------------------------------------------------------------------
 */
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const URL_BASE = process.argv[2] || 'http://127.0.0.1:8899/index.php';
const PERFIL = path.join(os.tmpdir(), 'sget-chrome-calif-' + process.pid);

let ok = 0, fallos = 0;
function check(t, c, d = '') {
    if (c) { ok++; console.log('  [OK]    ' + t); }
    else { fallos++; console.log('  [FALLA] ' + t + (d ? ' -> ' + d : '')); }
}

/* --------------------------------------------------------------------------
   1) Estructura
   -------------------------------------------------------------------------- */
console.log('\n=== El flujo de calificación está conectado ===');
check('Existe services/CalificacionService.php', fs.existsSync('services/CalificacionService.php'));
check('El bootstrap lo carga', /CalificacionService\.php/.test(fs.readFileSync('core/bootstrap.php', 'utf8')));
check('Hay UN solo modal de calificación (views/modals/calificar.php)',
      fs.existsSync('views/modals/calificar.php'));
check('El API expone el módulo calificacion',
      /case 'calificacion'/.test(fs.readFileSync('api/index.php', 'utf8')));
check('El modal manda el token CSRF', /campoToken\(\)/.test(fs.readFileSync('views/modals/calificar.php', 'utf8')));

for (const pagina of ['Pasajero/pasajero.php', 'Pasajero/historial_pasajero.php']) {
    const src = fs.readFileSync(pagina, 'utf8');
    check(pagina + ' ya no envía a guardar_calificacion.php',
          !/action="guardar_calificacion\.php"/.test(src));
    check(pagina + ' incluye el modal común', /views\/modals\/calificar\.php/.test(src));
    check(pagina + ' abre el modal con data-sget-modal', /data-sget-modal="modalCalificar"/.test(src));
}
check('El servicio notifica al conductor', /notificarCalificacion/.test(fs.readFileSync('services/CalificacionService.php', 'utf8')));

console.log('\n' + '='.repeat(64));
console.log(' RESULTADO: ' + ok + ' correctas, ' + fallos + ' fallidas');
console.log('='.repeat(64) + '\n');

/* --------------------------------------------------------------------------
   2) Comportamiento real
   -------------------------------------------------------------------------- */
console.log('\n=== Calificar de verdad en el navegador ===');
let probe = '';
try {
    const url = URL_BASE.replace(/\/[^/]*$/, '/pruebas/_calif_probe.html');
    probe = execFileSync(CHROME, [
        '--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage',
        `--user-data-dir=${PERFIL}`,
        '--virtual-time-budget=30000',
        '--dump-dom', url,
    ], { maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
} catch (e) {
    console.log('  (no se pudo ejecutar la sonda: ' + e.message + ')');
}

const bloque = probe.match(/CALIF_START([\s\S]*?)CALIF_END/);
if (!bloque) {
    check('La sonda del navegador devolvió resultados', false, 'sin CALIF_START');
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
