/*
 * pruebas/informacion-visual.js
 * -----------------------------------------------------------------------------
 * Verificación del MÓDULO DE INFORMACIÓN del administrador (Admin/reportes.php).
 *
 * Comprueba lo que el módulo debe cumplir tras dejar de ser un exportador CSV:
 *   · las 5 secciones (general, viajes, usuarios, rutas, ganancias) cargan,
 *   · no queda ningún botón/enlace de exportación,
 *   · no hay errores PHP ni iconos rotos (Font Awesome FREE).
 *
 * Requiere la sonda de sesión: el servidor debe arrancar con SGET_DEBUG=1
 * (o SGET_SONDA=1) para que pruebas/_sesion_admin.php abra la sesión de admin.
 *
 * Uso:
 *     SGET_DEBUG=1 php -S 127.0.0.1:8899 -t .
 *     node pruebas/informacion-visual.js
 * -----------------------------------------------------------------------------
 */
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const URL_BASE = process.argv[2] || 'http://127.0.0.1:8899/index.php';
const PERFIL = path.join(os.tmpdir(), 'sget-chrome-info-' + process.pid);

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
   1) Estructura del código
   -------------------------------------------------------------------------- */
console.log('\n=== Estructura del módulo ===');
const pagina = fs.readFileSync('Admin/reportes.php', 'utf8');
check('La página NO contiene SQL (todo vive en el servicio)',
      !/\b(SELECT|INSERT|UPDATE|DELETE)\b[\s\S]{0,40}\bFROM\b/i.test(pagina));
check('La página NO exporta CSV',
      !/Content-Disposition|text\/csv|exportarCsv/i.test(pagina));
check('La página usa InformacionService', /InformacionService::/.test(pagina));
check('El servicio existe', fs.existsSync('services/InformacionService.php'));
check('El servicio NO tiene exportación CSV', !/exportarCsv|text\/csv/.test(fs.readFileSync('services/InformacionService.php', 'utf8')));
check('El bootstrap carga InformacionService',
      /InformacionService\.php/.test(fs.readFileSync('core/bootstrap.php', 'utf8')));
// El nombre `ReporteService` lo reutiliza hoy el módulo de QUEJAS de los
// pasajeros (services/ReporteService.php): lo que no debe volver es el servicio
// ANALÍTICO que solo servía para exportar CSV.
check('El servicio analítico de reportes fue sustituido por InformacionService',
      fs.existsSync('services/InformacionService.php')
      && !/exportarCsv|text\/csv/.test(fs.readFileSync('services/InformacionService.php', 'utf8')));
check('Las 5 secciones están declaradas',
      ['general', 'viajes', 'usuarios', 'rutas', 'ganancias'].every(s => pagina.includes("'" + s + "'")));
check('Se mantienen las 5 pestañas visibles',
      ['Información general', 'Historial de viajes', 'Historial de usuarios', 'Historial de rutas', 'Ganancias']
          .every(s => pagina.includes(s)));
check('El menú lateral ya no dice "Reportes Generales"',
      !/Reportes Generales/.test(fs.readFileSync('includes/sidebar.php', 'utf8')));

console.log('\n' + '='.repeat(64));
console.log(' RESULTADO: ' + ok + ' correctas, ' + fallos + ' fallidas');
console.log('='.repeat(64) + '\n');

/* --------------------------------------------------------------------------
   2) Comportamiento real en el navegador
   -------------------------------------------------------------------------- */
console.log('\n=== Las cinco secciones cargan en el navegador ===');

let probe = '';
try {
    const url = URL_BASE.replace(/\/index\.php.*$/, '/pruebas/_info_probe.html');
    probe = execFileSync(CHROME, CHROME_ARGS(url), { maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
} catch (e) {
    console.log('  (no se pudo ejecutar la sonda: ' + e.message + ')');
}

const bloque = probe.match(/INFO_PROBE_START([\s\S]*?)INFO_PROBE_END/);
if (!bloque) {
    check('La sonda del navegador devolvió resultados', false,
          'sin INFO_PROBE_START (¿arrancaste el servidor con SGET_DEBUG=1?)');
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
