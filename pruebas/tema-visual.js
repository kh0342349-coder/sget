/*
 * pruebas/tema-visual.js
 * -----------------------------------------------------------------------------
 * Verifica en Chrome headless que el tema CLARO y el OSCURO se apliquen de
 * verdad en TODAS las páginas y que la interfaz sea legible en ambos.
 *
 * Qué comprueba por página y por tema:
 *   · <html> tiene la clase y el atributo correctos
 *   · el fondo del body y el del contenido difieren del color de texto
 *     (contraste real, no solo "aplica la clase")
 *   · los botones primarios tienen fondo visible
 *   · los inputs no son invisibles sobre su fondo
 *   · en claro NO queda ninguna superficie oscura residual
 *   · en oscuro NO queda ninguna superficie blanca residual
 *
 *     php -S 127.0.0.1:8899 -t .
 *     node pruebas/tema-visual.js
 */
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE = process.argv[2] || 'http://127.0.0.1:8899';
const PERFIL = path.join(os.tmpdir(), 'sget-chrome-tema-' + process.pid);

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
        `--user-data-dir=${PERFIL}`, '--virtual-time-budget=16000', '--dump-dom', url,
    ], { maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
}

process.on('exit', () => { try { fs.rmSync(PERFIL, { recursive: true, force: true }); } catch (e) {} });

/* --------------------------------------------------------------------------
   1) Todas las páginas deben cargar el script de tema
   -------------------------------------------------------------------------- */
console.log('\n=== El script de tema está en todas las páginas ===');
const PAGINAS = [
    { rol: 1, ruta: 'Admin/admin.php' },
    { rol: 1, ruta: 'Admin/rutas.php' },
    { rol: 1, ruta: 'Admin/viajes.php' },
    { rol: 1, ruta: 'Admin/vehiculos.php' },
    { rol: 1, ruta: 'Admin/usuarios.php' },
    { rol: 1, ruta: 'Admin/logs.php' },
    { rol: 1, ruta: 'Admin/asignaciones.php' },
    { rol: 1, ruta: 'Admin/reportes.php' },
    { rol: 1, ruta: 'Admin/gestion_permisos.php' },
    { rol: 2, ruta: 'Conductor/conductor.php' },
    { rol: 2, ruta: 'Conductor/viajes_conductor.php' },
    { rol: 2, ruta: 'Conductor/viaje_asignado.php' },
    { rol: 2, ruta: 'Conductor/resenas_conductor.php' },
    { rol: 3, ruta: 'Pasajero/pasajero.php' },
    { rol: 3, ruta: 'Pasajero/viajes_pasajero.php' },
    { rol: 3, ruta: 'Pasajero/historial_pasajero.php' },
    { rol: 3, ruta: 'Pasajero/calificar.php' },
    { rol: 0, ruta: 'index.php' },
];

// Primero se crea la sesión por rol
for (const rol of [0, 1, 2, 3]) {
    const url = rol === 0 ? BASE + '/index.php'
                         : BASE + '/pruebas/_sesion_test.php?rol=' + rol;
    if (rol !== 0) cargar(url);
}

for (const p of PAGINAS) {
    const dom = cargar(BASE + '/' + p.ruta);
    check(`${p.ruta} carga theme-init.js`, dom.includes('theme-init.js'));
    check(`${p.ruta} no tiene 404 de theme-toggle.js`, !dom.includes('theme-toggle.js'));
}

/* --------------------------------------------------------------------------
   2) Contraste real en ambos temas, página por página
   -------------------------------------------------------------------------- */
console.log('\n=== Contraste real en tema claro y oscuro ===');

for (const p of PAGINAS) {
    if (p.rol === 0) continue;   // la landing se comprueba aparte
    const url = BASE + '/pruebas/_tema_probe.html?rol=' + p.rol
              + '&pagina=' + encodeURIComponent(p.ruta);

    let salida = '';
    try {
        salida = cargar(url);
    } catch (e) {
        check(p.ruta + ' · sonda de tema', false, String(e.message).slice(0, 90));
        continue;
    }

    const m = salida.match(/TEMA_PROBE_START([\s\S]*?)TEMA_PROBE_END/);
    if (!m) {
        check(p.ruta + ' · la sonda de tema responde', false, 'sin resultados');
        continue;
    }

    const etiqueta = p.ruta.replace('Admin/', '').replace('Conductor/', 'cond-')
                        .replace('Pasajero/', 'pasa-').replace('.php', '');
    m[1].trim().split('\n').forEach((line) => {
        if (!line.trim() || !line.includes('|') || line.startsWith('INFO')) return;
        const correcto = line.startsWith('OK');
        const q = line.replace('|', '~~').split('~~').map((x) => x.trim());
        check(etiqueta + ' ' + q[1], correcto, correcto ? '' : (q[2] || ''));
    });
}

console.log('\n' + '='.repeat(60));
console.log(' TOTAL: ' + ok + ' correctas, ' + fallos + ' fallidas');
console.log('='.repeat(60) + '\n');
process.exit(fallos === 0 ? 0 : 1);
