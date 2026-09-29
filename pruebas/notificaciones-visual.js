/*
 * pruebas/notificaciones-visual.js
 * -----------------------------------------------------------------------------
 * Verificación del BUZÓN DE NOTIFICACIONES de Pasajero y Conductor.
 *
 * Fallos que se comprueban (los dos estaban vivos):
 *   1. Las acciones del buzón dependían de assets/js/sget-page.js, que no se
 *      carga en las páginas de Pasajero ni de Conductor → «marcar como leída»
 *      no tenía manejador y el buzón era de adorno.
 *   2. El conductor nunca recibía ningún aviso: solo se notificaba a los
 *      pasajeros en la cancelación de viajes.
 *
 * Uso:
 *     touch pruebas/.habilitar
 *     php -S 127.0.0.1:8899 -t .
 *     node pruebas/notificaciones-visual.js
 *     rm pruebas/.habilitar
 * -----------------------------------------------------------------------------
 */
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const URL_BASE = process.argv[2] || 'http://127.0.0.1:8899/index.php';
const PERFIL = path.join(os.tmpdir(), 'sget-chrome-noti-' + process.pid);

let ok = 0, fallos = 0;
function check(t, c, d = '') {
    if (c) { ok++; console.log('  [OK]    ' + t); }
    else { fallos++; console.log('  [FALLA] ' + t + (d ? ' -> ' + d : '')); }
}

/* --------------------------------------------------------------------------
   1) Estructura del código
   -------------------------------------------------------------------------- */
console.log('\n=== El buzón es autónomo ===');
const js  = fs.readFileSync('assets/js/sget-notificaciones.js', 'utf8');
const php = fs.readFileSync('views/modals/notificaciones.php', 'utf8');
const svc = fs.readFileSync('services/NotificacionService.php', 'utf8');

check('Existe assets/js/sget-notificaciones.js', true);
check('El buzón carga su propio JS (no depende del de las páginas CRUD)',
      /sget-notificaciones\.js/.test(php)
      && !/<script[^>]+src="[^"]*sget-page\.js/.test(php));
check('El JS se carga con Config::basePath() (la cabecera se incluye en carpetas distintas)',
      /Config::basePath\(\)/.test(php));
check('El JS actualiza el contador sin recargar la página',
      /data-sget-noti-contador/.test(js) && /pintarContador/.test(js));
check('El JS sondea el servidor para detectar avisos nuevos', /INTERVALO_MS/.test(js));

console.log('\n=== El conductor también recibe avisos ===');
check('El servicio tiene el tipo «viaje_asignado»', /TIPO_ASIGNACION\s*=/.test(svc));
check('El servicio avisa al conductor de una asignación', /notificarAsignacionConductor/.test(svc));
check('El servicio avisa al conductor de una cancelación', /notificarCancelacionConductor/.test(svc));
check('ViajeService avisa al conductor al cancelar', /notificarCancelacionConductor/.test(fs.readFileSync('services/ViajeService.php', 'utf8')));
check('ViajeService avisa al conductor al asignar', /notificarAsignacionConductor/.test(fs.readFileSync('services/ViajeService.php', 'utf8')));
check('Hay generador de recordatorios de salida', /function recordatorios/.test(svc));
check('La API expone contador / eliminar / vaciar / recordatorios',
      ['contador', 'eliminar', 'vaciar', 'recordatorios']
          .every(a => fs.readFileSync('api/index.php', 'utf8').includes("'" + a + "'")));

console.log('\n' + '='.repeat(64));
console.log(' RESULTADO: ' + ok + ' correctas, ' + fallos + ' fallidas');
console.log('='.repeat(64) + '\n');

/* --------------------------------------------------------------------------
   2) Comportamiento real, para pasajero y conductor
   -------------------------------------------------------------------------- */
for (const rol of [3, 2]) {
    console.log('\n=== Flujo real en el navegador · rol ' + (rol === 3 ? 'Pasajero' : 'Conductor') + ' ===');
    let probe = '';
    try {
        const url = URL_BASE.replace(/\/[^/]*$/, '/pruebas/_noti_probe.html?rol=' + rol);
        probe = execFileSync(CHROME, [
            '--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage',
            `--user-data-dir=${PERFIL}-${rol}`,
            '--virtual-time-budget=30000',
            '--dump-dom', url,
        ], { maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
    } catch (e) {
        console.log('  (no se pudo ejecutar la sonda: ' + e.message + ')');
    }

    const bloque = probe.match(/NOTI_START([\s\S]*?)NOTI_END/);
    if (!bloque) {
        check('rol ' + rol + ': la sonda devolvió resultados', false, 'sin NOTI_START');
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
    check('rol ' + rol + ': la sonda no reporta fallos', f && Number(f[1]) === 0, f ? 'fallos=' + f[1] : 'sin dato');
}

console.log('\n' + '='.repeat(64));
console.log(' TOTAL: ' + ok + ' correctas, ' + fallos + ' fallidas');
console.log('='.repeat(64) + '\n');
process.exit(fallos ? 1 : 0);
