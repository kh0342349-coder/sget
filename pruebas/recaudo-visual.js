/*
 * pruebas/recaudo-visual.js
 * -----------------------------------------------------------------------------
 * Verificación del MÓDULO DE RECAUDO (Admin/asignaciones.php).
 *
 * El fallo que se comprueba: insertaba las reservas con `estado_pago =
 * 'Completado'`, un valor que NO existe en el ENUM de la tabla `reserva`
 * ('Pendiente','Confirmada','Cancelada'). El INSERT fallaba, el código no
 * comprobaba el resultado y aun así pintaba "¡Asignación registrada con
 * éxito!" con el ticket de la reserva 0. En la práctica no se cobraba nada, y
 * como ninguna reserva quedaba 'Confirmada', el módulo de Ganancias mostraba
 * siempre $0.
 *
 * Uso:
 *     touch pruebas/.habilitar
 *     php -S 127.0.0.1:8899 -t .
 *     node pruebas/recaudo-visual.js
 *     rm pruebas/.habilitar
 * -----------------------------------------------------------------------------
 */
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const URL_BASE = process.argv[2] || 'http://127.0.0.1:8899/index.php';
const PERFIL = path.join(os.tmpdir(), 'sget-chrome-recaudo-' + process.pid);

let ok = 0, fallos = 0;
function check(t, c, d = '') {
    if (c) { ok++; console.log('  [OK]    ' + t); }
    else { fallos++; console.log('  [FALLA] ' + t + (d ? ' -> ' + d : '')); }
}

/* --------------------------------------------------------------------------
   1) Estructura
   -------------------------------------------------------------------------- */
console.log('\n=== El recaudo usa el servicio y respeta el ENUM ===');
check('Existe services/ReservaService.php', fs.existsSync('services/ReservaService.php'));
check('El bootstrap lo carga', /ReservaService\.php/.test(fs.readFileSync('core/bootstrap.php', 'utf8')));
const mod = fs.readFileSync('Admin/asignaciones.php', 'utf8');
// Se busca fuera de la cabecera de comentario, que explica el fallo a propósito.
const codigo = mod.replace(/\/\*[\s\S]*?\*\//g, '');
check('El código NO usa el valor inexistente "Completado"', !/'Completado'/.test(codigo));
check('El módulo no escribe SQL propio (delega en el servicio)',
      !/INSERT INTO reserva/i.test(mod) && !/UPDATE reserva/i.test(mod));
check('El servicio usa las constantes del ENUM',
      /Config::RES_CONFIRMADA/.test(fs.readFileSync('services/ReservaService.php', 'utf8')));
check('El API expone crear / cobrar / cancelar / porViaje',
      ['crear', 'cobrar', 'cancelar', 'porViaje', 'ocasional']
          .every(a => fs.readFileSync('api/index.php', 'utf8').includes("'" + a + "'")));
check('El módulo muestra los indicadores de caja',
      /Recaudo de hoy/.test(mod) && /por cobrar/i.test(mod));

console.log('\n' + '='.repeat(64));
console.log(' RESULTADO: ' + ok + ' correctas, ' + fallos + ' fallidas');
console.log('='.repeat(64) + '\n');

/* --------------------------------------------------------------------------
   2) Comportamiento real
   -------------------------------------------------------------------------- */
console.log('\n=== Cobrar de verdad en el navegador ===');
let probe = '';
try {
    const url = URL_BASE.replace(/\/[^/]*$/, '/pruebas/_recaudo_probe.html');
    probe = execFileSync(CHROME, [
        '--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage',
        `--user-data-dir=${PERFIL}`,
        '--virtual-time-budget=40000',
        '--dump-dom', url,
    ], { maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
} catch (e) {
    console.log('  (no se pudo ejecutar la sonda: ' + e.message + ')');
}

const bloque = probe.match(/RECAUDO_START([\s\S]*?)RECAUDO_END/);
if (!bloque) {
    check('La sonda del navegador devolvió resultados', false, 'sin RECAUDO_START');
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
